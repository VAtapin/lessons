<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class PlatformCheck extends Command
{
    protected $signature = 'lessons:check';

    protected $description = 'Check the database connection and required lesson schema without writing data';

    public function handle(): int
    {
        try {
            if ($this->callSilent('lessons:database-preflight') !== self::SUCCESS) {
                throw new RuntimeException;
            }

            $connection = DB::connection();
            $tables = [
                'users' => ['id', 'owner_key', 'ui_locale', 'email_verified_at'],
                'guest_workspace_claims' => ['source_owner_key', 'target_user_id', 'target_owner_key', 'result'],
                'lesson_materials' => ['id', 'owner_key', 'revision', 'current_version_id', 'favorite'],
                'lesson_versions' => ['id', 'lesson_material_id', 'status', 'document', 'purpose', 'editor_draft'],
                'lesson_save_receipts' => ['id', 'lesson_material_id', 'save_id', 'fingerprint', 'applied_revision', 'applied_version_id', 'created_at'],
                'teaching_sessions' => ['id', 'lesson_version_id', 'owner_key', 'locale', 'current_stage_id', 'revision', 'join_code', 'projector_token',
                    'status', 'timer_status', 'timer_ends_at', 'timer_remaining_seconds', 'timer_resume_on_session_resume', 'message', 'wave_id', 'wave_expires_at',
                    'mode', 'started_at', 'finished_at', 'visited_stage_ids', 'final_aggregates', 'teacher_notes', 'details_purged_at', 'public_access_closed_at'],
                'session_participants' => ['id', 'teaching_session_id', 'name', 'last_seen_at'],
                'session_answers' => ['id', 'teaching_session_id', 'session_participant_id', 'block_id', 'option_id',
                    'value', 'revision', 'moderation_status', 'display_text', 'published', 'acknowledged'],
                'session_block_states' => ['id', 'teaching_session_id', 'block_id', 'status', 'attempt_no'],
                'session_command_receipts' => ['id', 'teaching_session_id', 'command_id', 'fingerprint'],
                'media_owner_quotas' => ['owner_key', 'used_bytes'],
                'media_assets' => ['id', 'owner_key', 'title', 'revision', 'current_version_id', 'archived'],
                'media_versions' => ['id', 'media_asset_id', 'version_no', 'storage_key', 'mime', 'bytes', 'width', 'height', 'sha256', 'attribution'],
                'block_template_records' => ['id', 'owner_key', 'title', 'revision', 'current_version_id', 'archived'],
                'block_template_versions' => ['id', 'block_template_record_id', 'version_no', 'block', 'locales', 'default_locale', 'attribution'],
            ];
            foreach ($tables as $table => $columns) {
                $connection->table($table)->select($columns)->limit(0)->get();
            }

            if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                $column = $connection->selectOne(
                    "SELECT COLLATION_NAME AS collation_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'session_answers' AND COLUMN_NAME = 'block_id'",
                    [$connection->getDatabaseName()],
                );
                if ($column === null || $column->collation_name !== 'utf8mb4_bin') {
                    throw new RuntimeException;
                }
                $stateColumn = $connection->selectOne(
                    "SELECT COLLATION_NAME AS collation_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'session_block_states' AND COLUMN_NAME = 'block_id'",
                    [$connection->getDatabaseName()],
                );
                if ($stateColumn === null || $stateColumn->collation_name !== 'utf8mb4_bin') {
                    throw new RuntimeException;
                }
                $version = $connection->selectOne('SELECT VERSION() AS version')->version;
                if (! is_string($version) || ! preg_match('/\A[A-Za-z0-9.+_:~-]{1,120}\z/', $version)) {
                    throw new RuntimeException;
                }
                $this->info('Database version: '.$version);
            }

            $this->info('Database connection and lesson schema checks passed.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Platform database check failed. Verify the private database configuration and required migrations.');

            return self::FAILURE;
        }
    }
}
