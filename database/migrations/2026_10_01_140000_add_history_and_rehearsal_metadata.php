<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_materials', fn (Blueprint $table) => $table->boolean('favorite')->default(false));
        Schema::table('lesson_versions', fn (Blueprint $table) => $table->string('purpose', 16)->default('authoring'));
        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->string('mode', 16)->default('lesson');
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('finished_at', 6)->nullable()->index();
            $table->json('visited_stage_ids')->nullable();
            $table->json('final_aggregates')->nullable();
            $table->text('teacher_notes')->nullable();
            $table->dateTime('details_purged_at', 6)->nullable();
            $table->dateTime('public_access_closed_at', 6)->nullable();
            $table->index(['owner_key', 'created_at', 'id'], 'teaching_sessions_owner_history');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_sessions', function (Blueprint $table) {
            $table->dropIndex('teaching_sessions_owner_history');
            $table->dropIndex(['finished_at']);
            $table->dropColumn(['mode', 'started_at', 'finished_at', 'visited_stage_ids', 'final_aggregates', 'teacher_notes', 'details_purged_at', 'public_access_closed_at']);
        });
        Schema::table('lesson_versions', fn (Blueprint $table) => $table->dropColumn('purpose'));
        Schema::table('lesson_materials', fn (Blueprint $table) => $table->dropColumn('favorite'));
    }
};
