<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\OperationsLock;
use App\Application\Operations\PrivateBackupFiles;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class OperationsLockTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = str_replace('\\', '/', sys_get_temp_dir()).'/lessons-operations-lock-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $path) {
            if (is_link($path) || is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                foreach (glob($path.'/*') as $file) {
                    unlink($file);
                }
                rmdir($path);
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_second_handle_cannot_enter_and_exception_releases_the_persistent_lock(): void
    {
        $first = $this->lock();
        $second = $this->lock();
        try {
            $first->run(function () use ($second): void {
                $this->assertFalse($second->run(fn () => $this->fail('Concurrent mutation entered a locked operation.')));
                throw new RuntimeException('synthetic operation failure');
            });
            $this->fail('The operation error must not be hidden.');
        } catch (RuntimeException $failure) {
            $this->assertSame('synthetic operation failure', $failure->getMessage());
        }
        $this->assertTrue($second->run(fn () => $this->addToAssertionCount(1)));
        $this->assertFileExists($first->path(), 'The lock inode must never be deleted between operations.');
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0700, fileperms(dirname($first->path())) & 0777);
            $this->assertSame(0600, fileperms($first->path()) & 0777);
        }
    }

    public function test_relative_traversal_and_nonprivate_existing_directories_fail_closed(): void
    {
        foreach (['relative/operations', $this->directory.'/../operations'] as $path) {
            try {
                (new OperationsLock(new PrivateBackupFiles, $path))->run(fn () => $this->fail('Unsafe lock entered.'));
                $this->fail('Unsafe lock path was accepted.');
            } catch (BackupFailure) {
                $this->addToAssertionCount(1);
            }
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            mkdir($this->directory.'/unsafe', 0755);
            chmod($this->directory.'/unsafe', 0755);
            $this->expectException(BackupFailure::class);
            (new OperationsLock(new PrivateBackupFiles, $this->directory.'/unsafe'))->path();
        }
    }

    public function test_symlinked_lock_is_rejected_without_changing_its_target(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink creation and private POSIX modes require Linux.');
        }
        mkdir($this->directory.'/operations', 0700);
        file_put_contents($this->directory.'/target', 'untouched synthetic bytes');
        symlink($this->directory.'/target', $this->directory.'/operations/lock');
        try {
            $this->lock()->run(fn () => $this->fail('Symlinked lock entered.'));
            $this->fail('Symlinked lock was accepted.');
        } catch (BackupFailure) {
            $this->assertSame('untouched synthetic bytes', file_get_contents($this->directory.'/target'));
        }
    }

    public function test_php_and_the_deployment_flock_utility_share_the_same_exclusion(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('PHP/util-linux flock interoperability requires POSIX.');
        }
        $flock = (new ExecutableFinder)->find('flock');
        $this->assertNotNull($flock, 'Linux CI/deployment must provide flock.');
        $lock = $this->lock();
        $path = $lock->path();
        $process = new Process([$flock, '--exclusive', '--nonblock', $path, 'true']);
        $process->setTimeout(5);
        $process->disableOutput();
        $this->assertTrue($lock->run(function () use ($process): void {
            $this->assertSame(1, $process->run(), 'Deployment must refuse mutation while the PHP backup owns the lock.');
        }));
        $this->assertSame(0, $process->run(), 'Deployment must enter after PHP released the lock.');
    }

    private function lock(): OperationsLock
    {
        return new OperationsLock(new PrivateBackupFiles, $this->directory.'/operations');
    }
}
