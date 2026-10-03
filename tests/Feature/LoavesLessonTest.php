<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\LoavesLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LoavesLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_source_words_readings_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(LoavesLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/loaves-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('azure', $stage->config['theme']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, 'de')['content']);
            $text = $normalize(json_encode(array_map(fn ($b) => $b->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['slides'][$i]['slide'] as $line) {
                if ($line !== str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) {
                    $this->assertStringContainsString($normalize($line), $text);
                }
            }
            foreach ($stage->blocks as $block) {
                if (isset($block->media['image'])) {
                    $asset = $block->media['image']['assetId'];
                    $images[] = $asset;
                    $n = (int) str_replace('builtin-loaves-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/u-menya-slishkom-malo/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/loaves/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('Christus wirkt das Wunder', $plans['de']['plan']);
        $this->assertStringContainsString('Persönliches Blatt bleibt beim Jugendlichen', $plans['de']['plan']);
        preg_match_all('/\*\*Ин\. 6:(\d+)\.\*\*/u', $raw['bible'], $ruVerses);
        preg_match_all('/^Johannes 6,(\d+)\. /m', str_replace("\r\n", "\n", (require resource_path('content/loaves-de.php'))['bible']), $deVerses);
        $this->assertSame(array_map('strval', range(1, 15)), $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertCount(10, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/loaves-starters.php');
        $this->assertCount(70, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 70);
        $this->assertCount(24, require resource_path('content/loaves-cards.php'));
        foreach (require resource_path('content/loaves-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($plans['de']['plan']));
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($doc->stages[$card[4]]->content['de']['notes']));
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(LoavesLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 70);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_reading_roles_interactive_decisions_independent_plans_and_finish(string $locale): void
    {
        app(LoavesLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('u-menya-slishkom-malo', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $answer = function ($stage, $block, $value) use ($runtime, $participant, &$session): void {
            $runtime->answer($session['id'], $participant, $stage, $block, ['stageId' => $stage, 'blockId' => $block, 'value' => $value]);
        };
        $execute('begin');
        $answer('loaves-step-01', 'loaves-step-01-resource', ['optionId' => 'resource-2']);
        $execute('stage', ['stageId' => 'loaves-step-02']);
        foreach ([1 => [1, 5], 2 => [6, 10], 3 => [11, 15]] as $page => [$from, $to]) {
            $id = 'loaves-step-02-reading-'.$page;
            $this->assertSame('', $find($runtime->projector($token), $id)['content']['text']);
            $execute('presentation.toggle', ['blockId' => $id]);
            foreach (range($from, $to) as $verse) {
                $this->assertStringContainsString($locale === 'ru' ? 'Ин. 6:'.$verse.'.' : 'Johannes 6,'.$verse.'.', $find($runtime->projector($token), $id)['content']['text']);
            }
        }
        $execute('stage', ['stageId' => 'loaves-step-03']);
        $this->assertCount(6, $find($runtime->student($session['id'], $participant), 'loaves-step-03-roles')['content']['roles']);
        $execute('role.reveal.next', ['blockId' => 'loaves-step-03-roles']);
        $this->assertSame(['role-1'], $find($runtime->student($session['id'], $participant), 'loaves-step-03-roles')['runtime']['presentation']['revealedRoleIds']);
        $answer('loaves-step-03', 'loaves-step-03-roles', ['roleId' => 'role-1']);
        $execute('presentation.toggle', ['blockId' => 'loaves-step-03-frame-6']);
        $this->assertStringContainsString($locale === 'ru' ? 'Иисус отходит один' : 'Jesus geht allein weg', $find($runtime->projector($token), 'loaves-step-03-frame-6')['content']['text']);
        $execute('stage', ['stageId' => 'loaves-step-04']);
        $this->assertArrayNotHasKey('solution', $find($runtime->student($session['id'], $participant), 'loaves-step-04-numbers'));
        $answer('loaves-step-04', 'loaves-step-04-numbers', ['pairs' => array_map(fn ($j) => ['leftId' => 'number-'.$j, 'rightId' => 'meaning-'.$j], range(1, 3))]);
        $execute('presentation.toggle', ['blockId' => 'loaves-step-04-number-review']);
        $execute('stage', ['stageId' => 'loaves-step-05']);
        $answer('loaves-step-05', 'loaves-step-05-order', ['itemIds' => ['step-2', 'step-4', 'step-3', 'step-1']]);
        $execute('presentation.toggle', ['blockId' => 'loaves-step-05-check']);
        foreach (['6' => ['cases', ['case-1', 'case-4'], 'case-review'], '7' => ['phrases', ['phrase-2', 'phrase-4'], 'phrase-review']] as $n => [$suffix, $correct, $review]) {
            $stage = 'loaves-step-0'.$n;
            $execute('stage', ['stageId' => $stage]);
            $block = $find($runtime->student($session['id'], $participant), $stage.'-'.$suffix);
            $this->assertArrayNotHasKey('solution', $block);
            $this->assertGreaterThan(80, mb_strlen($block['content']['options'][0]['text']));
            $answer($stage, $stage.'-'.$suffix, ['optionIds' => $correct]);
            $execute('presentation.toggle', ['blockId' => $stage.'-'.$review]);
        }
        $execute('stage', ['stageId' => 'loaves-step-08']);
        $first = 'Ask Roman, show the room and offer a game.';
        $answer('loaves-step-08', 'loaves-step-08-first-plan', ['text' => $first]);
        $execute('stage', ['stageId' => 'loaves-step-09']);
        $this->assertStringContainsString($locale === 'ru' ? 'десять минут' : 'zehn Minuten', $find($runtime->student($session['id'], $participant), 'loaves-step-09-condition')['content']['text']);
        $second = 'Show the coat place; offer quiet drawing within ten minutes.';
        $answer('loaves-step-09', 'loaves-step-09-second-plan', ['text' => $second]);
        foreach (['loaves-step-08-first-plan' => $first, 'loaves-step-09-second-plan' => $second] as $id => $text) {
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
            $this->assertNull(collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id)['grade']);
        }
        $execute('stage', ['stageId' => 'loaves-step-10']);
        $answer('loaves-step-10', 'loaves-step-10-workshop', ['optionId' => 'meeting']);
        $execute('stage', ['stageId' => 'loaves-step-11']);
        $this->assertEmpty(array_filter($runtime->student($session['id'], $participant)['stage']['blocks'], fn ($b) => $b['type'] === 'core.free-response'));
        $execute('stage', ['stageId' => 'loaves-step-12']);
        foreach (['christ', 'step'] as $suffix) {
            $answer('loaves-step-12', 'loaves-step-12-'.$suffix, ['text' => 'Synthetic reflection '.$suffix]);
        }
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame('core.presentation', $runtime->student($session['id'], $participant)['closing']['type']);
    }
}
