<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('teaching_session_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at', 6);
            $table->timestamp('accepted_at', 6)->nullable();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamps(6);
        });
        Schema::create('teacher_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('teaching_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_invitation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('proof_hash', 64);
            $table->string('display_name', 80);
            $table->timestamp('expires_at', 6);
            $table->timestamp('revoked_at', 6)->nullable();
            $table->timestamps(6);
        });
        Schema::table('teaching_sessions', function (Blueprint $table): void {
            $table->boolean('presenter_is_owner')->default(true);
            $table->uuid('presenter_grant_id')->nullable();
            $table->unsignedInteger('presenter_epoch')->default(0);
        });
        Schema::table('session_command_receipts', function (Blueprint $table): void {
            $table->string('actor_kind', 16)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->unsignedInteger('control_epoch')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('session_command_receipts', fn (Blueprint $table) => $table->dropColumn(['actor_kind', 'actor_id', 'control_epoch']));
        Schema::table('teaching_sessions', fn (Blueprint $table) => $table->dropColumn(['presenter_is_owner', 'presenter_grant_id', 'presenter_epoch']));
        Schema::dropIfExists('teacher_grants');
        Schema::dropIfExists('teacher_invitations');
    }
};
