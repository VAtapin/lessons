<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\RestoreTarget;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

final class RestoreGuardTest extends TestCase
{
    public function test_restore_requires_explicit_cli_integrity_options(): void
    {
        $this->assertSame(1, Artisan::call('lessons:restore-test', ['bundle' => '/missing-bundle']));
        $this->assertStringContainsString('Explicit media destination', Artisan::output());
    }

    public function test_unsafe_effective_connection_is_rejected_before_network_access(): void
    {
        foreach ([['database' => 'lessons'], ['host' => 'production.example'], ['host' => 'localhost'], ['url' => 'mysql://private'],
            ['unix_socket' => '/private/mysql.sock'], ['prefix' => 'prefix_'], ['read' => []], ['write' => []], ['driver' => 'sqlite']] as $override) {
            $database = Mockery::mock(Connection::class);
            $database->shouldReceive('getConfig')->once()->andReturn(array_replace($this->config(), $override));
            $database->shouldNotReceive('selectOne');
            try {
                (new RestoreTarget)->assertSafe($database, 'testing');
                $this->fail('Unsafe connection was accepted.');
            } catch (BackupFailure $failure) {
                $this->assertStringContainsString('Restore requires', $failure->getMessage());
            }
        }
        $database = Mockery::mock(Connection::class);
        $database->shouldReceive('getConfig')->once()->andReturn($this->config());
        $database->shouldNotReceive('selectOne');
        $this->expectException(BackupFailure::class);
        (new RestoreTarget)->assertSafe($database, 'production');
    }

    public function test_root_global_roles_wildcard_grants_and_grant_option_are_refused(): void
    {
        foreach (["GRANT ALL PRIVILEGES ON *.* TO 'root'@'%'", "GRANT SELECT ON `lessons_test`.* TO 'restore'@'%'",
            "GRANT ALL PRIVILEGES ON `lessons_restore_test`.* TO 'restore'@'%'", "GRANT `role` TO 'restore'@'%'",
            "GRANT USAGE ON *.* TO 'restore'@'%' WITH GRANT OPTION"] as $grant) {
            $database = $this->database([$grant]);
            try {
                (new RestoreTarget)->assertSafe($database, 'testing');
                $this->fail('Non-isolated credentials were accepted.');
            } catch (BackupFailure) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_exact_target_grant_and_empty_schema_are_accepted(): void
    {
        $database = $this->database(["GRANT USAGE ON *.* TO 'restore'@'%'", "GRANT ALL PRIVILEGES ON `lessons\\_restore\\_test`.* TO 'restore'@'%'"]);
        $database->shouldReceive('selectOne')->withArgs(fn ($query) => str_contains($query, 'information_schema.'))->times(3)->andReturn((object) ['objects' => 0]);
        (new RestoreTarget)->assertSafe($database, 'testing');
        $this->addToAssertionCount(1);
    }

    public function test_nonempty_schema_cannot_be_overwritten(): void
    {
        $database = $this->database(["GRANT ALL PRIVILEGES ON `lessons\\_restore\\_test`.* TO 'restore'@'%'"]);
        $database->shouldReceive('selectOne')->withArgs(fn ($query) => str_contains($query, 'information_schema.TABLES'))->once()->andReturn((object) ['objects' => 1]);
        $this->expectException(BackupFailure::class);
        $this->expectExceptionMessage('must be empty');
        (new RestoreTarget)->assertSafe($database, 'testing');
    }

    private function database(array $grants): Connection
    {
        $database = Mockery::mock(Connection::class);
        $database->shouldReceive('getConfig')->andReturn($this->config());
        $database->shouldReceive('selectOne')->with('SELECT DATABASE() AS database_name, VERSION() AS server_version')->andReturn((object) ['database_name' => 'lessons_restore_test', 'server_version' => '10.6.23-MariaDB']);
        $database->shouldReceive('select')->with('SHOW GRANTS FOR CURRENT_USER')->andReturn(array_map(fn ($grant) => (object) ['grant' => $grant], $grants));

        return $database;
    }

    private function config(): array
    {
        return ['driver' => 'mysql', 'database' => 'lessons_restore_test', 'host' => '127.0.0.1', 'url' => null, 'prefix' => '', 'unix_socket' => ''];
    }
}
