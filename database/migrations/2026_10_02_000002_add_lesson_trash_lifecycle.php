<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->timestamp('archived_at', 6)->nullable();
            $table->timestamp('purged_at', 6)->nullable();
        });
        // Earlier archives only recorded updated_at; preserve the best available timestamp.
        DB::table('lesson_materials')->where('archived', true)->update(['archived_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('lesson_materials', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'purged_at']);
        });
    }
};
