<?php

declare(strict_types=1);

namespace App\Application\Operations;

/** Persistent shared inode: PHP flock and deployment's util-linux flock use the same private file. */
final readonly class OperationsLock
{
    public function __construct(private PrivateBackupFiles $files, private ?string $directory = null) {}

    public function path(): string
    {
        // cache:clear removes cache/data, never this private sibling directory.
        $directory = str_replace('\\', '/', $this->directory ?? storage_path('framework/cache/operations'));
        $this->files->validatePath($directory);
        $this->files->directory($directory);
        $directory = $this->files->canonical($directory);
        $path = $directory.'/lock';
        if (is_link($path)) {
            throw new BackupFailure('Operations lock must not contain symbolic links.');
        }
        if (! file_exists($path)) {
            $mask = umask(0077);
            try {
                $stream = $this->files->open($path);
                fclose($stream);
            } finally {
                umask($mask);
            }
        }
        if (! is_file($path) || $this->files->canonical($path) !== $path) {
            throw new BackupFailure('Operations lock must be a canonical regular file.');
        }
        $this->files->permissions($path, 0600);

        return $path;
    }

    /** False means another operation owns the lock; no callback or successful execution occurred. */
    public function run(callable $operation): bool
    {
        $path = $this->path();
        $stream = @fopen($path, 'r+b');
        if ($stream === false) {
            throw new BackupFailure('Cannot open the private operations lock.');
        }
        $locked = false;
        try {
            $opened = fstat($stream);
            $actual = stat($path);
            if ($opened === false || $actual === false || ($opened['mode'] & 0170000) !== 0100000
                || $opened['ino'] !== $actual['ino'] || $opened['dev'] !== $actual['dev']) {
                throw new BackupFailure('Operations lock file changed while it was opened.');
            }
            if (! flock($stream, LOCK_EX | LOCK_NB)) {
                return false;
            }
            $locked = true;
            $operation();

            return true;
        } finally {
            if ($locked) {
                flock($stream, LOCK_UN);
            }
            fclose($stream);
        }
    }
}
