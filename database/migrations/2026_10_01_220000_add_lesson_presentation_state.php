<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_block_states', function (Blueprint $table): void {
            $table->json('presentation')->nullable();
        });
        Schema::table('session_answers', function (Blueprint $table): void {
            $table->text('private_reply')->nullable();
            $table->boolean('discussed')->default(false);
            $table->unsignedTinyInteger('kindness_points')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('session_block_states', fn (Blueprint $table) => $table->dropColumn('presentation'));
        Schema::table('session_answers', fn (Blueprint $table) => $table->dropColumn(['private_reply', 'discussed', 'kindness_points']));
    }
};
