<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DatabasePreflight extends Command
{
    protected $signature = 'lessons:database-preflight {--empty : Require a database without user schema objects}';

    protected $description = 'Check the configured database without changing its schema or data';

    public function handle(): int
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
                throw new RuntimeException;
            }
            $connection->select('SELECT 1');

            if ($driver === 'sqlite') {
                if ($this->option('empty')) {
                    $objects = $connection->selectOne("SELECT COUNT(*) AS total FROM sqlite_schema WHERE type IN ('table', 'view', 'trigger') AND substr(name, 1, 7) <> 'sqlite_'");
                    if ((int) $objects->total !== 0) {
                        throw new RuntimeException;
                    }
                }
            } else {
                $database = $connection->getDatabaseName();
                $selected = $connection->selectOne('SELECT DATABASE() AS database_name');
                if (! is_string($database) || $database === '' || $selected->database_name !== $database) {
                    throw new RuntimeException;
                }
                if ($this->option('empty')) {
                    foreach (['TABLES' => 'TABLE_SCHEMA', 'VIEWS' => 'TABLE_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'TRIGGERS' => 'TRIGGER_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $table => $column) {
                        $objects = $connection->selectOne("SELECT COUNT(*) AS total FROM information_schema.{$table} WHERE {$column} = ?", [$database]);
                        if ((int) $objects->total !== 0) {
                            throw new RuntimeException;
                        }
                    }
                }
            }

            $this->info($this->option('empty') ? 'Database connection verified; schema is empty.' : 'Database connection verified.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Database preflight failed. Verify the private database configuration and schema before proceeding.');

            return self::FAILURE;
        }
    }
}
