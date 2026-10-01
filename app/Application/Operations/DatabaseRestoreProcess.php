<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Symfony\Component\Process\Process;

class DatabaseRestoreProcess
{
    public function run(array $arguments, string $sql, int $timeout): void
    {
        $input = @fopen($sql, 'rb');
        if ($input === false) {
            throw new BackupFailure('Cannot read the verified SQL snapshot.');
        }
        try {
            $inherited = array_merge(getenv(), $_ENV, $_SERVER);
            $environment = array_fill_keys(array_keys($inherited), false);
            foreach (['PATH', 'SystemRoot', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP', 'LANG', 'LC_ALL'] as $name) {
                if (isset($inherited[$name]) && is_string($inherited[$name])) {
                    $environment[$name] = $inherited[$name];
                }
            }
            $process = new Process($arguments, env: $environment, input: $input);
            $process->setTimeout($timeout);
            $process->disableOutput();
            if ($process->run() !== 0) {
                throw new BackupFailure('Isolated SQL import failed; keep the test target isolated and inspect privately.');
            }
        } finally {
            fclose($input);
        }
    }
}
