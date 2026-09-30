<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_owner_quotas', function (Blueprint $table) {
            $table->uuid('owner_key')->primary();
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->timestamps();
        });
        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_key')->index();
            $table->string('title', 200);
            $table->json('tags');
            $table->text('author');
            $table->text('source');
            $table->string('rights_basis', 32);
            $table->text('usage_rights');
            $table->unsignedInteger('revision');
            $table->boolean('archived')->default(false);
            $table->uuid('current_version_id')->nullable();
            $table->timestamps();
        });
        Schema::create('media_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('media_asset_id')->constrained('media_assets');
            $table->unsignedInteger('version_no');
            $table->string('storage_key')->unique();
            $table->string('mime', 50);
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->json('attribution');
            $table->timestamps();
            $table->unique(['media_asset_id', 'version_no']);
        });
        Schema::table('media_assets', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('media_versions');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('media_versions');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_owner_quotas');
    }
};
