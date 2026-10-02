<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\TalentLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\EditorDraft;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TalentLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_screens_notes_and_downloads_are_preserved_in_both_languages(): void
    {
        $result = app(TalentLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_column(array_map(fn ($s) => $s->config, $doc->stages), 'durationSeconds')));
        $raw = json_decode(file_get_contents(resource_path('content/talant-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString($raw['screens'][$i]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, 'de')['content']);
            $text = $normalize(json_encode(array_map(fn ($b) => $b->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['screens'][$i]['slides'] as $number) {
                $lines = $raw['slides'][$number - 1]['slide'];
                if ($number === 1) {
                    $this->assertStringContainsString($raw['title'], $text);

                    continue;
                }
                foreach ($lines as $line) {
                    $this->assertStringContainsString($normalize($line), $text);
                }
            }
        }
        $this->assertStringContainsString($raw['handout'], $doc->documentation->toArray()['content']['ru']['plan']);
        $this->assertStringContainsString('30 а негодного раба', $doc->documentation->toArray()['content']['ru']['plan']);
        $this->assertStringContainsString('30 Werft den unnützen', $doc->documentation->toArray()['content']['de']['plan']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $count = count((require resource_path('content/talant-starters.php'))['templates']);
        $this->assertDatabaseCount('common_templates', $count);
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(TalentLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', $count);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_table_example_roles_feedback_and_final_answers_keep_the_original_order(string $locale): void
    {
        app(TalentLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('chto-delat-so-svoim-talantom', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $block = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        $execute('stage', ['stageId' => 'talent-step-02']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $table = $block($dto, 'talent-step-02-table');
            $this->assertEmpty($table['content']['text']);
            $this->assertArrayNotHasKey('table', $table['content']);
            $this->assertArrayNotHasKey('source', $table['content']);
        }
        $execute('role.reveal.next', ['blockId' => 'talent-step-02-roles']);
        $roles = $block($runtime->student($session['id'], $participant), 'talent-step-02-roles');
        $this->assertSame(['role-1'], $roles['runtime']['presentation']['revealedRoleIds']);
        $execute('presentation.toggle', ['blockId' => 'talent-step-02-table']);
        $table = $block($runtime->projector($token), 'talent-step-02-table')['content']['table'];
        $this->assertSame(['5', '2', '1'], array_column($table['rows'], 1));
        $this->assertSame(['10', '4', '1'], array_column($table['rows'], 2));
        $execute('presentation.toggle', ['blockId' => 'talent-step-02-table']);
        $this->assertArrayNotHasKey('table', $block($runtime->projector($token), 'talent-step-02-table')['content']);
        $execute('stage', ['stageId' => 'talent-step-10']);
        $this->assertEmpty($block($runtime->projector($token), 'talent-step-10-example')['content']['text']);
        $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        $execute('presentation.toggle', ['blockId' => 'talent-step-10-example']);
        $this->assertNotEmpty($block($runtime->projector($token), 'talent-step-10-example')['content']['text']);
        foreach ([9 => ['helped', 'clarify'], 12 => ['meaning', 'step']] as $n => $ids) {
            $stage = 'talent-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $execute('stage', ['stageId' => $stage]);
            foreach ($ids as $suffix) {
                $id = $stage.'-'.$suffix;
                $runtime->answer($session['id'], $participant, $stage, $id, ['stageId' => $stage, 'blockId' => $id, 'value' => ['text' => 'Synthetic answer '.$suffix]]);
                $this->assertSame('Synthetic answer '.$suffix, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
            }
        }
        $this->assertArrayNotHasKey('documentation', $runtime->projector($token));
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame('terracotta', $runtime->projector($token)['stage']['config']['theme']);
        $this->assertNotEmpty($runtime->projector($token)['closing']['content']['title']);
    }

    public static function invalidTables(): array
    {
        return [['short-row'], ['different-translations'], ['wrong-kind'], ['nontext'], ['empty-rows'], ['unknown-field']];
    }

    public function test_table_translation_drafts_keep_blank_cells_and_report_their_exact_paths(): void
    {
        $source = require resource_path('content/talant.php');
        $source['document']['stages'][1]['blocks'][3]['content']['de']['table']['headers'][1] = '';
        $source['document']['stages'][1]['blocks'][3]['content']['de']['table']['rows'][2][2] = "\u{00A0}";
        $draft = EditorDraft::fromArray($source['document'], app(BlockRegistry::class));
        $readiness = $draft->blockReadiness('talent-step-02-table');
        $this->assertSame(['ru'], $readiness['readyLocales']);
        $this->assertSame('partial', $readiness['locales'][1]['status']);
        $this->assertSame([
            '/stages/1/blocks/3/content/de/table/headers/1',
            '/stages/1/blocks/3/content/de/table/rows/2/2',
        ], array_column($readiness['locales'][1]['issues'], 'path'));
        $this->assertSame("\u{00A0}", $draft->toArray()['stages'][1]['blocks'][3]['content']['de']['table']['rows'][2][2]);
    }

    #[DataProvider('invalidTables')]
    public function test_reusable_table_validation_is_strict(string $case): void
    {
        $source = require resource_path('content/talant.php');
        $b = &$source['document']['stages'][1]['blocks'][3];
        match ($case) {
            'short-row' => array_pop($b['content']['ru']['table']['rows'][0]),
            'different-translations' => array_pop($b['content']['de']['table']['rows']),
            'wrong-kind' => $b['config']['kind'] = 'scene',
            'nontext' => $b['content']['ru']['table']['rows'][0][1] = 5,
            'empty-rows' => $b['content']['ru']['table']['rows'] = [],
            'unknown-field' => $b['content']['ru']['table']['html'] = '<script>',
        };
        $this->expectException(ValidationException::class);
        LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
    }
}
