<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_version_id')->constrained('lesson_versions')->restrictOnDelete();
            $table->uuid('owner_key')->index();
            $table->string('locale', 35);
            $table->string('current_stage_id', 128);
            $table->unsignedInteger('revision')->default(1);
            $table->string('join_code', 8)->unique();
            $table->string('projector_token', 64)->unique();
            $table->timestamps();
        });
        Schema::create('session_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
        });
        Schema::create('session_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->foreignUuid('session_participant_id')->constrained('session_participants')->cascadeOnDelete();
            // Domain IDs are case-sensitive, including within answer uniqueness.
            $table->string('block_id', 128)->collation(
                Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin',
            );
            $table->string('option_id', 128);
            $table->timestamps();
            $table->unique(['teaching_session_id', 'session_participant_id', 'block_id'], 'session_answers_participant_block_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_answers');
        Schema::dropIfExists('session_participants');
        Schema::dropIfExists('teaching_sessions');
    }
};
