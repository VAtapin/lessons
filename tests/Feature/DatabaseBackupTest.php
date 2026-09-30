<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\DatabaseBackup;
use App\Application\Operations\DatabaseDumpProcess;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class DatabaseBackupTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = sys_get_temp_dir().'/lessons-backup-test-'.bin2hex(random_bytes(8));
        mkdir($this->temporary, 0700);
    }

    protected function tearDown(): void
    {
        $this->removeTemporary($this->temporary);
        parent::tearDown();
    }

    public function test_dump_uses_private_credentials_file_and_safe_arguments_and_removes_credentials_on_success(): void
    {
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $password = "test-only-quote\"slash\\\nnext;$(not-a-command)";
        $process->shouldReceive('run')->once()->withArgs(function (array $arguments, int $timeout) use ($password): bool {
            $this->assertSame('/trusted/mariadb-dump', $arguments[0]);
            $this->assertSame(30, $timeout);
            $this->assertStringStartsWith('--defaults-file=', $arguments[1]);
            $credentials = substr($arguments[1], strlen('--defaults-file='));
            $this->assertStringContainsString('password="test-only-quote\\"slash\\\\\\nnext;$(not-a-command)"', file_get_contents($credentials));
            $this->assertStringNotContainsString($password, implode(' ', $arguments));
            foreach (['--single-transaction', '--quick', '--routines', '--events', '--triggers', '--databases'] as $option) {
                $this->assertContains($option, $arguments);
            }
            $this->assertSame('lessons_test', $arguments[array_key_last($arguments)]);
            $partial = $this->resultFile($arguments);
            $this->assertMode($credentials, 0600);
            $this->assertMode($partial, 0600);
            $this->assertMode(dirname($partial), 0700);
            file_put_contents($partial, "-- test fixture only\nCREATE TABLE fixture (id int);\n");

            return true;
        });
        $connection = $this->connection();
        $connection['password'] = $password;
        $file = $this->backup($process)->create($connection, $this->temporary.'/private/backups', base_path(), 30);
        $this->assertFileExists($file);
        $this->assertStringEndsWith('.sql', $file);
        $this->assertMode($file, 0600);
        $this->assertSame([$file], glob(dirname($file).'/*'));
        $this->assertMode($this->temporary.'/private', 0700);
    }

    public function test_failed_or_timed_out_dump_removes_partial_sql_and_credentials(): void
    {
        foreach ([new RuntimeException('test-only private diagnostic'), new ProcessTimedOutException(new Process([PHP_BINARY]), ProcessTimedOutException::TYPE_GENERAL)] as $failure) {
            $process = Mockery::mock(DatabaseDumpProcess::class);
            $process->shouldReceive('run')->once()->andReturnUsing(function (array $arguments) use ($failure): void {
                file_put_contents($this->resultFile($arguments), 'partial fixture');
                throw $failure;
            });
            try {
                $this->backup($process)->create($this->connection(), $this->temporary.'/backups', base_path(), 30);
                $this->fail('A failed dump must not succeed.');
            } catch (RuntimeException $caught) {
                $this->assertSame($failure, $caught);
            }
            $this->assertSame([], glob($this->temporary.'/backups/*'));
        }
    }

    public function test_successful_process_without_dump_content_is_rejected_and_cleaned_up(): void
    {
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldReceive('run')->once()->andReturnNull();
        try {
            $this->backup($process)->create($this->connection(), $this->temporary.'/backups', base_path(), 30);
            $this->fail('An empty dump must not succeed.');
        } catch (BackupFailure $failure) {
            $this->assertStringContainsString('nonempty regular file', $failure->getMessage());
        }
        $this->assertSame([], glob($this->temporary.'/backups/*'));
    }

    public function test_missing_dump_binary_has_a_clear_failure_without_creating_working_files(): void
    {
        $finder = Mockery::mock(ExecutableFinder::class);
        $finder->shouldReceive('find')->twice()->andReturnNull();
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldNotReceive('run');
        $backup = new DatabaseBackup($process, $finder);
        try {
            $backup->create($this->connection(), $this->temporary.'/backups', base_path(), 30);
            $this->fail('Missing binaries must fail.');
        } catch (BackupFailure $failure) {
            $this->assertStringContainsString('mariadb-dump/mysqldump is unavailable', $failure->getMessage());
        }
        $this->assertDirectoryDoesNotExist($this->temporary.'/backups');
    }

    public function test_application_paths_relative_paths_and_traversal_are_rejected_before_dumping(): void
    {
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldNotReceive('run');
        foreach ([base_path('public/backups'), base_path(), 'relative/backups', $this->temporary.'/../backups'] as $directory) {
            try {
                $this->backup($process)->create($this->connection(), $directory, base_path(), 30);
                $this->fail('Unsafe backup directory must fail.');
            } catch (BackupFailure $failure) {
                $this->assertStringContainsString('Backup directory', $failure->getMessage());
            }
        }
    }

    public function test_symlink_path_into_the_application_is_rejected(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink creation needs privileges on Windows; Linux CI covers this path.');
        }
        symlink(base_path(), $this->temporary.'/linked-app');
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldNotReceive('run');
        $this->expectException(BackupFailure::class);
        $this->expectExceptionMessage('symbolic links');
        $this->backup($process)->create($this->connection(), $this->temporary.'/linked-app/backups', base_path(), 30);
    }

    public function test_existing_world_readable_backup_directory_is_rejected(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('POSIX access bits are enforced in Linux CI and production.');
        }
        mkdir($this->temporary.'/backups', 0755);
        chmod($this->temporary.'/backups', 0755);
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldNotReceive('run');
        $this->expectException(BackupFailure::class);
        $this->expectExceptionMessage('permissions');
        $this->backup($process)->create($this->connection(), $this->temporary.'/backups', base_path(), 30);
    }

    public function test_command_does_not_output_private_process_errors_and_rejects_invalid_timeouts(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql' => $this->connection()]);
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldReceive('run')->once()->andThrow(new RuntimeException('private-password-and-sql-must-not-appear'));
        $this->app->instance(DatabaseBackup::class, $this->backup($process));
        $this->assertSame(1, Artisan::call('lessons:database-backup', ['--directory' => $this->temporary.'/backups']));
        $output = Artisan::output();
        $this->assertStringContainsString('Database backup failed.', $output);
        $this->assertStringNotContainsString('private-password', $output);
        $this->assertSame([], glob($this->temporary.'/backups/*'));
        foreach (['0', '3601', 'not-an-integer'] as $timeout) {
            $this->assertSame(1, Artisan::call('lessons:database-backup', ['--timeout' => $timeout]));
            $this->assertStringContainsString('timeout must be an integer', Artisan::output());
        }
    }

    public function test_unsupported_database_and_option_like_or_invalid_schema_names_are_rejected(): void
    {
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldNotReceive('run');
        $sqlite = $this->connection();
        $sqlite['driver'] = 'sqlite';
        $connections = [$sqlite];
        foreach (['', '--all-databases', 'schema;DROP DATABASE other'] as $database) {
            $connection = $this->connection();
            $connection['database'] = $database;
            $connections[] = $connection;
        }
        foreach ($connections as $connection) {
            try {
                $this->backup($process)->create($connection, $this->temporary.'/backups', base_path(), 30);
                $this->fail('Unsupported or unsafe database configuration must fail.');
            } catch (BackupFailure $failure) {
                $this->assertSame($connection['driver'] === 'sqlite'
                    ? 'Only MySQL/MariaDB connections support this backup command.'
                    : 'Configured database name is invalid for a private dump.', $failure->getMessage());
            }
        }
        $this->assertDirectoryDoesNotExist($this->temporary.'/backups');
    }

    public function test_cli_rejects_a_backup_directory_inside_the_application(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql' => $this->connection()]);
        $process = Mockery::mock(DatabaseDumpProcess::class);
        $process->shouldNotReceive('run');
        $this->app->instance(DatabaseBackup::class, $this->backup($process));
        $this->assertSame(1, Artisan::call('lessons:database-backup', ['--directory' => base_path('public/backups')]));
        $this->assertStringContainsString('outside the application/webroot', Artisan::output());
        $this->assertDirectoryDoesNotExist(base_path('public/backups'));
    }

    public function test_real_process_receives_no_laravel_or_mysql_password_environment(): void
    {
        $previous = getenv('MYSQL_PWD');
        $previousPassword = $_ENV['DB_PASSWORD'] ?? null;
        $previousUrl = $_SERVER['DB_URL'] ?? null;
        putenv('MYSQL_PWD=test-only-password');
        $_ENV['DB_PASSWORD'] = 'test-only-password';
        $_SERVER['DB_URL'] = 'mysql://test-only-password';
        try {
            (new DatabaseDumpProcess)->run([PHP_BINARY, '-r', 'exit(getenv("MYSQL_PWD") === false && getenv("DB_PASSWORD") === false && getenv("DB_URL") === false ? 0 : 1);'], 10);
            $this->assertTrue(true);
        } finally {
            $previous === false ? putenv('MYSQL_PWD') : putenv('MYSQL_PWD='.$previous);
            if ($previousPassword === null) {
                unset($_ENV['DB_PASSWORD']);
            } else {
                $_ENV['DB_PASSWORD'] = $previousPassword;
            }
            if ($previousUrl === null) {
                unset($_SERVER['DB_URL']);
            } else {
                $_SERVER['DB_URL'] = $previousUrl;
            }
        }
    }

    public function test_real_process_treats_shell_metacharacters_as_a_single_literal_argument(): void
    {
        $literal = '$(not-a-command); & test-only literal';
        (new DatabaseDumpProcess)->run([PHP_BINARY, '-r', 'exit(count($argv) === 2 && $argv[1] === "$(not-a-command); & test-only literal" ? 0 : 1);', $literal], 10);
        $this->assertTrue(true);
    }

    public function test_real_process_failure_does_not_expose_its_private_diagnostics(): void
    {
        try {
            (new DatabaseDumpProcess)->run([PHP_BINARY, '-r', 'fwrite(STDERR, "test-only-private-diagnostic"); exit(1);'], 10);
            $this->fail('A nonzero process exit must fail the backup.');
        } catch (BackupFailure $failure) {
            $this->assertStringContainsString('Dump process failed', $failure->getMessage());
            $this->assertStringNotContainsString('test-only-private-diagnostic', $failure->getMessage());
        }
    }

    private function backup(DatabaseDumpProcess $process): DatabaseBackup
    {
        $finder = Mockery::mock(ExecutableFinder::class);
        $finder->shouldReceive('find')->andReturn('/trusted/mariadb-dump');

        return new DatabaseBackup($process, $finder);
    }

    private function connection(): array
    {
        return ['driver' => 'mysql', 'database' => 'lessons_test', 'host' => 'localhost', 'port' => 3306,
            'username' => 'test_only_user', 'password' => 'test-only-password', 'unix_socket' => ''];
    }

    private function resultFile(array $arguments): string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--result-file=')) {
                return substr($argument, strlen('--result-file='));
            }
        }
        $this->fail('Missing result-file argument.');
    }

    private function assertMode(string $path, int $mode): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            clearstatcache(true, $path);
            $this->assertSame($mode, fileperms($path) & 0777);
        }
    }

    private function removeTemporary(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->removeTemporary($path.'/'.$entry);
                }
            }
            rmdir($path);
        }
    }
}
