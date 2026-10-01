<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('operation', 32);
            $table->string('status', 12);
            $table->boolean('dry_run');
            $table->json('counts')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->dateTime('started_at', 6);
            $table->dateTime('finished_at', 6)->nullable();
            $table->index(['operation', 'started_at']);
            $table->index(['status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_runs');
    }
};
