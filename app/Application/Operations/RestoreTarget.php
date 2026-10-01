<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Illuminate\Database\Connection;

final class RestoreTarget
{
    public function assertSafe(Connection $database, string $environment, bool $empty = true): void
    {
        $config = $database->getConfig();
        if (! in_array($environment, ['testing', 'local'], true)
            || ! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)
            || ($config['database'] ?? null) !== 'lessons_restore_test'
            || ! in_array($config['host'] ?? null, ['127.0.0.1', '::1'], true)
            || ($config['unix_socket'] ?? '') !== '' || ($config['prefix'] ?? '') !== ''
            || ! empty($config['url']) || isset($config['read']) || isset($config['write'])) {
            throw new BackupFailure('Restore requires local/testing, a loopback MariaDB lessons_restore_test database, without URL, socket, prefix or replicas.');
        }
        $actual = $database->selectOne('SELECT DATABASE() AS database_name, VERSION() AS server_version');
        if ($actual->database_name !== 'lessons_restore_test' || ! preg_match('/\A10\.6\..*MariaDB/', $actual->server_version)) {
            throw new BackupFailure('The actual restore target must be MariaDB 10.6 lessons_restore_test.');
        }
        // The importer must have no authority over the source or any other schema.
        // A loopback root account is deliberately refused, even on a test machine.
        $targetGrant = false;
        foreach ($database->select('SHOW GRANTS FOR CURRENT_USER') as $row) {
            $grant = array_values((array) $row)[0];
            if (str_contains($grant, 'WITH GRANT OPTION')) {
                throw new BackupFailure('Restore credentials must not have grant option.');
            }
            if (preg_match('/\AGRANT USAGE ON \*\.\* TO /', $grant)) {
                continue;
            }
            // MariaDB database grants treat _ as a wildcard unless escaped.
            if (! preg_match('/\AGRANT (?:ALL PRIVILEGES|[A-Z ,]+) ON `'.preg_quote('lessons\\_restore\\_test', '/').'`\.\* TO /', $grant)) {
                throw new BackupFailure('Restore credentials must have privileges only on lessons_restore_test, without global privileges, roles or grant option.');
            }
            $targetGrant = true;
        }
        if (! $targetGrant) {
            throw new BackupFailure('Restore credentials lack a dedicated target schema grant.');
        }
        if ($empty) {
            foreach (['TABLES', 'ROUTINES', 'EVENTS'] as $kind) {
                $field = $kind === 'TABLES' ? 'TABLE_SCHEMA' : ($kind === 'ROUTINES' ? 'ROUTINE_SCHEMA' : 'EVENT_SCHEMA');
                $count = $database->selectOne('SELECT COUNT(*) AS objects FROM information_schema.'.$kind.' WHERE '.$field.' = ?', ['lessons_restore_test']);
                if ((int) $count->objects !== 0) {
                    throw new BackupFailure('Restore target must be empty; existing tables, views, routines and events are never overwritten.');
                }
            }
        }
    }
}
