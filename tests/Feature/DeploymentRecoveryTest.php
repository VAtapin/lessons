<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class DeploymentRecoveryTest extends TestCase
{
    public function test_worker_restart_occurs_after_cache_generation_before_up_while_deployment_lock_is_held(): void
    {
        $script = str_replace("\r\n", "\n", file_get_contents(base_path('scripts/deploy-plesk.sh')));
        $lock = strpos($script, 'flock --exclusive --nonblock 9');
        $down = strpos($script, 'php artisan down');
        $optimize = strpos($script, "php artisan optimize\n");
        $restart = strpos($script, 'php artisan queue:restart');
        $up = strpos($script, 'php artisan up');
        foreach ([$lock, $down, $optimize, $restart, $up] as $position) {
            $this->assertNotFalse($position);
        }
        $this->assertLessThan($down, $lock);
        $this->assertLessThan($optimize, $down);
        $this->assertLessThan($restart, $optimize);
        $this->assertLessThan($up, $restart);
        $this->assertStringNotContainsString('flock --unlock', substr($script, $lock, $up - $lock));
    }

    public function test_recovery_checks_before_up_preserves_original_status_and_keeps_schema_failures_down(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Bash deployment EXIT-trap behavior requires Linux.');
        }
        $bash = (new ExecutableFinder)->find('bash');
        $this->assertNotNull($bash);
        $directory = sys_get_temp_dir().'/lessons-deploy-recovery-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $php = $directory.'/php';
        file_put_contents($php, "#!/usr/bin/env bash\n".'echo "$2" >> "$OPERATIONS_TEST_LOG"'."\n".'if [[ "$2" == "lessons:check" ]]; then exit "$OPERATIONS_TEST_CHECK"; fi'."\n".'exit "$OPERATIONS_TEST_UP"'."\n");
        chmod($php, 0700);
        try {
            foreach ([
                ['false', 'false', '0', '0', ['lessons:check', 'up']],
                ['false', 'false', '1', '0', ['lessons:check']],
                ['false', 'false', '0', '1', ['lessons:check', 'up']],
                ['true', 'false', '0', '0', []],
                ['false', 'true', '0', '0', []],
            ] as [$schemaStarted, $initiallyDown, $check, $up, $calls]) {
                $log = $directory.'/calls';
                file_put_contents($log, '');
                // Execute the actual recovery module with fake CLI responses, never a production deployment.
                $process = new Process([$bash, '-c', 'set -euo pipefail; source "$1"; deployment_started=true; deployment_completed=false; schema_changes_started="$2"; initially_down="$3"; exit 7',
                    'recovery-test', base_path('scripts/deployment-recovery.sh'), $schemaStarted, $initiallyDown], env: [
                        'PATH' => $directory.':'.getenv('PATH'), 'OPERATIONS_TEST_LOG' => $log, 'OPERATIONS_TEST_CHECK' => $check, 'OPERATIONS_TEST_UP' => $up,
                    ]);
                $process->setTimeout(5);
                $process->disableOutput();
                $this->assertSame(7, $process->run(), 'Recovery must preserve the failing deployment exit code, even when up also fails.');
                $this->assertSame($calls, array_values(array_filter(explode("\n", trim(file_get_contents($log))))));
            }
            $script = file_get_contents(base_path('scripts/deploy-plesk.sh'));
            $this->assertLessThan(strpos($script, 'php artisan down'), strpos($script, 'flock --exclusive --nonblock 9'));
            $this->assertStringContainsString('source scripts/deployment-recovery.sh', $script);
            $this->assertStringContainsString('schema_changes_started=true', $script);
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
