<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Illuminate\Database\Connection;
use PDO;
use Throwable;

/** Server-wide exclusion only for the explicitly guarded isolated restore schema. */
final readonly class RestoreDatabaseLock
{
    public const NAME = 'lessons:restore-test:lessons_restore_test';

    public function __construct(private RestoreTarget $guard) {}

    public function run(Connection $database, string $environment, callable $operation): mixed
    {
        // Never acquire anything on an environment/server/account that the restore guard refuses.
        $this->guard->assertSafe($database, $environment, empty: false);
        $pdo = $database->getPdo();
        $owned = false;
        $failed = false;
        try {
            $acquired = $pdo->query("SELECT GET_LOCK('".self::NAME."', 0)")->fetchColumn();
            if ((int) $acquired !== 1) {
                throw new BackupFailure('Another isolated restore owns the target database lock; no import was started.');
            }
            $owned = true;
            $connectionId = (string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            // Only this clone is used for final checks/coverage. Reconnect would silently lose the named lock.
            $pinned = clone $database;
            $pinned->setPdo($pdo)->setReadPdo($pdo)->setReconnector(function (): never {
                throw new BackupFailure('Isolated restore lost its locked database connection; reconnect is forbidden.');
            });
            $assertOwned = function () use ($pdo, $pinned, $connectionId): void {
                if ($pinned->getPdo() !== $pdo || $pinned->getReadPdo() !== $pdo) {
                    throw new BackupFailure('Isolated restore changed its locked database connection.');
                }
                $ownership = $pdo->query("SELECT CONNECTION_ID() AS connection_id, IS_USED_LOCK('".self::NAME."') AS lock_owner")->fetch(PDO::FETCH_ASSOC);
                if (! is_array($ownership) || (string) $ownership['connection_id'] !== $connectionId || (string) $ownership['lock_owner'] !== $connectionId) {
                    throw new BackupFailure('Isolated restore no longer owns its target database lock.');
                }
            };
            $assertOwned();
            $result = $operation($pinned, $assertOwned);
            $assertOwned();

            return $result;
        } catch (BackupFailure $failure) {
            $failed = true;
            throw $failure;
        } catch (Throwable) {
            $failed = true;
            throw new BackupFailure('Cannot retain the isolated restore database lock; keep the test target isolated.');
        } finally {
            if ($owned) {
                try {
                    if ((int) $pdo->query("SELECT RELEASE_LOCK('".self::NAME."')")->fetchColumn() !== 1) {
                        throw new BackupFailure('Cannot release the isolated restore database lock.');
                    }
                } catch (Throwable) {
                    if (! $failed) {
                        throw new BackupFailure('Cannot release the isolated restore database lock; keep the test target isolated.');
                    }
                }
            }
        }
    }
}
