<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_versions', fn (Blueprint $table) => $table->json('editor_draft')->nullable());
        Schema::create('lesson_save_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('lesson_material_id')->constrained('lesson_materials')->cascadeOnDelete();
            $table->uuid('save_id');
            $table->char('fingerprint', 64);
            $table->unsignedInteger('applied_revision');
            $table->foreignUuid('applied_version_id')->constrained('lesson_versions')->restrictOnDelete();
            $table->dateTime('created_at', 6)->index();
            $table->unique(['lesson_material_id', 'save_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_save_receipts');
        Schema::table('lesson_versions', fn (Blueprint $table) => $table->dropColumn('editor_draft'));
    }
};
