<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\VineyardLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class VineyardLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_source_words_readings_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(VineyardLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/vineyard-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('wine', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-vineyard-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/pochemu-emu-bolshe-chem-mne/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/vineyard/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('Keine universelle Lohn-', $plans['de']['plan']);
        $this->assertStringContainsString('Keine sofortige Freude', $plans['de']['plan']);
        preg_match_all('/\*\*Мф\. 20:(\d+)\.\*\*/u', $raw['bible'], $ruVerses);
        preg_match_all('/^Matthäus 20,(\d+)\. /m', str_replace("\r\n", "\n", (require resource_path('content/vineyard-de.php'))['bible']), $deVerses);
        $this->assertSame(array_map('strval', range(1, 16)), $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/vineyard-starters.php');
        $this->assertCount(68, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 68);
        $this->assertCount(21, require resource_path('content/vineyard-cards.php'));
        foreach (require resource_path('content/vineyard-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($plans['de']['plan']));
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($doc->stages[$card[4]]->content['de']['notes']));
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(VineyardLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 68);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_full_reading_seven_roles_five_invitations_equal_payment_and_independent_plans(string $locale): void
    {
        app(VineyardLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('pochemu-emu-bolshe-chem-mne', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'vineyard-step-02']);
        foreach ([1 => [1, 8], 2 => [9, 16]] as $page => [$from, $to]) {
            $id = 'vineyard-step-02-reading-'.$page;
            $this->assertSame('', $find($runtime->projector($token), $id)['content']['text']);
            $execute('presentation.toggle', ['blockId' => $id]);
            $reading = $find($runtime->projector($token), $id)['content']['text'];
            foreach (range($from, $to) as $verse) {
                $this->assertStringContainsString($locale === 'ru' ? 'Мф. 20:'.$verse.'.' : 'Matthäus 20,'.$verse.'.', $reading);
            }
        }
        $execute('stage', ['stageId' => 'vineyard-step-03']);
        $roles = $find($runtime->student($session['id'], $participant), 'vineyard-step-03-roles');
        $this->assertCount(7, $roles['content']['roles']);
        $this->assertEmpty($roles['runtime']['presentation']['revealedRoleIds'] ?? []);
        $execute('role.reveal.next', ['blockId' => 'vineyard-step-03-roles']);
        $this->assertSame(['role-1'], $find($runtime->student($session['id'], $participant), 'vineyard-step-03-roles')['runtime']['presentation']['revealedRoleIds']);
        $runtime->answer($session['id'], $participant, 'vineyard-step-03', 'vineyard-step-03-roles', ['stageId' => 'vineyard-step-03', 'blockId' => 'vineyard-step-03-roles', 'value' => ['roleId' => 'role-1']]);
        $fragments = $locale === 'ru' ? ['пять', 'шестой час', 'никто нас не нанял', 'одиннадцатый, девятый, шестой, третий, раннее утро', 'не за динарий ли', 'только теперь обсуждение'] : ['Gesamtzahl nicht zählen', 'sechste', 'Niemand hat uns eingestellt', 'elfte, neunte, sechste, dritte Stunde, früher Morgen', 'einen Denar mit mir vereinbart', 'erst jetzt besprechen'];
        foreach (range(1, 6) as $j) {
            $id = 'vineyard-step-03-frame-'.$j;
            $this->assertSame('', $find($runtime->projector($token), $id)['content']['text']);
            $execute('presentation.toggle', ['blockId' => $id]);
            $text = $find($runtime->projector($token), $id)['content']['text'];
            $this->assertStringContainsString($fragments[$j - 1], $text);
            if ($j === 4) {
                $this->assertStringContainsString($locale === 'ru' ? 'один одинаковый жетон' : 'einen gleichen Spielstein', $text);
            }
        }
        $execute('stage', ['stageId' => 'vineyard-step-05']);
        $order = $find($runtime->student($session['id'], $participant), 'vineyard-step-05-order');
        $this->assertArrayNotHasKey('solution', $order);
        $this->assertSame(['step-1', 'step-2', 'step-3', 'step-4'], array_column($order['content']['items'], 'itemId'));
        $runtime->answer($session['id'], $participant, 'vineyard-step-05', 'vineyard-step-05-order', ['stageId' => 'vineyard-step-05', 'blockId' => 'vineyard-step-05-order', 'value' => ['itemIds' => ['step-2', 'step-4', 'step-1', 'step-3']]]);
        $execute('presentation.toggle', ['blockId' => 'vineyard-step-05-check']);
        $this->assertStringContainsString($locale === 'ru' ? 'Утренний договор → Новые приглашения → Плата с последних → Ропот и ответ' : 'Vereinbarung am Morgen → Neue Einladungen → Auszahlung von den Letzten an → Murren und Antwort', $find($runtime->projector($token), 'vineyard-step-05-check')['content']['text']);
        foreach (['promise' => 'One denar agreed, one received.', 'expectation' => 'They expected more after seeing the late workers.'] as $suffix => $text) {
            $id = 'vineyard-step-05-'.$suffix;
            $runtime->answer($session['id'], $participant, 'vineyard-step-05', $id, ['stageId' => 'vineyard-step-05', 'blockId' => $id, 'value' => ['text' => $text]]);
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('stage', ['stageId' => 'vineyard-step-06']);
        $this->assertCount(6, array_filter($runtime->student($session['id'], $participant)['stage']['blocks'], fn ($b) => $b['type'] === 'core.presentation' && $b['config']['kind'] === 'reveal'));
        $execute('stage', ['stageId' => 'vineyard-step-08']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['подарил ему свой новый набор', 'Ein Freiwilliger schenkte', 'только пять минут', 'nur fünf statt zehn'] as $private) {
                $this->assertStringNotContainsString($private, $public);
            }
        }
        $first = 'Ask when the promised feedback starts and help Mischa settle in.';
        $runtime->answer($session['id'], $participant, 'vineyard-step-08', 'vineyard-step-08-first-plan', ['stageId' => 'vineyard-step-08', 'blockId' => 'vineyard-step-08-first-plan', 'value' => ['text' => $first]]);
        $execute('stage', ['stageId' => 'vineyard-step-09']);
        $condition = $find($runtime->projector($token), 'vineyard-step-09-condition')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'пять минут' : 'fünf statt zehn', $condition);
        $second = 'Request the missing five minutes; Mischa keeps his gift.';
        $runtime->answer($session['id'], $participant, 'vineyard-step-09', 'vineyard-step-09-second-plan', ['stageId' => 'vineyard-step-09', 'blockId' => 'vineyard-step-09-second-plan', 'value' => ['text' => $second]]);
        foreach (['vineyard-step-08-first-plan' => $first, 'vineyard-step-09-second-plan' => $second] as $id => $text) {
            $answer = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $answer['value']['text']);
            $this->assertNull($answer['grade']);
        }
        $execute('stage', ['stageId' => 'vineyard-step-11']);
        $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        $execute('presentation.toggle', ['blockId' => 'vineyard-step-11-prayer']);
        $this->assertStringContainsString($locale === 'ru' ? 'Можно молча слушать' : 'Still zuhören ist möglich', $find($runtime->projector($token), 'vineyard-step-11-prayer')['content']['text']);
        $execute('stage', ['stageId' => 'vineyard-step-12']);
        $this->assertNotSame('finished', $runtime->student($session['id'], $participant)['status']);
        foreach (['promise' => 'The agreed denar.', 'kindness' => 'Ask for my need without taking the gift.'] as $suffix => $text) {
            $id = 'vineyard-step-12-'.$suffix;
            $runtime->answer($session['id'], $participant, 'vineyard-step-12', $id, ['stageId' => 'vineyard-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('vineyard-step-12-closing', $finished['closing']['id']);
    }
}
