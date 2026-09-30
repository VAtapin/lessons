<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->string('status', 16)->default('running');
            $table->string('timer_status', 16)->default('idle');
            $table->dateTime('timer_ends_at', 6)->nullable();
            $table->unsignedInteger('timer_remaining_seconds')->default(0);
            $table->boolean('timer_resume_on_session_resume')->default(false);
            $table->text('message')->nullable();
            $table->uuid('wave_id')->nullable();
            $table->dateTime('wave_expires_at', 6)->nullable();
        });
        Schema::table('session_participants', function (Blueprint $table) {
            $table->dateTime('last_seen_at', 6)->nullable();
        });
        Schema::create('session_command_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->uuid('command_id');
            $table->char('fingerprint', 64);
            $table->timestamps();
            $table->unique(['teaching_session_id', 'command_id'], 'session_command_receipts_session_command_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_command_receipts');
        Schema::table('session_participants', fn (Blueprint $table) => $table->dropColumn('last_seen_at'));
        Schema::table('teaching_sessions', fn (Blueprint $table) => $table->dropColumn([
            'status', 'timer_status', 'timer_ends_at', 'timer_remaining_seconds', 'timer_resume_on_session_resume',
            'message', 'wave_id', 'wave_expires_at',
        ]));
    }
};
