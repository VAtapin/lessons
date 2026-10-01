<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_answers', function (Blueprint $table) {
            $table->string('option_id', 128)->nullable()->change();
            $table->json('value')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->string('moderation_status', 16)->nullable();
            $table->text('display_text')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('acknowledged')->default(false);
        });
        Schema::create('session_block_states', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->string('block_id', 128)->collation(
                Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin',
            );
            $table->string('status', 16);
            $table->unsignedInteger('attempt_no')->default(1);
            $table->timestamps();
            $table->unique(['teaching_session_id', 'block_id'], 'session_block_states_session_block_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_block_states');
        Schema::table('session_answers', function (Blueprint $table) {
            $table->dropColumn(['value', 'revision', 'moderation_status', 'display_text', 'published', 'acknowledged']);
        });
        // Keep option_id nullable: restoring NOT NULL could destroy generic answers.
    }
};
