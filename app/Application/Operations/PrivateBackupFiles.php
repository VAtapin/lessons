<?php

declare(strict_types=1);

namespace App\Application\Operations;

final class PrivateBackupFiles
{
    public function directory(string $path): void
    {
        if ((! is_dir($path) && ! @mkdir($path, 0700, true)) || is_link($path)) {
            throw new BackupFailure('Cannot create a private backup directory.');
        }
        $this->permissions($path, 0700);
    }

    public function open(string $path)
    {
        $stream = @fopen($path, 'xb');
        if ($stream === false) {
            throw new BackupFailure('Cannot create a private backup file.');
        }
        if (! @chmod($path, 0600)) {
            fclose($stream);
            throw new BackupFailure('Cannot set private backup file permissions.');
        }
        $this->permissions($path, 0600);

        return $stream;
    }

    public function permissions(string $path, int $mode): void
    {
        clearstatcache(true, $path);
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 0777) !== $mode) {
            throw new BackupFailure('Private backup permissions must be 0700 for directories and 0600 for files.');
        }
    }

    public function validatePath(string $path): void
    {
        $cursor = str_replace('\\', '/', $path);
        if (! preg_match('~\A(?:[A-Za-z]:/|/)~', $cursor) || str_contains($cursor, "\0")
            || preg_match('~(?:\A|/)\.{1,2}(?:/|\z)~', $cursor)) {
            throw new BackupFailure('Media storage must use an absolute canonical path.');
        }
        while (true) {
            if (is_link($cursor)) {
                throw new BackupFailure('Media storage must not contain symbolic links.');
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                break;
            }
            $cursor = $parent;
        }
    }

    public function canonical(string $path): string
    {
        $this->validatePath($path);
        $resolved = realpath($path);
        if ($resolved === false) {
            throw new BackupFailure('A required media file or storage directory is missing.');
        }

        return rtrim(str_replace('\\', '/', $resolved), '/');
    }

    public function contains(string $directory, string $path): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $directory = strtolower($directory);
            $path = strtolower($path);
        }

        return str_starts_with($path, rtrim($directory, '/').'/');
    }

    public function cleanup(string $partial, string $parent): void
    {
        // Only this generated direct child may ever be removed recursively.
        if (! preg_match('/\Alessons-[0-9]{8}-[0-9]{6}-[a-f0-9]{16}\.partial\z/', basename($partial))
            || ! $this->contains($parent, $partial) || dirname($partial) !== $parent
            || is_link($partial) || $this->canonical($partial) !== $partial) {
            throw new BackupFailure('Cannot safely clean the partial backup; keep maintenance enabled and inspect privately.');
        }
        $this->remove($partial);
    }

    private function remove(string $path): void
    {
        // Never traverse a symbolic link, including one introduced during failure.
        if (is_link($path) || ! is_dir($path)) {
            $removed = @unlink($path);
        } else {
            $entries = @scandir($path);
            if ($entries === false) {
                throw new BackupFailure('Cannot clean the partial backup; inspect private permissions.');
            }
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path.'/'.$entry);
                }
            }
            $removed = @rmdir($path);
        }
        if (! $removed) {
            throw new BackupFailure('Cannot clean the partial backup; keep maintenance enabled and inspect privately.');
        }
    }
}
