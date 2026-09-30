<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Illuminate\Database\Connection;
use Throwable;

final readonly class BackupBundle
{
    public function __construct(private DatabaseBackup $databaseBackup, private PrivateBackupFiles $files) {}

    public function create(Connection $database, string $directory, string $applicationRoot, string $mediaRoot, int $timeout): string
    {
        $schema = $database->getSchemaBuilder();
        if (! $schema->hasColumns('media_versions', ['id', 'media_asset_id', 'version_no', 'storage_key', 'bytes', 'sha256'])
            || ! $schema->hasColumns('media_assets', ['id'])) {
            throw new BackupFailure('Media schema is missing or incompatible; a complete backup cannot be created.');
        }
        $directory = $this->databaseBackup->safeDirectory($directory, $applicationRoot);
        $mediaRoot = rtrim(str_replace('\\', '/', $mediaRoot), '/');
        $this->files->validatePath($mediaRoot);
        // An unused media disk may not exist yet. Any committed version still fails below.
        if (file_exists($mediaRoot) || is_link($mediaRoot)) {
            $mediaRoot = $this->files->canonical($mediaRoot);
            if (! is_dir($mediaRoot)) {
                throw new BackupFailure('Configured media storage is not a directory.');
            }
        }
        if ($this->files->contains($mediaRoot, $directory) || $this->files->contains($directory, $mediaRoot) || $directory === $mediaRoot) {
            throw new BackupFailure('Backup and media directories must be separate.');
        }
        $mask = umask(0077);
        $partial = null;
        try {
            $this->files->directory($directory);
            $name = 'lessons-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(8));
            $candidate = $directory.'/'.$name.'.partial';
            if (! @mkdir($candidate, 0700)) {
                throw new BackupFailure('Cannot create the private partial backup bundle.');
            }
            $partial = $candidate;
            $this->files->permissions($partial, 0700);
            // SQL first. With immutable media and no physical deletion, the later
            // committed version list covers its snapshot; concurrent new files are harmless extras.
            $sql = $this->databaseBackup->create($database->getConfig(), $partial, $applicationRoot, $timeout);
            $sqlHash = @hash_file('sha256', $sql);
            if ($sqlHash === false || ! @rename($sql, $partial.'/database.sql')) {
                throw new BackupFailure('Cannot verify or prepare the bundled database dump.');
            }
            $manifest = ['formatVersion' => 1, 'createdAt' => gmdate('c'),
                'database' => ['file' => 'database.sql', 'bytes' => filesize($partial.'/database.sql'), 'sha256' => $sqlHash],
                'media' => []];
            $this->files->directory($partial.'/media');
            foreach ($database->table('media_versions')->orderBy('id')->cursor() as $version) {
                $key = $this->storageKey($version);
                $source = $this->files->canonical($mediaRoot.'/'.$key);
                if (! $this->files->contains($mediaRoot, $source) || ! is_file($source)) {
                    throw new BackupFailure('A media source is not a contained regular file.');
                }
                $target = $partial.'/media/'.$key;
                $this->files->directory(dirname($target));
                $this->copy($source, $target, (int) $version->bytes, $version->sha256);
                $manifest['media'][] = ['assetId' => $version->media_asset_id, 'versionId' => $version->id,
                    'versionNo' => (int) $version->version_no, 'file' => 'media/'.$key,
                    'bytes' => (int) $version->bytes, 'sha256' => $version->sha256];
            }
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            $stream = $this->files->open($partial.'/manifest.json');
            try {
                if (fwrite($stream, $json) !== strlen($json) || ! fflush($stream)) {
                    throw new BackupFailure('Cannot write the private backup manifest.');
                }
            } finally {
                fclose($stream);
            }
            $final = $directory.'/'.$name;
            if (! @rename($partial, $final)) {
                throw new BackupFailure('Cannot finalize the private backup bundle.');
            }
            $partial = null;

            return $final;
        } catch (BackupFailure $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new BackupFailure('Bundle creation failed; inspect database access, private storage and disk space privately.');
        } finally {
            try {
                if ($partial !== null) {
                    $this->files->cleanup($partial, $directory);
                }
            } finally {
                umask($mask);
            }
        }
    }

    private function storageKey(object $version): string
    {
        $uuid = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';
        $pattern = '~\Aversions/('.$uuid.')/('.$uuid.')\.(png|jpg|webp)\z~';
        if (! is_string($version->storage_key) || ! preg_match($pattern, $version->storage_key, $matches)
            || $matches[1] !== $version->media_asset_id || $matches[2] !== $version->id
            || (int) $version->bytes < 1 || (int) $version->version_no < 1
            || ! is_string($version->sha256) || ! preg_match('/\A[a-f0-9]{64}\z/', $version->sha256)) {
            throw new BackupFailure('A media version has an invalid canonical storage key or integrity metadata.');
        }

        return $version->storage_key;
    }

    private function copy(string $source, string $target, int $bytes, string $sha256): void
    {
        $input = @fopen($source, 'rb');
        if ($input === false) {
            throw new BackupFailure('Cannot read a required immutable media file.');
        }
        $output = null;
        try {
            $stat = fstat($input);
            if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || $stat['size'] !== $bytes) {
                throw new BackupFailure('Immutable media size or file type does not match its database version.');
            }
            $output = $this->files->open($target);
            $hash = hash_init('sha256');
            $copied = 0;
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);
                if ($chunk === false || ($chunk === '' && ! feof($input))) {
                    throw new BackupFailure('Cannot read the complete immutable media file.');
                }
                $copied += strlen($chunk);
                if ($copied > $bytes || fwrite($output, $chunk) !== strlen($chunk)) {
                    throw new BackupFailure('Cannot copy the complete immutable media file.');
                }
                hash_update($hash, $chunk);
            }
            if ($copied !== $bytes || ! hash_equals($sha256, hash_final($hash)) || ! fflush($output)) {
                throw new BackupFailure('Immutable media checksum does not match its database version.');
            }
        } finally {
            fclose($input);
            if ($output !== null) {
                fclose($output);
            }
        }
        clearstatcache(true, $target);
        if (filesize($target) !== $bytes || ! hash_equals($sha256, (string) @hash_file('sha256', $target))) {
            throw new BackupFailure('Copied media verification failed.');
        }
    }
}
