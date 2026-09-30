<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Symfony\Component\Process\Process;

class DatabaseDumpProcess
{
    public function run(array $arguments, int $timeout): void
    {
        // Symfony also inherits $_ENV/$_SERVER, populated by Laravel's .env loader.
        // Give the dump a minimal environment rather than forwarding DB secrets.
        $inherited = array_merge(getenv(), $_ENV, $_SERVER);
        $environment = array_fill_keys(array_keys($inherited), false);
        foreach (['PATH', 'SystemRoot', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP', 'LANG', 'LC_ALL'] as $name) {
            if (isset($inherited[$name]) && is_string($inherited[$name])) {
                $environment[$name] = $inherited[$name];
            }
        }
        $process = new Process($arguments, env: $environment);
        $process->setTimeout($timeout);
        // SQL goes directly to the private result file; diagnostics may contain data.
        $process->disableOutput();
        if ($process->run() !== 0) {
            throw new BackupFailure('Dump process failed; inspect database access and disk space privately.');
        }
    }
}
