<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\ChoicesLessonInstaller;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ChoicesLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_screens_full_plan_images_downloads_and_reusable_blocks_are_preserved(): void
    {
        $result = app(ChoicesLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/choices-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);

        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString($raw['screens'][$i]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('plum', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-choices-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/ya-imeyu-pravo-vybirat/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/choices/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('32 а о том надобно было радоваться', $plans['ru']['plan']);
        $this->assertStringContainsString('32 Aber wir mussten uns freuen', $plans['de']['plan']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $this->assertDatabaseCount('common_templates', count((require resource_path('content/choices-starters.php'))['templates']));
        $this->assertCount(40, (require resource_path('content/choices-starters.php'))['templates']);
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(ChoicesLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', count((require resource_path('content/choices-starters.php'))['templates']));
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_open_ending_private_conditions_manual_repair_and_independent_final_answers(string $locale): void
    {
        app(ChoicesLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('ya-imeyu-pravo-vybirat', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'choices-step-03']);
        $this->assertCount(5, $find($runtime->student($session['id'], $participant), 'choices-step-03-roles')['content']['roles']);
        $execute('stage', ['stageId' => 'choices-step-04']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $this->assertArrayNotHasKey('table', $find($dto, 'choices-step-04-review')['content']);
        }
        $execute('presentation.toggle', ['blockId' => 'choices-step-04-review']);
        $this->assertCount(6, $find($runtime->student($session['id'], $participant), 'choices-step-04-review')['content']['table']['rows']);
        $execute('stage', ['stageId' => 'choices-step-05']);
        $this->assertEmpty($find($runtime->student($session['id'], $participant), 'choices-step-05-frame-6')['media']);
        $execute('presentation.toggle', ['blockId' => 'choices-step-05-frame-6']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $this->assertSame('builtin-choices-3', $find($dto, 'choices-step-05-frame-6')['media']['image']['assetId']);
        }
        $execute('stage', ['stageId' => 'choices-step-06']);
        $public = json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE);
        foreach (['18:20', '19:10', 'Передача и сообщение команде займут 5 минут.', 'Übergabe und Nachricht dauern fünf Minuten.'] as $secret) {
            $this->assertStringNotContainsString($secret, $public);
        }
        $execute('stage', ['stageId' => 'choices-step-07']);
        $public = json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('18:20', $public);
        $this->assertStringContainsString('19:10', $public);
        $execute('stage', ['stageId' => 'choices-step-09']);
        $sequence = $find($runtime->student($session['id'], $participant), 'choices-step-09-repair');
        $this->assertArrayNotHasKey('solution', $sequence);
        foreach ([['step-2', 'step-4', 'step-1', 'step-3'], ['step-4', 'step-2', 'step-1', 'step-3']] as $order) {
            $runtime->answer($session['id'], $participant, 'choices-step-09', 'choices-step-09-repair', ['stageId' => 'choices-step-09', 'blockId' => 'choices-step-09-repair', 'value' => ['itemIds' => $order]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', 'choices-step-09-repair');
            $this->assertSame($order, $own['value']['itemIds']);
            $this->assertNull($own['grade']);
            $teacherAnswer = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', 'choices-step-09-repair');
            $this->assertNull($teacherAnswer['grade']);
        }
        $execute('stage', ['stageId' => 'choices-step-11']);
        $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        $execute('stage', ['stageId' => 'choices-step-12']);
        foreach (['choices-step-12-choice' => 'Уход и голод', 'choices-step-12-question' => 'Кого затронет мой выбор?'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'choices-step-12', $id, ['stageId' => 'choices-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $own['value']['text']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('choices-step-12-closing', $finished['closing']['id']);
    }
}
