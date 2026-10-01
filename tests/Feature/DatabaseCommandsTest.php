<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_accepts_a_truly_empty_database_without_creating_tables(): void
    {
        $this->withEmptyDatabase(function (): void {
            $this->artisan('lessons:database-preflight', ['--empty' => true])->assertSuccessful();
            $this->assertSame([], Schema::getTables());
        });
    }

    public function test_preflight_refuses_existing_schema_without_modifying_it(): void
    {
        $tables = Schema::getTables();
        $this->artisan('lessons:database-preflight', ['--empty' => true])
            ->expectsOutputToContain('Database preflight failed.')->assertFailed();
        $this->assertSame($tables, Schema::getTables());
        $this->artisan('lessons:database-preflight')->assertSuccessful();
    }

    public function test_schema_check_refuses_an_empty_database_without_creating_missing_tables(): void
    {
        $this->withEmptyDatabase(function (): void {
            $this->artisan('lessons:check')->expectsOutputToContain('Platform database check failed.')->assertFailed();
            $this->assertSame([], Schema::getTables());
        });
    }

    public function test_schema_check_accepts_the_migrated_domain_schema_without_writing_rows(): void
    {
        $this->artisan('lessons:check')->expectsOutputToContain('Database connection and lesson schema checks passed.')->assertSuccessful();
        foreach (['users', 'guest_workspace_claims', 'lesson_materials', 'lesson_versions', 'lesson_save_receipts', 'teaching_sessions', 'session_participants', 'session_answers', 'session_command_receipts', 'session_block_states',
            'media_owner_quotas', 'media_assets', 'media_versions', 'block_template_records', 'block_template_versions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_empty_preflight_also_refuses_a_view_without_tables(): void
    {
        $this->withEmptyDatabase(function (): void {
            DB::statement('CREATE VIEW existing_view AS SELECT 1 AS value');
            $this->artisan('lessons:database-preflight', ['--empty' => true])->assertFailed();
            $this->assertSame(1, (int) DB::selectOne('SELECT value FROM existing_view')->value);
        });
    }

    private function withEmptyDatabase(callable $callback): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.preflight_empty' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('preflight_empty');
        try {
            $callback();
        } finally {
            DB::purge('preflight_empty');
            DB::setDefaultConnection($original);
        }
    }
}
