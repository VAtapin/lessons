<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\JudgmentLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JudgmentLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_is_installed_once_with_exact_russian_words_and_translated_resources(): void
    {
        $first = app(JudgmentLessonInstaller::class)->install();
        $entry = $first['entry'];
        $this->assertTrue($first['created']);
        $doc = LessonDocument::fromArray($entry->version->document, app(BlockRegistry::class));
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_column(array_map(fn ($stage) => $stage->config, $doc->stages), 'durationSeconds')));
        $raw = json_decode(file_get_contents(resource_path('content/ne-speshi-sudit-source.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($doc->stages as $index => $stage) {
            $this->assertSame($raw['screens'][$index]['title'], $stage->content['ru']['title']);
            $this->assertSame('slate', $stage->config['theme']);
            $this->assertStringContainsString($raw['screens'][$index]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Student, 'ru')['content']);
            $publicText = preg_replace('/\s+/u', ' ', json_encode(array_map(fn ($block) => $block->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['screens'][$index]['slide'] as $line) {
                // Layout line breaks and A/B/C markers become matching/choice controls; words remain exact.
                if ($index === 0 && str_contains($line, '13–15')) {
                    continue;
                }
                $line = preg_replace('/^[АБВГ]\.\s*/u', '', $line);
                $line = preg_replace('/\s+/u', ' ', $line);
                $this->assertStringContainsString($line, str_replace('\\n', ' ', $publicText));
            }
        }
        $this->assertNull($doc->stages[1]->blocks[1]->solution);
        $this->assertNull($doc->stages[4]->blocks[2]->solution);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $count = count((require resource_path('content/ne-speshi-sudit-starters.php'))['templates']);
        $this->assertDatabaseCount('common_templates', $count);
        $this->assertFalse(app(JudgmentLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('catalog_entries', 1);
        $this->assertDatabaseCount('common_templates', $count);
        $entry->update(['status' => 'hidden']);
        app(JudgmentLessonInstaller::class)->install();
        $this->assertSame('hidden', $entry->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_decisions_confidence_classification_privacy_and_closing(string $locale): void
    {
        app(JudgmentLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('ne-speshi-sudit', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic student', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $execute('begin');
        for ($i = 1; $i <= 12; $i++) {
            $id = 'judge-step-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $execute('stage', ['stageId' => $id]);
            $public = $runtime->projector($token);
            $this->assertSame('slate', $public['stage']['config']['theme']);
            $this->assertArrayNotHasKey('documentation', $public);
            $this->assertArrayNotHasKey('notes', $public['stage']['content']);
            $submit = fn ($block, $value) => $runtime->answer($session['id'], $participant, $id, $block, ['stageId' => $id, 'blockId' => $block, 'value' => $value]);
            if (in_array($i, [2, 5], true)) {
                $submit($id.'-answer', ['optionId' => $i === 2 ? 'option-1' : 'option-4']);
                $submit($id.'-confidence', ['modeId' => $i === 2 ? 'confidence-3' : 'confidence-1']);
                $execute('block.review', ['blockId' => $id.'-answer']);
                $own = $runtime->student($session['id'], $participant)['ownAnswers'];
                $this->assertNull(collect($own)->firstWhere('blockId', $id.'-answer')['grade']);
                $this->assertSame($i === 2 ? 'confidence-3' : 'confidence-1', collect($own)->firstWhere('blockId', $id.'-confidence')['value']['modeId']);
                $confidence = collect($runtime->projector($token)['stage']['blocks'])->firstWhere('id', $id.'-confidence');
                $this->assertArrayNotHasKey('modeId', $confidence['runtime']['presentation'] ?? []);
                $this->assertEmpty($confidence['runtime']['results'] ?? []);
                if ($i === 5) {
                    $this->assertSame('option-1', collect($own)->firstWhere('blockId', 'judge-step-02-answer')['value']['optionId']);
                    $this->assertSame('confidence-3', collect($own)->firstWhere('blockId', 'judge-step-02-confidence')['value']['modeId']);
                }
            }
            if ($i === 3) {
                $submit($id.'-answer', ['pairs' => [['leftId' => 'claim-1', 'rightId' => 'category-1'], ['leftId' => 'claim-2', 'rightId' => 'category-2'], ['leftId' => 'claim-3', 'rightId' => 'category-3']]]);
                $execute('block.review', ['blockId' => $id.'-answer']);
                $this->assertTrue(collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id.'-answer')['grade']);
            }
            if ($i === 8) {
                $submit($id.'-answer', ['optionId' => 'option-2']);
                $execute('block.review', ['blockId' => $id.'-answer']);
                $this->assertTrue(collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id.'-answer')['grade']);
            }
            if (in_array($i, [9, 11, 12], true)) {
                $submit($id.'-answer', ['text' => 'Synthetic private answer']);
                $this->assertStringNotContainsString('Synthetic private answer', json_encode($runtime->projector($token)));
            }
            if ($i === 6) {
                try {
                    $submit($id.'-instruction', ['text' => 'Private paper reflection']);
                    $this->fail('Paper reflection must not collect server answers.');
                } catch (ApiProblem) {
                    $this->assertStringNotContainsString('Private paper reflection', json_encode($runtime->teacher($owner, $session['id'])));
                }
            }
        }
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertNotEmpty($runtime->projector($token)['closing']['content']['quote']);
    }
}
