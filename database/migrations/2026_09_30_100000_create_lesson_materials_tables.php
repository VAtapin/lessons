<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_key')->index();
            $table->unsignedInteger('revision');
            $table->uuid('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lesson_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lesson_material_id')->constrained('lesson_materials');
            $table->string('status', 16);
            $table->json('document');
            $table->timestamps();
        });

        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('lesson_versions');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('lesson_versions');
        Schema::dropIfExists('lesson_materials');
    }
};
