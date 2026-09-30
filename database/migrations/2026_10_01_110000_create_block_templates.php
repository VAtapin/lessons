<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_template_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_key')->index();
            $table->string('title', 200);
            $table->json('tags');
            $table->text('author');
            $table->text('source');
            $table->string('rights_basis', 32);
            $table->text('usage_rights');
            $table->unsignedInteger('revision');
            $table->uuid('current_version_id')->nullable();
            $table->boolean('archived')->default(false);
            $table->timestamps();
        });
        Schema::create('block_template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('block_template_record_id')->constrained('block_template_records');
            $table->unsignedInteger('version_no');
            $table->json('block');
            $table->json('locales');
            $table->string('default_locale', 35);
            $table->json('attribution');
            $table->timestamps();
            $table->unique(['block_template_record_id', 'version_no'], 'block_template_versions_record_number_unique');
        });
        Schema::table('block_template_records', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('block_template_versions');
        });
    }

    public function down(): void
    {
        Schema::table('block_template_records', fn (Blueprint $table) => $table->dropForeign(['current_version_id']));
        Schema::dropIfExists('block_template_versions');
        Schema::dropIfExists('block_template_records');
    }
};
