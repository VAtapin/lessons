<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Operations\BackupFailure;
use App\Application\Operations\RestoreDatabaseLock;
use App\Application\Operations\RestoreTarget;
use Illuminate\Database\Connection;
use Mockery;
use PDO;
use PDOStatement;
use RuntimeException;
use Tests\TestCase;

/** Synthetic PDO responses check guard/ownership failures; real concurrency is BackupRestoreTest. */
final class RestoreDatabaseLockTest extends TestCase
{
    public function test_unsafe_environment_or_account_never_attempts_lock_acquisition(): void
    {
        foreach ([['production', false], ['testing', true]] as [$environment, $root]) {
            $pdo = Mockery::mock(PDO::class);
            $pdo->shouldNotReceive('query');
            $database = $this->database($pdo, $root);
            try {
                (new RestoreDatabaseLock(new RestoreTarget))->run($database, $environment, fn () => $this->fail('Unsafe target entered.'));
                $this->fail('Unsafe restore guard was accepted.');
            } catch (BackupFailure) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_busy_database_lock_never_enters_or_releases_another_owners_lock(): void
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('query')->once()->with("SELECT GET_LOCK('".RestoreDatabaseLock::NAME."', 0)")->andReturn($this->scalar(0));
        $database = $this->database($pdo);
        $this->expectException(BackupFailure::class);
        $this->expectExceptionMessage('Another isolated restore');
        (new RestoreDatabaseLock(new RestoreTarget))->run($database, 'testing', fn () => $this->fail('Busy target entered.'));
    }

    public function test_locked_clone_refuses_reconnect_and_finally_releases_after_operation_error(): void
    {
        $pdo = $this->ownedPdo();
        $database = $this->database($pdo);
        $reconnected = false;
        $database->setReconnector(function () use (&$reconnected): void {
            $reconnected = true;
        });
        try {
            (new RestoreDatabaseLock(new RestoreTarget))->run($database, 'testing', function (Connection $pinned): void {
                $pinned->reconnect();
            });
            $this->fail('Locked clone reconnected.');
        } catch (BackupFailure $failure) {
            $this->assertStringContainsString('reconnect is forbidden', $failure->getMessage());
        }
        $this->assertFalse($reconnected);
        $this->assertSame($pdo, $database->getPdo());
        $database->reconnect();
        $this->assertTrue($reconnected, 'The original Laravel connection must retain its own reconnect behavior.');
    }

    public function test_success_keeps_the_same_pdo_and_ownership_through_callback_and_releases(): void
    {
        $pdo = $this->ownedPdo();
        $database = $this->database($pdo);
        $result = (new RestoreDatabaseLock(new RestoreTarget))->run($database, 'testing', function (Connection $pinned, callable $assertOwned) use ($pdo, $database): string {
            $this->assertNotSame($database, $pinned);
            $this->assertSame($pdo, $pinned->getPdo());
            $this->assertSame($pdo, $pinned->getReadPdo());
            $assertOwned();

            return 'verified isolated result';
        });
        $this->assertSame('verified isolated result', $result);
    }

    public function test_changed_lock_ownership_fails_before_acceptance_and_attempts_release(): void
    {
        $pdo = $this->ownedPdo(changed: true);
        $database = $this->database($pdo);
        $this->expectException(BackupFailure::class);
        $this->expectExceptionMessage('no longer owns');
        (new RestoreDatabaseLock(new RestoreTarget))->run($database, 'testing', fn (Connection $pinned, callable $assertOwned) => $assertOwned());
    }

    public function test_generic_operation_error_is_sanitized_and_lock_is_finally_released(): void
    {
        $database = $this->database($this->ownedPdo());
        try {
            (new RestoreDatabaseLock(new RestoreTarget))->run($database, 'testing', fn () => throw new RuntimeException('private SQL password'));
            $this->fail('Operation error was hidden.');
        } catch (BackupFailure $failure) {
            $this->assertStringNotContainsString('private', $failure->getMessage());
            $this->assertNull($failure->getPrevious());
        }
    }

    private function database(PDO $pdo, bool $root = false): Connection
    {
        $config = ['driver' => 'mysql', 'database' => 'lessons_restore_test', 'host' => '127.0.0.1'];
        $database = Mockery::mock(Connection::class.'[select,selectOne]', [$pdo, 'lessons_restore_test', '', $config]);
        $database->shouldReceive('selectOne')->with('SELECT DATABASE() AS database_name, VERSION() AS server_version')->andReturn((object) ['database_name' => 'lessons_restore_test', 'server_version' => '10.6.23-MariaDB']);
        $database->shouldReceive('select')->with('SHOW GRANTS FOR CURRENT_USER')->andReturn([(object) ['grant' => $root ? "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%'" : "GRANT ALL PRIVILEGES ON `lessons\\_restore\\_test`.* TO 'restore'@'%'"]]);

        return $database;
    }

    private function ownedPdo(bool $changed = false): PDO
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('query')->once()->with("SELECT GET_LOCK('".RestoreDatabaseLock::NAME."', 0)")->andReturn($this->scalar(1));
        $pdo->shouldReceive('query')->once()->with('SELECT CONNECTION_ID()')->andReturn($this->scalar(42));
        $ownership = Mockery::mock(PDOStatement::class);
        $ownership->shouldReceive('fetch')->with(PDO::FETCH_ASSOC)->andReturn(['connection_id' => 42, 'lock_owner' => 42], ['connection_id' => 42, 'lock_owner' => $changed ? 7 : 42]);
        $pdo->shouldReceive('query')->with("SELECT CONNECTION_ID() AS connection_id, IS_USED_LOCK('".RestoreDatabaseLock::NAME."') AS lock_owner")->andReturn($ownership);
        $pdo->shouldReceive('query')->once()->with("SELECT RELEASE_LOCK('".RestoreDatabaseLock::NAME."')")->andReturn($this->scalar($changed ? 0 : 1));

        return $pdo;
    }

    private function scalar(int $value): PDOStatement
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('fetchColumn')->once()->andReturn($value);

        return $statement;
    }
}
