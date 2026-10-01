<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Catalog\NeighborUpgradeInstaller;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class NeighborUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public static function incompatibleOriginals(): array
    {
        return [['owner'], ['revision'], ['document'], ['occupied-version']];
    }

    #[DataProvider('incompatibleOriginals')]
    public function test_incompatible_source_or_occupied_version_is_refused_atomically(string $fault): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        $source = require resource_path('content/kto-moi-blizhnii-v2.php');
        if ($fault === 'owner') {
            $entry->version->material->update(['owner_key' => (string) Str::uuid()]);
        } elseif ($fault === 'revision') {
            $entry->version->material->update(['revision' => 7]);
        } elseif ($fault === 'document') {
            $changed = $entry->version->document;
            $changed['content']['ru']['title'] = 'Unexpected source title';
            // Fault injection only: production never bypasses immutable model guards.
            DB::table('lesson_versions')->where('id', $entry->lesson_version_id)->update(['document' => json_encode($changed)]);
        } else {
            $version = new LessonVersion(['lesson_material_id' => $entry->version->lesson_material_id, 'status' => 'released', 'purpose' => 'authoring', 'document' => $entry->version->document]);
            $version->id = $source['versionId'];
            $version->save();
        }
        $before = LessonVersion::query()->orderBy('id')->get()->map->getAttributes()->all();
        try {
            app(NeighborUpgradeInstaller::class)->install();
            $this->fail('Incompatible original or occupied immutable ID must not be upgraded.');
        } catch (RuntimeException) {
            $this->assertSame($before, LessonVersion::query()->orderBy('id')->get()->map->getAttributes()->all());
            $this->assertSame($entry->lesson_version_id, $entry->fresh()->lesson_version_id);
        }
    }

    public function test_upgrade_adds_one_release_preserves_old_copies_classes_and_admin_visibility_and_is_idempotent(): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        $original = $entry->version->getAttributes();
        $copy = app(CatalogService::class)->use($entry->slug, 'de', (string) Str::uuid(), true);
        $copyVersion = LessonMaterial::findOrFail($copy['lesson']['id'])->currentVersion;
        $copyBefore = $copyVersion->getAttributes();
        $sessionBefore = TeachingSession::findOrFail($copy['session']['id'])->getAttributes();
        $entry->update(['status' => 'hidden', 'approved_by' => 'existing-reviewer']);
        $approval = $entry->only(['status', 'approved_by', 'approved_at', 'metadata', 'slug']);
        $this->artisan('lessons:upgrade-neighbor')->expectsOutput('Upgraded: kto-moi-blizhnii')->assertSuccessful();
        $upgraded = $entry->fresh();
        $version = $upgraded->version;
        $this->assertNotSame($original['id'], $version->id);
        $this->assertSame($original, LessonVersion::findOrFail($original['id'])->getAttributes());
        $this->assertEquals($approval, $upgraded->only(array_keys($approval)));
        $this->assertSame($copyBefore, $copyVersion->fresh()->getAttributes());
        $this->assertSame($sessionBefore, TeachingSession::findOrFail($copy['session']['id'])->getAttributes());
        $this->assertSame($original['lesson_material_id'], $version->lesson_material_id);
        $this->assertSame(2, $version->material->revision);
        $this->assertDatabaseCount('catalog_entries', 1);
        $versionBefore = $version->getAttributes();
        $this->artisan('lessons:upgrade-neighbor')->expectsOutput('Already upgraded: kto-moi-blizhnii')->assertSuccessful();
        $this->assertSame($versionBefore, $version->fresh()->getAttributes());
        $this->assertSame(2, LessonVersion::where('lesson_material_id', $version->lesson_material_id)->count());
        $this->assertSame('hidden', $entry->fresh()->status);
    }

    public function test_new_source_contains_bilingual_original_workflow_and_complete_methodical_material(): void
    {
        app(NeighborInstaller::class)->install();
        $entry = app(NeighborUpgradeInstaller::class)->install()['entry'];
        $document = LessonDocument::fromArray($entry->version->document, app(BlockRegistry::class));
        $this->assertCount(13, $document->stages);
        $this->assertSame(2700, array_sum(array_column(array_column($document->toArray()['stages'], 'config'), 'durationSeconds')));
        $this->assertSame((require resource_path('content/neighbor-documentation.php')), $document->toArray()['documentation']);
        $blocks = [];
        foreach ($document->stages as $stage) {
            $this->assertTrue($stage->config['openTasks']);
            foreach ($stage->blocks as $block) {
                $blocks[$block->id] = $block;
                $this->assertSame(['ru', 'de'], array_keys($block->content));
            }
        }
        $this->assertSame(['priest-excuse', 'levite-excuse'], $blocks['excuses-board']->config['sourceBlockIds']);
        $this->assertSame(['week-promise'], $blocks['promise-board']->config['sourceBlockIds']);
        $this->assertSame('samaritan-motive', $blocks['samaritan-reveal']->config['reviewBlockId']);
        $this->assertSame('neighbor-choice', $blocks['neighbor-reveal']->config['reviewBlockId']);
        $this->assertSame(['Увидел', 'Подошёл', 'Помог', 'Привёз', 'Позаботился дальше'], array_map(
            fn ($id) => array_column($blocks['help-sequence']->content['ru']['items'], 'text', 'itemId')[$id],
            $blocks['help-sequence']->solution['itemIds'],
        ));
        $this->assertCount(1, $document->stages[6]->blocks);
        foreach (['newcomer', 'books', 'game'] as $id) {
            $this->assertSame(['excuse', 'help'], array_column($blocks[$id.'-discussion']->content['ru']['modes'], 'modeId'));
            $this->assertSame(['excuse', 'help'], array_column($blocks[$id.'-discussion']->content['de']['modes'], 'modeId'));
            $this->assertSame(['Как пройти мимо?', 'Как помочь?'], array_column($blocks[$id.'-discussion']->content['ru']['modes'], 'label'));
            $this->assertSame(['Wie vorbeigehen?', 'Wie helfen?'], array_column($blocks[$id.'-discussion']->content['de']['modes'], 'label'));
            $this->assertNotSame($blocks[$id.'-discussion']->content['ru']['modes'][0]['label'], $blocks[$id.'-discussion']->content['ru']['modes'][0]['text']);
            $this->assertSame('core.free-response', $blocks[$id.'-words']->type);
        }
        $this->assertSame(array_fill_keys(['traveler', 'robber_1', 'robber_2', 'priest', 'levite', 'samaritan'], 1), $blocks['traveler-roles']->config['capacities']);
    }

    public function test_mismatched_original_receipt_fails_without_creating_or_overwriting_any_release(): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        DB::table('catalog_entries')->where('id', $entry->id)->update(['source_hash' => str_repeat('0', 64)]);
        $before = $entry->version->getAttributes();
        try {
            app(NeighborUpgradeInstaller::class)->install();
            $this->fail('An unexpected source receipt must be refused.');
        } catch (RuntimeException $problem) {
            $this->assertStringContainsString('receipt differs', $problem->getMessage());
        }
        $this->assertDatabaseCount('lesson_versions', 1);
        $this->assertSame($before, $entry->version->fresh()->getAttributes());
        $this->assertSame(1, $entry->version->material->revision);
    }

    public function test_ordinary_catalog_update_still_cannot_repin_after_trusted_upgrade(): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        $oldId = $entry->lesson_version_id;
        $entry = app(NeighborUpgradeInstaller::class)->install()['entry'];
        $newId = $entry->lesson_version_id;
        try {
            $entry->update(['lesson_version_id' => $oldId]);
            $this->fail('Ordinary updates must remain immutable.');
        } catch (LogicException) {
            $this->assertSame($newId, $entry->fresh()->lesson_version_id);
        }
    }
}
