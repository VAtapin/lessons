<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_documentations', function (Blueprint $table): void {
            $table->uuid('lesson_version_id')->primary();
            $table->foreign('lesson_version_id')->references('id')->on('lesson_versions')->cascadeOnDelete();
            $table->json('payload');
            $table->char('source_hash', 64);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_documentations');
    }
};
