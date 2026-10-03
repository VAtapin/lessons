<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CommonStarterInstaller;
use App\Application\Catalog\CommonTemplateService;
use App\Application\Catalog\JosephLessonInstaller;
use App\Application\Catalog\NeighborDocumentationInstaller;
use App\Models\CommonTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LibraryDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_formats_include_existing_downloads_and_separate_legacy_documentation(): void
    {
        app(JosephLessonInstaller::class)->install();
        app(NeighborDocumentationInstaller::class)->install();
        foreach (['ru', 'de'] as $locale) {
            foreach (['notes', 'presentation'] as $format) {
                $result = $this->getJson('/api/catalog?locale='.$locale.'&format='.$format)->assertOk()->assertJsonCount(2, 'entries');
                foreach ($result->json('entries') as $card) {
                    $this->assertSame([$locale], $card['locales']);
                }
            }
        }
        $this->getJson('/api/catalog?format=worksheet')->assertOk()->assertJsonCount(1, 'entries')->assertJsonCount(2, 'entries.0.downloads');
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(2, 'entries')->assertJsonMissingPath('entries.0.materialId');
        $this->getJson('/api/catalog?format=interactive')->assertOk()->assertJsonCount(2, 'entries')->assertJsonMissingPath('entries.0.downloads');
        $this->getJson('/api/catalog?format=game')->assertOk()->assertJsonCount(0, 'entries');
        $this->getJson('/api/catalog?format=questions')->assertOk()->assertJsonCount(0, 'entries');
        foreach ($this->getJson('/api/catalog?format=presentation')->json('entries') as $card) {
            $this->assertSame('PPTX', $card['downloads'][0]['extension']);
            $this->get($card['downloads'][0]['url'])->assertOk();
            $this->get('/ru/catalog/'.$card['slug'])->assertOk();
        }
        $this->get('/ru/catalog?format=notes')->assertOk()->assertSee('download', false)->assertSee('Открыть интерактивный урок');
    }

    public function test_new_universal_pack_is_independent_bilingual_and_idempotent(): void
    {
        $this->artisan('lessons:install-universal-starters')->assertSuccessful();
        $this->artisan('lessons:install-universal-starters')->assertSuccessful();
        $this->assertDatabaseCount('common_templates', 8);
        foreach (CommonTemplate::with('record.currentVersion')->get() as $common) {
            foreach (['ru', 'de'] as $locale) {
                $this->getJson('/api/catalog/templates/'.$common->id.'?locale='.$locale)
                    ->assertOk()->assertJsonMissingPath('preview.teacherNotes')->assertJsonMissingPath('preview.solution');
                $copy = app(CommonTemplateService::class)->instantiate($common->id, $common->record->current_version_id, [$locale], (string) Str::uuid());
                $this->assertNotSame($common->record->currentVersion->block['id'], $copy['id']);
                $this->assertSame([$locale], array_keys($copy['content']));
                $this->assertNotEmpty($copy['teacherNotes'][$locale]);
                $this->assertEmpty($copy['config']['reviewBlockId'] ?? null);
            }
        }
    }

    public function test_library_filters_before_paging_and_never_loads_all_histories(): void
    {
        app(CommonStarterInstaller::class)->install();
        app(JosephLessonInstaller::class)->install();
        $this->artisan('lessons:install-universal-starters')->assertSuccessful();
        DB::enableQueryLog();
        $first = $this->getJson('/api/catalog/templates?locale=ru&scope=lesson')->assertOk()->assertJsonCount(12, 'templates');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThan(12, count($queries));
        $this->assertArrayNotHasKey('versions', $first->json('templates.0'));
        $this->assertArrayNotHasKey('block', $first->json('templates.0'));
        $second = $this->getJson('/api/catalog/templates?locale=ru&scope=lesson&page=2')->assertOk()->assertJsonCount(12, 'templates');
        $this->assertEmpty(array_intersect(array_column($first->json('templates'), 'id'), array_column($second->json('templates'), 'id')));
        $this->getJson('/api/catalog/templates?scope=universal')->assertOk()->assertJsonCount(8, 'templates')->assertJsonPath('pagination.total', 8);
        $this->getJson('/api/catalog/templates?scope=universal&type=core.free-response')->assertOk()->assertJsonCount(2, 'templates');
        $this->getJson('/api/catalog/templates?locale=de&scope=universal&q=Gedankenaustausch')->assertOk()->assertJsonCount(1, 'templates');
        $this->getJson('/api/catalog/templates?tag=joseph')->assertOk()->assertJsonPath('pagination.total', 76);
        $this->getJson('/api/catalog/templates?q=%25')->assertOk()->assertJsonCount(0, 'templates');
        $this->getJson('/api/catalog/templates?page=0')->assertUnprocessable();
        $this->getJson('/api/catalog/templates?scope=unknown')->assertUnprocessable();
        $this->getJson('/api/catalog/templates?page=999')->assertOk()->assertJsonCount(0, 'templates');
    }
}
