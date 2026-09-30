<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Symfony\Component\Process\ExecutableFinder;

final readonly class DatabaseBackup
{
    public function __construct(private DatabaseDumpProcess $process, private ExecutableFinder $executables) {}

    public function create(array $connection, string $directory, string $applicationRoot, int $timeout): string
    {
        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new BackupFailure('Only MySQL/MariaDB connections support this backup command.');
        }
        $database = $connection['database'] ?? null;
        if (! is_string($database) || ! preg_match('/\A[A-Za-z0-9_][A-Za-z0-9_.$-]*\z/', $database)) {
            throw new BackupFailure('Configured database name is invalid for a private dump.');
        }
        $directory = $this->safeDirectory($directory, $applicationRoot);
        $binary = $this->executables->find('mariadb-dump', extraDirs: ['/usr/bin', '/usr/local/bin'])
            ?? $this->executables->find('mysqldump', extraDirs: ['/usr/bin', '/usr/local/bin']);
        if ($binary === null) {
            throw new BackupFailure('mariadb-dump/mysqldump is unavailable; no backup was created.');
        }
        $settings = $this->clientSettings($connection);
        $previousMask = umask(0077);
        $credentials = null;
        $partial = null;
        try {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                throw new BackupFailure('Cannot create the private backup directory.');
            }
            $this->assertPermissions($directory, 0700);
            $identifier = gmdate('Ymd-His').'-'.bin2hex(random_bytes(8));
            $credentials = $directory.'/credentials-'.$identifier.'.cnf';
            $partial = $directory.'/lessons-'.$identifier.'.sql.partial';
            $final = $directory.'/lessons-'.$identifier.'.sql';
            $this->privateFile($credentials, $settings);
            $this->privateFile($partial, '');
            $this->process->run([$binary, '--defaults-file='.$credentials,
                '--single-transaction', '--quick', '--routines', '--events', '--triggers', '--hex-blob',
                '--default-character-set=utf8mb4', '--result-file='.$partial, '--databases', $database], $timeout);
            clearstatcache(true, $partial);
            if (! is_file($partial) || is_link($partial) || filesize($partial) === 0) {
                throw new BackupFailure('Dump did not produce a nonempty regular file.');
            }
            $this->assertPermissions($partial, 0600);
            if (! rename($partial, $final)) {
                throw new BackupFailure('Cannot finalize the private backup file.');
            }
            $partial = null;

            return $final;
        } finally {
            $cleaned = true;
            foreach ([$credentials, $partial] as $workingFile) {
                if ($workingFile !== null && (file_exists($workingFile) || is_link($workingFile))) {
                    $cleaned = @unlink($workingFile) && $cleaned;
                }
            }
            umask($previousMask);
            if (! $cleaned) {
                throw new BackupFailure('Cannot remove private backup working files; keep maintenance enabled and inspect the private backup directory.');
            }
        }
    }

    public function safeDirectory(string $directory, string $applicationRoot): string
    {
        $directory = str_replace('\\', '/', $directory);
        if (! preg_match('~\A(?:[A-Za-z]:/|/)~', $directory) || str_contains($directory, "\0")
            || preg_match('~(?:\A|/)\.{1,2}(?:/|\z)~', $directory)) {
            throw new BackupFailure('Backup directory must be an absolute path without traversal.');
        }
        $directory = rtrim($directory, '/');
        $cursor = $directory;
        $suffix = '';
        while (! file_exists($cursor)) {
            if (is_link($cursor)) {
                throw new BackupFailure('Backup directory must not contain symbolic links.');
            }
            $suffix = '/'.basename($cursor).$suffix;
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                throw new BackupFailure('Cannot resolve the backup directory.');
            }
            $cursor = $parent;
        }
        $ancestor = $cursor;
        while (true) {
            if (is_link($ancestor)) {
                throw new BackupFailure('Backup directory must not contain symbolic links.');
            }
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }
        $resolved = str_replace('\\', '/', (string) realpath($cursor)).$suffix;
        $root = rtrim(str_replace('\\', '/', (string) realpath($applicationRoot)), '/');
        $comparison = PHP_OS_FAMILY === 'Windows' ? strtolower($resolved) : $resolved;
        $root = PHP_OS_FAMILY === 'Windows' ? strtolower($root) : $root;
        if ($root === '' || $comparison === $root || str_starts_with($comparison, $root.'/')) {
            throw new BackupFailure('Backup directory must be outside the application/webroot.');
        }

        return $resolved;
    }

    private function clientSettings(array $connection): string
    {
        $fields = ['user' => $connection['username'] ?? '', 'password' => $connection['password'] ?? '',
            'host' => $connection['host'] ?? 'localhost', 'port' => (string) ($connection['port'] ?? '3306')];
        if (($connection['unix_socket'] ?? '') !== '') {
            $fields['socket'] = $connection['unix_socket'];
        }
        $settings = "[client]\n";
        foreach ($fields as $key => $value) {
            if (! is_string($value) || str_contains($value, "\0")) {
                throw new BackupFailure('Configured dump connection contains unsupported values.');
            }
            $escaped = str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $value);
            $settings .= $key.'="'.$escaped.'"'."\n";
        }

        return $settings;
    }

    private function privateFile(string $path, string $contents): void
    {
        $file = fopen($path, 'x');
        if ($file === false) {
            throw new BackupFailure('Cannot create a private backup working file.');
        }
        try {
            if (! chmod($path, 0600) || fwrite($file, $contents) !== strlen($contents)) {
                throw new BackupFailure('Cannot write the private backup working file.');
            }
        } finally {
            fclose($file);
        }
        $this->assertPermissions($path, 0600);
    }

    private function assertPermissions(string $path, int $mode): void
    {
        clearstatcache(true, $path);
        // Windows lacks POSIX modes; Linux CI and production enforce exact access.
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 0777) !== $mode) {
            throw new BackupFailure('Private backup permissions must be 0700 for directories and 0600 for files.');
        }
    }
}
