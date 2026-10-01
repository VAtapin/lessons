<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_sessions', function (Blueprint $table): void {
            $table->boolean('join_projection')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('teaching_sessions', function (Blueprint $table): void {
            $table->dropColumn('join_projection');
        });
    }
};
