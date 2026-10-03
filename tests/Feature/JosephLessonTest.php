<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\JosephLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JosephLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_source_words_readings_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(JosephLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/joseph-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('umber', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-joseph-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/kogda-nikto-ne-vidit/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/joseph/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('sofortige Belohnung', $plans['de']['plan']);
        $this->assertStringContainsString('Keine öffentlichen persönlichen Bekenntnisse', $plans['de']['plan']);
        preg_match_all('/\*\*Быт\. 39:(\d+)\.\*\*/u', $raw['bible'], $ruVerses);
        preg_match_all('/^Genesis 39,(\d+)\. /m', str_replace("\r\n", "\n", (require resource_path('content/joseph-de.php'))['bible']), $deVerses);
        $this->assertSame(array_map('strval', range(1, 23)), $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/joseph-starters.php');
        $this->assertCount(76, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 76);
        $this->assertCount(22, require resource_path('content/joseph-cards.php'));
        foreach (require resource_path('content/joseph-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($plans['de']['plan']));
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($doc->stages[$card[4]]->content['de']['notes']));
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(JosephLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 76);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_full_reading_four_roles_true_prison_ending_and_independent_plans(string $locale): void
    {
        app(JosephLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('kogda-nikto-ne-vidit', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'joseph-step-02']);
        foreach ([1 => [1, 6], 2 => [7, 12], 3 => [13, 18], 4 => [19, 23]] as $page => [$from, $to]) {
            $id = 'joseph-step-02-reading-'.$page;
            $this->assertSame('', $find($runtime->projector($token), $id)['content']['text']);
            $execute('presentation.toggle', ['blockId' => $id]);
            $reading = $find($runtime->projector($token), $id)['content']['text'];
            foreach (range($from, $to) as $verse) {
                $this->assertStringContainsString($locale === 'ru' ? 'Быт. 39:'.$verse.'.' : 'Genesis 39,'.$verse.'.', $reading);
            }
        }
        $execute('stage', ['stageId' => 'joseph-step-03']);
        $roles = $find($runtime->student($session['id'], $participant), 'joseph-step-03-roles');
        $this->assertCount(4, $roles['content']['roles']);
        $this->assertEmpty($roles['runtime']['presentation']['revealedRoleIds'] ?? []);
        $execute('role.reveal.next', ['blockId' => 'joseph-step-03-roles']);
        $this->assertSame(['role-1'], $find($runtime->student($session['id'], $participant), 'joseph-step-03-roles')['runtime']['presentation']['revealedRoleIds']);
        $runtime->answer($session['id'], $participant, 'joseph-step-03', 'joseph-step-03-roles', ['stageId' => 'joseph-step-03', 'blockId' => 'joseph-step-03-roles', 'value' => ['roleId' => 'role-1']]);
        $fragments = $locale === 'ru' ? ['не означают', 'согрешу пред Богом', 'никого из домашних', 'противоречит', 'не изображает признание', 'Только после этого'] : ['bedeuten nicht', 'gegen Gott sündigen', 'niemand aus dem Haus', 'widerspricht', 'kein Eingeständnis erfundener Schuld', 'Erst danach'];
        foreach (range(1, 6) as $j) {
            $id = 'joseph-step-03-frame-'.$j;
            $this->assertSame('', $find($runtime->projector($token), $id)['content']['text']);
            $execute('presentation.toggle', ['blockId' => $id]);
            $text = $find($runtime->projector($token), $id)['content']['text'];
            $this->assertStringContainsString($fragments[$j - 1], $text);
            if ($j === 6) {
                $this->assertStringContainsString($locale === 'ru' ? 'ст. 21–23' : 'Verse 21–23', $text);
            }
        }
        $execute('stage', ['stageId' => 'joseph-step-05']);
        $order = $find($runtime->student($session['id'], $participant), 'joseph-step-05-order');
        $this->assertArrayNotHasKey('solution', $order);
        $this->assertSame(['step-1', 'step-2', 'step-3', 'step-4'], array_column($order['content']['items'], 'itemId'));
        $runtime->answer($session['id'], $participant, 'joseph-step-05', 'joseph-step-05-order', ['stageId' => 'joseph-step-05', 'blockId' => 'joseph-step-05-order', 'value' => ['itemIds' => ['step-2', 'step-4', 'step-1', 'step-3']]]);
        $execute('presentation.toggle', ['blockId' => 'joseph-step-05-check']);
        $this->assertStringContainsString($locale === 'ru' ? 'Доверенный дом → Отказ и уход → Ложное обвинение → Верность в темнице' : 'Das anvertraute Haus → Weigerung und Weggehen → Falsche Beschuldigung → Treue im Gefängnis', $find($runtime->projector($token), 'joseph-step-05-check')['content']['text']);
        foreach (['choice' => 'Josef refused and left.', 'accusation' => 'The false accusation led to prison.'] as $suffix => $text) {
            $id = 'joseph-step-05-'.$suffix;
            $runtime->answer($session['id'], $participant, 'joseph-step-05', $id, ['stageId' => 'joseph-step-05', 'blockId' => $id, 'value' => ['text' => $text]]);
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('stage', ['stageId' => 'joseph-step-06']);
        $this->assertCount(6, array_filter($runtime->student($session['id'], $participant)['stage']['blocks'], fn ($b) => $b['type'] === 'core.presentation' && $b['config']['kind'] === 'reveal'));
        $execute('stage', ['stageId' => 'joseph-step-07']);
        $phrases = array_filter($runtime->projector($token)['stage']['blocks'], fn ($b) => $b['type'] === 'core.presentation' && $b['config']['kind'] === 'reveal');
        $this->assertCount(4, $phrases);
        $execute('presentation.toggle', ['blockId' => 'joseph-step-07-phrase-4']);
        $this->assertStringContainsString($locale === 'ru' ? 'ложное обвинение' : 'falscher Beschuldigung', $find($runtime->projector($token), 'joseph-step-07-phrase-4')['content']['text']);
        $execute('stage', ['stageId' => 'joseph-step-08']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['сама плохо склеила', 'selbst schlecht geklebt', 'Учитель рядом', 'Lehrkraft ist in Nähe'] as $private) {
                $this->assertStringNotContainsString($private, $public);
            }
        }
        $first = 'Tell Mascha what I did, ask permission to repair, request help.';
        $runtime->answer($session['id'], $participant, 'joseph-step-08', 'joseph-step-08-first-plan', ['stageId' => 'joseph-step-08', 'blockId' => 'joseph-step-08-first-plan', 'value' => ['text' => $first]]);
        $execute('stage', ['stageId' => 'joseph-step-09']);
        $condition = $find($runtime->projector($token), 'joseph-step-09-condition')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'сама плохо склеила' : 'selbst schlecht geklebt', $condition);
        $second = 'Refuse blaming Mascha; admit my own action and ask repair help.';
        $runtime->answer($session['id'], $participant, 'joseph-step-09', 'joseph-step-09-second-plan', ['stageId' => 'joseph-step-09', 'blockId' => 'joseph-step-09-second-plan', 'value' => ['text' => $second]]);
        foreach (['joseph-step-08-first-plan' => $first, 'joseph-step-09-second-plan' => $second] as $id => $text) {
            $answer = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $answer['value']['text']);
            $this->assertNull(collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id)['grade']);
        }
        $execute('stage', ['stageId' => 'joseph-step-11']);
        $this->assertEmpty(array_filter($runtime->student($session['id'], $participant)['stage']['blocks'], fn ($b) => $b['type'] === 'core.free-response'));
        $execute('stage', ['stageId' => 'joseph-step-12']);
        foreach (['refusal', 'step'] as $suffix) {
            $runtime->answer($session['id'], $participant, 'joseph-step-12', 'joseph-step-12-'.$suffix, ['stageId' => 'joseph-step-12', 'blockId' => 'joseph-step-12-'.$suffix, 'value' => ['text' => 'Synthetic reflection '.$suffix]]);
        }
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame('core.presentation', $runtime->student($session['id'], $participant)['closing']['type']);
    }
}
