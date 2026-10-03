<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\ForgivenessLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ForgivenessLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_screens_full_plan_images_downloads_and_reusable_blocks_are_preserved(): void
    {
        $result = app(ForgivenessLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/forgiveness-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);

        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString($raw['screens'][$i]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('copper', $stage->config['theme']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, 'de')['content']);
            $text = $normalize(json_encode(array_map(fn ($b) => $b->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['screens'][$i]['slides'] as $number) {
                if ($number === 1) {
                    $this->assertStringContainsString($raw['title'], $text);

                    continue;
                }
                foreach ($raw['slides'][$number - 1]['slide'] as $line) {
                    if ($line !== str_pad((string) $number, 2, '0', STR_PAD_LEFT)) {
                        $this->assertStringContainsString($normalize($line), $text);
                    }
                }
            }
            foreach ($stage->blocks as $block) {
                if (isset($block->media['image'])) {
                    $asset = $block->media['image']['assetId'];
                    $images[] = $asset;
                    $n = (int) str_replace('builtin-forgiveness-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/ya-ne-hochu-proshchat/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/forgiveness/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('35 Так и Отец Мой Небесный', $plans['ru']['plan']);
        $this->assertStringContainsString('35 So wird auch Mein himmlischer Vater', $plans['de']['plan']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $this->assertDatabaseCount('common_templates', count((require resource_path('content/forgiveness-starters.php'))['templates']));
        $this->assertCount(51, (require resource_path('content/forgiveness-starters.php'))['templates']);
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(ForgivenessLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', count((require resource_path('content/forgiveness-starters.php'))['templates']));
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_staged_story_private_team_conditions_revision_and_final_answers(string $locale): void
    {
        app(ForgivenessLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('ya-ne-hochu-proshchat', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'forgiveness-step-02']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $this->assertStringNotContainsString('Материалы учителю', json_encode($dto, JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString('35 Так и Отец', json_encode($dto, JSON_UNESCAPED_UNICODE));
        }
        $teacher = $runtime->teacher($owner, $session['id']);
        $this->assertStringContainsString($locale === 'ru' ? '35 Так и Отец' : '35 So wird auch Mein', collect($teacher['document']['stages'])->firstWhere('id', 'forgiveness-step-02')['content']['notes']);
        $execute('stage', ['stageId' => 'forgiveness-step-03']);
        $this->assertCount(5, $find($runtime->student($session['id'], $participant), 'forgiveness-step-03-roles')['content']['roles']);
        foreach ([14, 4, 5, 13] as $number) {
            $id = 'forgiveness-step-03-frame-'.$number;
            $this->assertEmpty($find($runtime->student($session['id'], $participant), $id)['media']);
            $execute('presentation.toggle', ['blockId' => $id]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $this->assertSame('builtin-forgiveness-'.$number, $find($dto, $id)['media']['image']['assetId']);
            }
        }
        $last = $find($runtime->projector($token), 'forgiveness-step-03-frame-13');
        $this->assertStringContainsString($locale === 'ru' ? '35 Так и Отец' : '35 So wird auch Mein', $last['content']['text']);
        $execute('stage', ['stageId' => 'forgiveness-step-04']);
        $this->assertArrayNotHasKey('table', $find($runtime->projector($token), 'forgiveness-step-04-debts')['content']);
        $execute('presentation.toggle', ['blockId' => 'forgiveness-step-04-debts']);
        $table = $find($runtime->projector($token), 'forgiveness-step-04-debts')['content']['table'];
        $this->assertSame('10 000 '.($locale === 'ru' ? 'талантов' : 'Talente'), $table['rows'][0][1]);
        $this->assertSame('100 '.($locale === 'ru' ? 'динариев' : 'Denare'), $table['rows'][1][1]);
        $this->assertArrayNotHasKey('solution', $find($runtime->student($session['id'], $participant), 'forgiveness-step-04-order'));
        $runtime->answer($session['id'], $participant, 'forgiveness-step-04', 'forgiveness-step-04-order', ['stageId' => 'forgiveness-step-04', 'blockId' => 'forgiveness-step-04-order', 'value' => ['itemIds' => ['event-4', 'event-2', 'event-1', 'event-3']]]);
        $execute('stage', ['stageId' => 'forgiveness-step-07']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['Коля лично извинился, но запись пока не убрал.', 'Kolja entschuldigte sich persönlich, löschte den Beitrag aber noch nicht.', 'Мне важно, чтобы это перестали пересылать', 'Mir ist wichtig, dass das nicht mehr weitergeschickt wird', 'насмешки повторяются.', 'Der Spott wiederholt sich.'] as $hidden) {
                $this->assertStringNotContainsString($hidden, $public);
            }
        }
        $execute('presentation.toggle', ['blockId' => 'forgiveness-step-07-sonya']);
        $this->assertStringContainsString($locale === 'ru' ? 'перестали пересылать' : 'nicht mehr weitergeschickt', $find($runtime->student($session['id'], $participant), 'forgiveness-step-07-sonya')['content']['text']);
        $execute('stage', ['stageId' => 'forgiveness-step-08']);
        $runtime->answer($session['id'], $participant, 'forgiveness-step-08', 'forgiveness-step-08-request', ['stageId' => 'forgiveness-step-08', 'blockId' => 'forgiveness-step-08-request', 'value' => ['text' => 'Please stop mocking my answer.']]);
        $execute('stage', ['stageId' => 'forgiveness-step-09']);
        $public = json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString($locale === 'ru' ? 'насмешки повторяются.' : 'Der Spott wiederholt sich.', $public);
        $runtime->answer($session['id'], $participant, 'forgiveness-step-09', 'forgiveness-step-09-boundary', ['stageId' => 'forgiveness-step-09', 'blockId' => 'forgiveness-step-09-boundary', 'value' => ['text' => 'I will leave and ask a teacher for help.']]);
        $teacherAnswers = $runtime->teacher($owner, $session['id'])['answers'];
        foreach (['forgiveness-step-08-request', 'forgiveness-step-09-boundary'] as $id) {
            $answer = collect($teacherAnswers)->firstWhere('blockId', $id);
            $this->assertNotNull($answer);
            $this->assertNull($answer['grade']);
        }
        foreach (['forgiveness-step-10', 'forgiveness-step-11'] as $id) {
            $execute('stage', ['stageId' => $id]);
            $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        }
        $execute('stage', ['stageId' => 'forgiveness-step-12']);
        $this->assertNotSame('finished', $runtime->student($session['id'], $participant)['status']);
        foreach (['forgiveness-step-12-parable' => 'The king forgave the whole debt.', 'forgiveness-step-12-step' => 'I will ask for help without revenge.'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'forgiveness-step-12', $id, ['stageId' => 'forgiveness-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $own['value']['text']);
            $this->assertNull($own['grade']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('forgiveness-step-12-closing', $finished['closing']['id']);
    }
}
