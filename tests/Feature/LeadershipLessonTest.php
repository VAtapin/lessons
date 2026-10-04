<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\LeadershipLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\CatalogEntry;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LeadershipLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_reading_downloads_starters_and_repeat_installation(): void
    {
        $result = app(LeadershipLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/leadership-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        foreach ($doc->stages as $i => $stage) {
            $this->assertStringContainsString($raw['ru']['stages'][$i]['notes'], $stage->content['ru']['notes']);
            foreach (array_slice($raw['ru']['slides'][$i], 0, -1) as $line) {
                $this->assertStringContainsString($line, $stage->blocks[0]->content['ru']['text']);
            }
            foreach (['ru', 'de'] as $locale) {
                $this->assertNotEmpty($stage->content[$locale]['notes']);
                $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, $locale)['content']);
            }
        }
        foreach (['ru' => 4, 'de' => 5] as $locale => $count) {
            preg_match_all('/^(\d+)\. /m', $raw[$locale]['bible'], $matches);
            $this->assertStringContainsString($raw[$locale]['bible'], $doc->stages[5]->content[$locale]['notes']);
            $this->assertSame(range(1, 17), array_map('intval', $matches[1]));
            $files = app(DocumentationFiles::class)->localizedReferences($doc->documentation, $locale);
            $this->assertCount($count, $files);
            foreach ($files as $reference) {
                $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
                $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
            }
        }
        $this->assertStringContainsString('не имеешь части со Мною', $raw['ru']['bible']);
        $this->assertStringContainsString('keinen Anteil an Mir', $raw['de']['bible']);
        $this->assertStringContainsString('Учителем и Господом', $raw['ru']['bible']);
        $this->assertStringContainsString('Lehrer und Herr', $raw['de']['bible']);
        $this->assertSame($doc->stages[1]->blocks[2]->content, $doc->stages[11]->blocks[2]->content);
        $this->assertFalse(app(LeadershipLessonInstaller::class)->install()['created']);
        $this->assertSame(48, $result['templates']['total']);
        $this->assertSame(180, $doc->stages[2]->config['answerSeconds']);
        $this->assertSame(90, $doc->stages[3]->config['answerSeconds']);
        $this->assertSame(60, $doc->stages[9]->config['answerSeconds']);
        $this->assertSame(1, CatalogEntry::query()->where('slug', 'kto-samyi-glavnyi')->count());
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_runtime_hides_surprise_preserves_polls_plans_and_private_notes(string $locale): void
    {
        app(LeadershipLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('kto-samyi-glavnyi', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $answer = function ($stage, $suffix, $value) use ($runtime, &$session, $participant): void {
            $stageId = 'leadership-step-'.sprintf('%02d', $stage);
            $id = $stageId.'-'.$suffix;
            $runtime->answer($session['id'], $participant, $stageId, $id, ['stageId' => $stageId, 'blockId' => $id, 'value' => $value]);
        };
        $execute('begin');
        $execute('stage', ['stageId' => 'leadership-step-02']);
        $answer(2, 'leader', ['optionId' => 'choice-1']);
        $this->assertArrayNotHasKey('results', $find($runtime->projector($token), 'leadership-step-02-leader')['runtime']);
        $this->assertArrayNotHasKey('summary', $find($runtime->projector($token), 'leadership-step-02-leader')['runtime']);
        $execute('block.review', ['blockId' => 'leadership-step-02-leader']);
        $this->assertSame(1, $find($runtime->projector($token), 'leadership-step-02-leader')['runtime']['results']['totalAnswers']);
        $this->assertSame('', $find($runtime->projector($token), 'leadership-step-02-votes')['content']['text']);
        $execute('presentation.toggle', ['blockId' => 'leadership-step-02-votes']);
        $this->assertNotEmpty($find($runtime->projector($token), 'leadership-step-02-votes')['content']['text']);
        $execute('stage', ['stageId' => 'leadership-step-03']);
        foreach ([$runtime->projector($token), $runtime->student($session['id'], $participant)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString($locale === 'ru' ? 'две фишки' : 'zwei Spielsteine', $public);
            $this->assertArrayNotHasKey('notes', $dto['stage']['content']);
        }
        $execute('stage', ['stageId' => 'leadership-step-04']);
        $this->assertStringContainsString($locale === 'ru' ? 'две фишки' : 'zwei Steine', $find($runtime->student($session['id'], $participant), 'leadership-step-04-rules')['content']['text']);
        $execute('stage', ['stageId' => 'leadership-step-06']);
        $this->assertStringNotContainsString($locale === 'ru' ? 'не имеешь части со Мною' : 'keinen Anteil an Mir', json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString($locale === 'ru' ? 'не имеешь части со Мною' : 'keinen Anteil an Mir', $runtime->teacher($owner, $session['id'])['document']['stages'][5]['content']['notes']);
        $execute('stage', ['stageId' => 'leadership-step-09']);
        $this->assertSame('', $find($runtime->projector($token), 'leadership-step-09-situation-1')['content']['text']);
        $execute('presentation.toggle', ['blockId' => 'leadership-step-09-situation-1']);
        $this->assertNotEmpty($find($runtime->projector($token), 'leadership-step-09-situation-1')['content']['text']);
        $answer(9, 'first-plan', ['text' => 'First plan']);
        $execute('stage', ['stageId' => 'leadership-step-10']);
        $answer(10, 'second-plan', ['text' => 'Second plan']);
        foreach ([9 => 'first-plan', 10 => 'second-plan'] as $stage => $suffix) {
            $id = 'leadership-step-'.sprintf('%02d', $stage).'-'.$suffix;
            $saved = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id);
            $this->assertSame($stage === 9 ? 'First plan' : 'Second plan', $saved['value']['text']);
            $this->assertNull($saved['grade']);
        }
        $execute('stage', ['stageId' => 'leadership-step-12']);
        $answer(12, 'leader', ['optionId' => 'choice-4']);
        $answer(12, 'christ', ['text' => 'Christ loves and serves']);
        $answer(12, 'service', ['text' => 'Share work and help']);
        $this->assertSame('running', $runtime->student($session['id'], $participant)['status']);
        $answers = collect($runtime->teacher($owner, $session['id'])['answers']);
        $this->assertSame('choice-1', $answers->firstWhere('blockId', 'leadership-step-02-leader')['value']['optionId']);
        $this->assertSame('choice-4', $answers->firstWhere('blockId', 'leadership-step-12-leader')['value']['optionId']);
        $this->assertCount(2, array_filter($runtime->student($session['id'], $participant)['stage']['blocks'], fn ($b) => $b['type'] === 'core.free-response'));
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame('leadership-step-12-closing', $runtime->student($session['id'], $participant)['closing']['id']);
    }
}
