<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\ZakkheiLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\Stage;
use App\Domain\Lessons\ValidationException;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ZakkheiLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_is_installed_once_with_exact_russian_words_and_translated_resources(): void
    {
        $first = app(ZakkheiLessonInstaller::class)->install();
        $entry = $first['entry'];
        $this->assertTrue($first['created']);
        $doc = LessonDocument::fromArray($entry->version->document, app(BlockRegistry::class));
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_column(array_map(fn ($stage) => $stage->config, $doc->stages), 'durationSeconds')));
        $raw = json_decode(file_get_contents(resource_path('content/zakkhei-source.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($doc->stages as $index => $stage) {
            $this->assertSame($raw['screens'][$index]['title'], $stage->content['ru']['title']);
            $this->assertSame('terracotta', $stage->config['theme']);
            $this->assertStringContainsString($raw['screens'][$index]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Student, 'ru')['content']);
            $publicText = preg_replace('/\s+/u', ' ', json_encode(array_map(fn ($block) => $block->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['screens'][$index]['slide'] as $line) {
                // Layout line breaks and A/B/C markers become matching/choice controls; words remain exact.
                if ($index === 0 && str_contains($line, '9–11')) {
                    continue;
                }
                $line = preg_replace('/^[АБВ]\.\s*/u', '', $line);
                $line = preg_replace('/\s+/u', ' ', $line);
                $this->assertStringContainsString($line, str_replace('\\n', ' ', $publicText));
            }
        }
        $this->assertNull($doc->stages[6]->blocks[1]->solution);
        $this->assertStringContainsString('Единственный жёсткий порядок нам не нужен.', $doc->stages[6]->content['ru']['notes']);
        $this->assertSame('core.prompt', $doc->stages[10]->blocks[1]->type);
        $this->assertStringContainsString('Карточку оставьте себе.', $doc->stages[10]->blocks[1]->content['ru']['text']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $count = count((require resource_path('content/zakkhei-starters.php'))['templates']);
        $this->assertDatabaseCount('common_templates', $count);
        $this->assertFalse(app(ZakkheiLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('catalog_entries', 1);
        $this->assertDatabaseCount('common_templates', $count);
        $entry->update(['status' => 'hidden']);
        app(ZakkheiLessonInstaller::class)->install();
        $this->assertSame('hidden', $entry->fresh()->status);
    }

    public function test_receipt_drift_does_not_overwrite_resources(): void
    {
        $entry = app(ZakkheiLessonInstaller::class)->install()['entry'];
        $before = $entry->version->getAttributes();
        DB::table('catalog_entries')->where('id', $entry->id)->update(['source_hash' => str_repeat('0', 64)]);
        try {
            app(ZakkheiLessonInstaller::class)->install();
            $this->fail('Source drift must fail closed.');
        } catch (RuntimeException) {
            $this->assertSame($before, $entry->version->fresh()->getAttributes());
        }
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_full_runtime_preserves_choices_ungraded_cards_private_paper_step_and_closing(string $locale): void
    {
        app(ZakkheiLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('zakkhei', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic student', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $execute('begin');
        foreach ($session['document']['documentation']['files'] as $i => $file) {
            $this->assertSame(__('studio.documentation_zakkhei_'.($i + 1), [], $locale), $file['label']);
        }
        for ($i = 1; $i <= 12; $i++) {
            $id = 'zakkhei-step-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $execute('stage', ['stageId' => $id]);
            $public = $runtime->projector($token);
            $this->assertSame('terracotta', $public['stage']['config']['theme']);
            $this->assertArrayNotHasKey('documentation', $public);
            $this->assertArrayNotHasKey('notes', $public['stage']['content']);
            if ($i === 2) {
                $answer = $runtime->answer($session['id'], $participant, $id, $id.'-answer', ['stageId' => $id, 'blockId' => $id.'-answer', 'value' => ['pairs' => [['leftId' => 'case-1', 'rightId' => 'kind-1'], ['leftId' => 'case-2', 'rightId' => 'kind-2']]]]);
                $execute('block.review', ['blockId' => $id.'-answer']);
                $this->assertTrue(collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id.'-answer')['grade']);
            }
            if (in_array($i, [4, 9], true)) {
                $runtime->answer($session['id'], $participant, $id, $id.'-answer', ['stageId' => $id, 'blockId' => $id.'-answer', 'value' => ['optionId' => $i === 4 ? 'option-1' : 'option-2']]);
            }
            if ($i === 7) {
                $memo = collect($public['stage']['blocks'])->firstWhere('id', $id.'-memo');
                $this->assertEmpty($memo['runtime']['presentation']['visible'] ?? false);
                $order = ['action-3', 'action-1', 'action-2', 'action-4'];
                $answer = $runtime->answer($session['id'], $participant, $id, $id.'-answer', ['stageId' => $id, 'blockId' => $id.'-answer', 'value' => ['itemIds' => $order]]);
                $this->assertNull(collect($answer['ownAnswers'])->firstWhere('blockId', $id.'-answer')['grade']);
                $this->assertSame($order, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id.'-answer')['value']['itemIds']);
                $execute('presentation.toggle', ['blockId' => $id.'-memo']);
                $memo = collect($runtime->projector($token)['stage']['blocks'])->firstWhere('id', $id.'-memo');
                $this->assertTrue($memo['runtime']['presentation']['visible']);
                $result = collect($runtime->student($session['id'], $participant)['stage']['blocks'])->firstWhere('id', $id.'-answer');
                $this->assertArrayNotHasKey('itemIds', $result['runtime']['results'] ?? []);
            }
            if ($i === 8 || $i === 12) {
                foreach ($i === 8 ? [$id.'-teacher', $id.'-author'] : [$id.'-answer'] as $blockId) {
                    $runtime->answer($session['id'], $participant, $id, $blockId, ['stageId' => $id, 'blockId' => $blockId, 'value' => ['text' => 'Synthetic answer']]);
                    $this->assertStringNotContainsString('Synthetic answer', json_encode($runtime->projector($token)));
                }
            }
            if ($i === 11) {
                try {
                    $runtime->answer($session['id'], $participant, $id, $id.'-instruction', ['stageId' => $id, 'blockId' => $id.'-instruction', 'value' => ['text' => 'Private paper step']]);
                    $this->fail('A private paper worksheet must not collect server answers.');
                } catch (ApiProblem) {
                    $this->assertStringNotContainsString('Private paper step', json_encode($runtime->teacher($owner, $session['id'])));
                }
            }
        }
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertNotEmpty($runtime->projector($token)['closing']['content']['quote']);
    }

    public function test_theme_is_strict_and_legacy_documents_remain_unchanged(): void
    {
        $source = require resource_path('content/zakkhei.php');
        $stage = $source['document']['stages'][0];
        unset($stage['config']['theme']);
        $this->assertArrayNotHasKey('theme', Stage::fromArray($stage, app(BlockRegistry::class), ['ru', 'de'])->config);
        $stage['config']['theme'] = 'arbitrary-css';
        $this->expectException(ValidationException::class);
        Stage::fromArray($stage, app(BlockRegistry::class), ['ru', 'de']);
    }
}
