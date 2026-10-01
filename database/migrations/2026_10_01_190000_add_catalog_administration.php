<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::table('catalog_entries', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(1));
        Schema::create('catalog_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_key')->index();
            $table->foreignUuid('lesson_version_id')->constrained('lesson_versions')->restrictOnDelete();
            $table->string('slug', 120);
            $table->json('metadata');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 20)->default('pending')->index();
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('catalog_entry_id')->nullable()->constrained('catalog_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['owner_key', 'lesson_version_id', 'slug'], 'catalog_submission_identity');
        });
        Schema::create('catalog_terms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 20);
            $table->string('key', 80);
            $table->json('labels');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['kind', 'key']);
        });
        foreach (require database_path('data/catalog-terms.php') as $term) {
            DB::table('catalog_terms')->insert(array_replace($term, ['id' => (string) Str::uuid(), 'labels' => json_encode($term['labels'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'active' => true, 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]));
        }
        Schema::create('common_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('block_template_record_id')->unique()->constrained('block_template_records')->restrictOnDelete();
            $table->json('labels');
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('common_templates');
        Schema::dropIfExists('catalog_terms');
        Schema::dropIfExists('catalog_submissions');
        Schema::table('catalog_entries', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
