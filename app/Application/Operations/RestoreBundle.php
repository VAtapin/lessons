<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Illuminate\Database\Connection;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/** Restore acceptance only. This class deliberately cannot target a production database. */
final readonly class RestoreBundle
{
    public function __construct(private RestoreTarget $guard, private DatabaseRestoreProcess $process, private ExecutableFinder $executables, private DatabaseBackup $backup, private PrivateBackupFiles $files, private RestoreMediaCoverage $coverage) {}

    public function restore(Connection $database, string $environment, string $bundle, string $manifestHash, string $mediaRoot, string $applicationRoot, int $timeout): array
    {
        $this->guard->assertSafe($database, $environment);
        if ($timeout < 1 || $timeout > 3600 || ! preg_match('/\A[a-f0-9]{64}\z/', $manifestHash)) {
            throw new BackupFailure('Restore requires a manifest SHA256 and timeout from 1 to 3600 seconds.');
        }
        $bundle = $this->files->canonical($bundle);
        $mediaRoot = $this->backup->safeDirectory($mediaRoot, $applicationRoot);
        $this->files->validatePath($mediaRoot);
        if ($bundle === $mediaRoot || $this->files->contains($bundle, $mediaRoot) || $this->files->contains($mediaRoot, $bundle)) {
            throw new BackupFailure('Restore media and backup source must be separate.');
        }
        $this->emptyMedia($mediaRoot);
        $manifest = $this->manifest($bundle, $manifestHash);
        $binary = $this->executables->find('mariadb', extraDirs: ['/usr/bin', '/usr/local/bin']);
        if ($binary === null) {
            throw new BackupFailure('The MariaDB import client is unavailable.');
        }
        $parent = $this->files->canonical(dirname($mediaRoot));
        $partial = $parent.'/lessons-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(8)).'.partial';
        $mask = umask(0077);
        try {
            $this->files->directory($partial);
            $this->files->directory($partial.'/media');
            // Every byte is verified before target writes; import uses a private stable snapshot.
            foreach (array_merge([$manifest['database']], $manifest['media']) as $entry) {
                $target = $partial.'/'.$entry['file'];
                $this->files->directory(dirname($target));
                $input = fopen($bundle.'/'.$entry['file'], 'rb');
                $output = $this->files->open($target);
                try {
                    if (stream_copy_to_stream($input, $output) !== $entry['bytes'] || ! fflush($output)) {
                        throw new BackupFailure('Cannot stage the complete verified bundle.');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                $this->verifyFile($partial, $entry);
            }
            $sql = fopen($partial.'/database.sql', 'rb');
            try {
                while (($line = fgets($sql)) !== false) {
                    if (preg_match('/\A\s*(?:\/\*![0-9]+\s*)?(?:CREATE\s+DATABASE|USE\s+)/i', $line)) {
                        throw new BackupFailure('Database-scoped legacy dumps require a separately reviewed restore procedure; create a new schema-scoped bundle for this isolated test.');
                    }
                }
            } finally {
                fclose($sql);
            }
            $credentials = $partial.'/client.cnf';
            $config = $database->getConfig();
            $settings = "[client]\n";
            foreach (['user' => $config['username'] ?? '', 'password' => $config['password'] ?? '', 'host' => $config['host'], 'port' => (string) ($config['port'] ?? 3306)] as $key => $value) {
                if (! is_string($value) || str_contains($value, "\0")) {
                    throw new BackupFailure('Unsupported isolated client configuration.');
                }
                $settings .= $key.'="'.str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $value)."\"\n";
            }
            $stream = $this->files->open($credentials);
            try {
                if (fwrite($stream, $settings) !== strlen($settings)) {
                    throw new BackupFailure('Cannot write private isolated client settings.');
                }
            } finally {
                fclose($stream);
            }

            return (new RestoreDatabaseLock($this->guard))->run($database, $environment, function (Connection $pinned, callable $assertOwned) use ($environment, $binary, $credentials, $partial, $timeout, $manifest, $mediaRoot, $manifestHash): array {
                $this->guard->assertSafe($pinned, $environment);
                $assertOwned();
                $this->emptyMedia($mediaRoot);
                $this->process->run([$binary, '--defaults-file='.$credentials, '--binary-mode', '--skip-reconnect', '--batch', '--database=lessons_restore_test'], $partial.'/database.sql', $timeout);
                $assertOwned();
                $unreferenced = $this->verifyRestoredMedia($pinned, $manifest['media'], $manifest['requiredMediaVersionIds'] ?? null);
                $assertOwned();
                $this->emptyMedia($mediaRoot);
                if (! @rename($partial.'/media', $mediaRoot)) {
                    throw new BackupFailure('Cannot finalize isolated restored media; keep the test target isolated.');
                }

                return ['database' => 'lessons_restore_test', 'mediaVersions' => count($manifest['media']), 'unreferencedMediaVersions' => $unreferenced, 'manifestSha256' => $manifestHash];
            });
        } catch (BackupFailure $failure) {
            throw $failure;
        } catch (Throwable) {
            throw new BackupFailure('Isolated restore failed; the test target may contain a partial import and must remain isolated.');
        } finally {
            try {
                if (is_dir($partial)) {
                    $this->files->cleanup($partial, $parent);
                }
            } finally {
                umask($mask);
            }
        }
    }

    private function emptyMedia(string $path): void
    {
        if (file_exists($path) || is_link($path)) {
            throw new BackupFailure('Restore media target must be a new directory; existing files are never overwritten.');
        }
    }

    private function manifest(string $bundle, string $sha256): array
    {
        $this->files->canonical($bundle.'/manifest.json');
        if (! hash_equals($sha256, (string) hash_file('sha256', $bundle.'/manifest.json'))) {
            throw new BackupFailure('Manifest checksum does not match the accepted bundle.');
        }
        $manifest = json_decode(file_get_contents($bundle.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($manifest) || ($manifest['formatVersion'] ?? null) !== 1 || ! is_array($manifest['database'] ?? null)
            || ($manifest['database']['file'] ?? null) !== 'database.sql' || ! is_array($manifest['media'] ?? null) || ! array_is_list($manifest['media'])) {
            throw new BackupFailure('Unsupported backup manifest.');
        }
        $expected = ['manifest.json' => true, 'database.sql' => true];
        $versions = [];
        $uuid = '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}';
        foreach ($manifest['media'] as $entry) {
            if (! is_array($entry) || ! is_string($entry['file'] ?? null)
                || ! preg_match('~\Amedia/versions/('.$uuid.')/('.$uuid.')\.(png|jpg|webp)\z~', $entry['file'], $matches)
                || ($entry['assetId'] ?? null) !== $matches[1] || ($entry['versionId'] ?? null) !== $matches[2]
                || ! is_int($entry['versionNo'] ?? null) || $entry['versionNo'] < 1 || isset($expected[$entry['file']]) || isset($versions[$entry['versionId']])) {
                throw new BackupFailure('Manifest media paths and IDs must be canonical and unique.');
            }
            $expected[$entry['file']] = true;
            $versions[$entry['versionId']] = true;
        }
        if (array_key_exists('requiredMediaVersionIds', $manifest)) {
            $required = $manifest['requiredMediaVersionIds'];
            if (! is_array($required) || ! array_is_list($required)) {
                throw new BackupFailure('Required media coverage must be a list of unique manifest version IDs.');
            }
            $seen = [];
            foreach ($required as $id) {
                if (! is_string($id) || ! preg_match('/\A'.$uuid.'\z/', $id) || ! isset($versions[$id]) || isset($seen[$id])) {
                    throw new BackupFailure('Required media coverage must be a list of unique manifest version IDs.');
                }
                $seen[$id] = true;
            }
        }
        foreach (array_merge([$manifest['database']], $manifest['media']) as $entry) {
            $this->verifyFile($bundle, $entry);
        }
        $this->inventory($bundle, $bundle, $expected);
        if ($expected !== []) {
            throw new BackupFailure('Manifest inventory is incomplete.');
        }

        return $manifest;
    }

    private function verifyFile(string $root, array $entry): void
    {
        if (! is_int($entry['bytes'] ?? null) || $entry['bytes'] < 1 || ! is_string($entry['sha256'] ?? null) || ! preg_match('/\A[a-f0-9]{64}\z/', $entry['sha256'])) {
            throw new BackupFailure('Manifest file integrity metadata is invalid.');
        }
        $path = $this->files->canonical($root.'/'.$entry['file']);
        clearstatcache(true, $path);
        if (! $this->files->contains($root, $path) || ! is_file($path) || filesize($path) !== $entry['bytes']
            || ! hash_equals($entry['sha256'], (string) hash_file('sha256', $path))) {
            throw new BackupFailure('Bundle file size or checksum verification failed.');
        }
    }

    private function inventory(string $root, string $directory, array &$expected): void
    {
        foreach (array_diff(scandir($directory), ['.', '..']) as $name) {
            $path = $this->files->canonical($directory.'/'.$name);
            if (is_dir($path)) {
                $this->inventory($root, $path, $expected);
            } else {
                $relative = substr($path, strlen($root) + 1);
                if (! isset($expected[$relative])) {
                    throw new BackupFailure('Unlisted files are not accepted in a restore bundle.');
                }
                unset($expected[$relative]);
            }
        }
    }

    private function verifyRestoredMedia(Connection $database, array $media, ?array $required): int
    {
        if (! $database->getSchemaBuilder()->hasColumns('media_versions', ['id', 'media_asset_id', 'storage_key', 'version_no', 'bytes', 'sha256'])) {
            throw new BackupFailure('Restored SQL lacks the required media schema.');
        }

        return $this->coverage->verify($database->table('media_versions')->cursor(), $media, $required);
    }
}
