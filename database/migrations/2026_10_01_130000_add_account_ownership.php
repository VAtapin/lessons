<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('owner_key')->nullable()->unique();
            $table->string('ui_locale', 16)->nullable();
        });
        Schema::create('guest_workspace_claims', function (Blueprint $table): void {
            $table->uuid('source_owner_key')->primary();
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('target_owner_key');
            $table->json('result');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_workspace_claims');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['owner_key']);
            $table->dropColumn(['owner_key', 'ui_locale']);
        });
    }
};
