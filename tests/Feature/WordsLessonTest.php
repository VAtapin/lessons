<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\CommonTemplateService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\WordsLessonInstaller;
use App\Application\Runtime\RuntimeConflict;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\MediaCatalogue;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\CatalogEntry;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class WordsLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_pack_installs_once_with_exact_russian_source_sixteen_stages_both_languages_files_and_shared_blocks(): void
    {
        $this->artisan('lessons:install-words')->expectsOutput('Installed: slova-ranyat-slova-lechat')->expectsOutput('Reusable blocks: 17')->assertSuccessful();
        $entry = CatalogEntry::firstOrFail();
        $before = $entry->version->getAttributes();
        $this->artisan('lessons:install-words')->expectsOutput('Already installed: slova-ranyat-slova-lechat')->expectsOutput('Reusable blocks: 17')->assertSuccessful();
        $this->assertSame($before, $entry->version->fresh()->getAttributes());
        $this->assertDatabaseCount('catalog_entries', 1);
        $this->assertDatabaseCount('common_templates', 17);
        $doc = LessonDocument::fromArray($entry->version->document, app(BlockRegistry::class));
        $this->assertCount(16, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($stage) => $stage->config['durationSeconds'], $doc->stages)));
        $raw = json_decode(file_get_contents(resource_path('content/slova-ranyat-source.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($doc->stages as $i => $stage) {
            $screen = $raw['screens'][$i];
            $this->assertSame($screen['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString($screen['teacher_actions'], $stage->content['ru']['notes']);
            if ($screen['type'] === 'poll_series') {
                $polls = array_values(array_filter($stage->blocks, fn ($block) => $block->type === 'core.poll'));
                $this->assertSame(explode("\n", $screen['screen_text']), array_map(fn ($block) => $block->content['ru']['question'], $polls));
            } else {
                $this->assertSame($screen['screen_text'], $stage->blocks[0]->content['ru']['text']);
            }
        }
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $this->assertNull($doc->stages[8]->blocks[1]->solution);
        $media = array_filter(app(MediaCatalogue::class)->all(), fn ($item) => str_starts_with($item['assetId'], 'builtin-words-'));
        $this->assertCount(8, $media);
        foreach (['ru', 'de'] as $locale) {
            $detail = app(CatalogService::class)->detail($entry->slug, $locale);
            $this->assertNotEmpty($detail);
            $templates = app(CommonTemplateService::class)->listing($locale);
            $this->assertCount(17, $templates);
        }
        $entry->update(['status' => 'hidden']);
        app(WordsLessonInstaller::class)->install();
        $this->assertSame('hidden', $entry->fresh()->status);
    }

    public function test_receipt_drift_rolls_back_without_overwriting_published_resources(): void
    {
        $entry = app(WordsLessonInstaller::class)->install()['entry'];
        $before = $entry->version->getAttributes();
        DB::table('catalog_entries')->where('id', $entry->id)->update(['source_hash' => str_repeat('0', 64)]);
        try {
            app(WordsLessonInstaller::class)->install();
            $this->fail('Drift must fail closed.');
        } catch (RuntimeException) {
            $this->assertSame($before, $entry->version->fresh()->getAttributes());
            $this->assertDatabaseCount('common_templates', 17);
        }
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    public function test_sequential_setting_also_keeps_legacy_default_open_choices_prepared(): void
    {
        app(WordsLessonInstaller::class)->install();
        $owner = (string) Str::uuid();
        $state = app(CatalogService::class)->use('slova-ranyat-slova-lechat', 'ru', $owner, true)['session'];
        $session = TeachingSession::findOrFail($state['id']);
        $document = $session->version->document;
        foreach ($document['stages'][2]['blocks'] as &$block) {
            if ($block['type'] === 'core.poll') {
                $block['type'] = 'core.single-choice';
            }
        }
        unset($block);
        DB::table('lesson_versions')->where('id', $session->lesson_version_id)->update(['document' => json_encode($document)]);
        $runtime = app(RuntimeService::class);
        $participant = $runtime->join($state['joinCode'], 'Synthetic student', [])['participant']['id'];
        $state = $runtime->command($owner, $state['id'], (string) Str::uuid(), $state['revision'], 'begin', [])['session'];
        $state = $runtime->command($owner, $state['id'], (string) Str::uuid(), $state['revision'], 'stage', ['stageId' => 'words-step-03'])['session'];
        $this->assertSame('prepared', collect($state['blockStates'])->firstWhere('blockId', 'words-step-03-phrase-2')['status']);
        $this->assertNotContains('words-step-03-phrase-2', array_column($runtime->student($state['id'], $participant)['stage']['blocks'], 'id'));
        $this->expectException(ApiProblem::class);
        $runtime->answer($state['id'], $participant, 'words-step-03', 'words-step-03-phrase-2', ['stageId' => 'words-step-03', 'blockId' => 'words-step-03-phrase-2', 'value' => ['optionId' => 'send']]);
    }

    #[DataProvider('locales')]
    public function test_full_lesson_keeps_sequential_phrases_timer_limits_moderation_and_personal_choice_private(string $locale): void
    {
        app(WordsLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('slova-ranyat-slova-lechat', $locale, $owner, true)['session'];
        foreach ($session['document']['documentation']['files'] as $index => $file) {
            $this->assertSame($locale, $file['locale']);
            $manifest = app(DocumentationFiles::class)->resolve($file['fileId']);
            $this->assertSame(__('studio.'.$manifest['labelKey'], [], $locale), $file['label']);
            $this->assertSame('/lesson-files/'.$file['fileId'], $file['url']);
        }
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $participant = $runtime->join($session['joinCode'], 'Synthetic student', [])['participant']['id'];
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $execute('begin');
        $source = (static fn () => require resource_path('content/slova-ranyat.php'))();
        foreach ($source['document']['stages'] as $i => $stage) {
            if ($session['currentStageId'] !== $stage['id']) {
                $execute('stage', ['stageId' => $stage['id']]);
            }
            $public = $runtime->projector($token);
            $this->assertStringNotContainsString('teacher_actions', json_encode($public));
            $this->assertArrayNotHasKey('notes', $public['stage']['content']);
            foreach ($public['stage']['blocks'] as $block) {
                $this->assertArrayNotHasKey('teacherNotes', $block);
                $this->assertArrayNotHasKey('solution', $block);
            }
            $tasks = array_values(array_filter($stage['blocks'], fn ($block) => ! in_array($block['type'], ['core.image', 'core.prompt'], true) && ($block['type'] !== 'core.presentation' || $block['config']['kind'] === 'personal-choice')));
            foreach ($tasks as $taskIndex => $block) {
                if (($stage['config']['sequentialTasks'] ?? false) && $taskIndex === 0) {
                    $this->assertNotContains($tasks[1]['id'], array_column($public['stage']['blocks'], 'id'));
                    try {
                        $execute('block.open', ['blockId' => $tasks[1]['id']]);
                        $this->fail('Cannot skip first phrase.');
                    } catch (ApiProblem|RuntimeConflict $problem) {
                        $this->assertSame('invalid_state', $problem->problemCode);
                    }
                }
                if (($stage['config']['sequentialTasks'] ?? false) && $taskIndex > 0) {
                    $execute('block.open', ['blockId' => $block['id']]);
                }
                $value = match ($block['type']) {
                    'core.poll' => ['optionId' => $block['content'][$locale]['options'][0]['optionId']],
                    'core.multiple-choice' => ['optionIds' => ['anger', 'rush']],
                    'core.free-response' => ['text' => 'Private reply '.$locale],
                    'core.presentation' => ['modeId' => 'apology'],
                    'core.signals' => ['ready' => true, 'question' => false],
                    default => $block['solution'],
                };
                $answer = fn ($value) => $runtime->answer($session['id'], $participant, $stage['id'], $block['id'], ['stageId' => $stage['id'], 'blockId' => $block['id'], 'value' => $value]);
                $student = $answer($value);
                $saved = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $block['id']);
                $this->assertSame($value, $saved['value']);
                if ($block['type'] === 'core.multiple-choice') {
                    try {
                        $answer(['optionIds' => ['anger', 'rush', 'joke']]);
                        $this->fail('Maximum two reasons.');
                    } catch (ApiProblem $problem) {
                        $this->assertSame('invalid_action', $problem->problemCode);
                    }
                }
                if ($block['type'] === 'core.free-response') {
                    $this->assertStringNotContainsString('Private reply', json_encode($runtime->projector($token)));
                    $row = SessionAnswer::query()->where('teaching_session_id', $session['id'])->where('block_id', $block['id'])->firstOrFail();
                    $execute('answer.moderate', ['answerId' => $row->id, 'expectedAnswerRevision' => $row->revision, 'status' => 'approved', 'displayText' => 'Reviewed reply']);
                    $execute('answer.publish', ['answerId' => $row->id, 'expectedAnswerRevision' => $row->fresh()->revision]);
                    $this->assertStringContainsString('Reviewed reply', json_encode($runtime->projector($token)));
                }
                if ($block['type'] === 'core.presentation') {
                    $projected = collect($runtime->projector($token)['stage']['blocks'])->firstWhere('id', $block['id']);
                    $this->assertArrayNotHasKey('summary', $projected['runtime']);
                    $this->assertArrayNotHasKey('results', $projected['runtime']);
                    $this->assertArrayNotHasKey('presentation', $projected['runtime']);

                    continue;
                }
                if (isset($stage['config']['answerSeconds'])) {
                    $execute('timer.start', ['seconds' => 1]);
                    $this->travel(2)->seconds();
                    $projected = collect($runtime->projector($token)['stage']['blocks'])->firstWhere('id', $block['id']);
                    $this->assertSame('closed', $projected['runtime']['status'], $block['id'].json_encode($runtime->projector($token)['timer']));
                    try {
                        $answer($value);
                        $this->fail('Expired timer closes submissions.');
                    } catch (ApiProblem $problem) {
                        $this->assertSame('invalid_state', $problem->problemCode);
                    }
                    $execute('timer.clear');
                    try {
                        $answer($value);
                        $this->fail('Clearing the timer must not reopen expired submissions.');
                    } catch (ApiProblem $problem) {
                        $this->assertSame('invalid_state', $problem->problemCode);
                    }
                }
                $execute('block.review', ['blockId' => $block['id']]);
                if (($stage['config']['sequentialTasks'] ?? false) && $taskIndex === 0) {
                    $execute('timer.start', ['seconds' => 1]);
                    $this->travel(2)->seconds();
                }
                if ($block['type'] === 'core.multiple-choice') {
                    $projected = collect($runtime->projector($token)['stage']['blocks'])->firstWhere('id', $block['id']);
                    $this->assertSame(1, $projected['runtime']['results']['totalAnswers']);
                    $this->assertNull(collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $block['id'])['grade']);
                }
            }
        }
        $this->assertNotNull($runtime->teacher($owner, $session['id']));
        $execute('finish');
        $this->assertSame($source['document']['stages'][15]['content'][$locale]['title'], $runtime->student($session['id'], $participant)['closing']['content']['title']);
    }
}
