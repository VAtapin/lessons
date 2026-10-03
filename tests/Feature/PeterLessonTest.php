<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\PeterLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PeterLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_source_words_readings_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(PeterLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/peter-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('sand', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-peter-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/ya-oshibsya-vse-poteryano/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/peter/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('kein leichtes Leben', $plans['de']['plan']);
        $this->assertStringContainsString('kauft Gottes Liebe nicht', $plans['de']['plan']);
        preg_match_all('/\*\*(?:Лк\. 22:|Ин\. 21:)(\d+)\.\*\*/u', $raw['bible'], $ruVerses);
        preg_match_all('/^(?:Lukas 22,|Johannes 21,)(\d+)\. /m', (require resource_path('content/peter-de.php'))['bible'], $deVerses);
        $this->assertSame(array_map('strval', array_merge(range(31, 34), range(54, 62), range(1, 19))), $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/peter-starters.php');
        $this->assertCount(59, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 59);
        $this->assertCount(23, require resource_path('content/peter-cards.php'));
        foreach (require resource_path('content/peter-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $plans['de']['plan']);
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $doc->stages[$card[4]]->content['de']['notes']);
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(PeterLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 59);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_two_stories_three_denials_three_commissions_and_independent_plans(string $locale): void
    {
        app(PeterLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('ya-oshibsya-vse-poteryano', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        foreach ([2, 4] as $n) {
            $stage = 'peter-step-'.sprintf('%02d', $n);
            $execute('stage', ['stageId' => $stage]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $this->assertCount(2, $dto['stage']['blocks']);
                $this->assertStringNotContainsString('Vorbereitung für die Lehrkraft', json_encode($dto, JSON_UNESCAPED_UNICODE));
                $this->assertStringNotContainsString('Лк. 22:62. И, выйдя', json_encode($dto, JSON_UNESCAPED_UNICODE));
            }
            $notes = collect($runtime->teacher($owner, $session['id'])['document']['stages'])->firstWhere('id', $stage)['content']['notes'];
            $this->assertStringContainsString($locale === 'ru' ? 'Лк. 22:62.' : 'Lukas 22,62.', $notes);
            $this->assertStringContainsString($locale === 'ru' ? 'Ин. 21:19.' : 'Johannes 21,19.', $notes);
        }
        $execute('stage', ['stageId' => 'peter-step-03']);
        $roles = $find($runtime->student($session['id'], $participant), 'peter-step-03-roles');
        $this->assertSame($locale === 'ru' ? ['Рассказчик', 'Пётр', 'Служанка', 'Первый мужчина', 'Второй мужчина'] : ['Erzähler', 'Petrus', 'Magd', 'Erster Mann', 'Zweiter Mann'], array_column($roles['content']['roles'], 'text'));
        $this->assertEmpty($roles['runtime']['presentation']['revealedRoleIds'] ?? []);
        $execute('role.reveal.next', ['blockId' => 'peter-step-03-roles']);
        $this->assertSame(['role-1'], $find($runtime->student($session['id'], $participant), 'peter-step-03-roles')['runtime']['presentation']['revealedRoleIds']);
        $runtime->answer($session['id'], $participant, 'peter-step-03', 'peter-step-03-roles', ['stageId' => 'peter-step-03', 'blockId' => 'peter-step-03-roles', 'value' => ['roleId' => 'role-1']]);
        $this->assertSame('role-1', collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', 'peter-step-03-roles')['value']['roleId']);
        foreach ([3 => [2, 13, 14, 14, 3], 5 => [4, 5, 5, 5, 5]] as $n => $pictures) {
            $stage = 'peter-step-'.sprintf('%02d', $n);
            $execute('stage', ['stageId' => $stage]);
            foreach ($pictures as $j => $picture) {
                $id = $stage.'-frame-'.($j + 1);
                $this->assertEmpty($find($runtime->projector($token), $id)['media']);
                $execute('presentation.toggle', ['blockId' => $id]);
                foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                    $this->assertSame('builtin-peter-'.$picture, $find($dto, $id)['media']['image']['assetId']);
                }
            }
            if ($n === 3) {
                foreach ([2 => ['я не знаю Его', 'Ich kenne Ihn nicht'], 3 => ['нет!', 'Nein!'], 4 => ['не знаю, что ты говоришь', 'Ich weiß nicht, was du sagst'], 5 => ['горько заплакал', 'weinte bitterlich']] as $j => $words) {
                    $this->assertStringContainsString($words[$locale === 'ru' ? 0 : 1], $find($runtime->projector($token), $stage.'-frame-'.$j)['content']['text']);
                }
                $this->assertStringNotContainsString($locale === 'ru' ? 'петух' : 'Hahn', $find($runtime->projector($token), $stage.'-frame-3')['content']['text']);
                $this->assertStringContainsString($locale === 'ru' ? 'запел петух' : 'krähte der Hahn', $find($runtime->projector($token), $stage.'-frame-4')['content']['text']);
            } else {
                $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.roles'));
                $this->assertStringContainsString($locale === 'ru' ? 'сто пятьдесят три' : 'hundertdreiundfünfzig', $find($runtime->projector($token), $stage.'-frame-1')['content']['text']);
                $this->assertStringContainsString($locale === 'ru' ? 'Пётр опечалился' : 'Petrus wurde traurig', $find($runtime->projector($token), $stage.'-frame-4')['content']['text']);
            }
        }
        $this->assertStringContainsString($locale === 'ru' ? 'больше, нежели они' : 'mehr als diese', $find($runtime->projector($token), 'peter-step-05-frame-2')['content']['text']);
        $this->assertStringContainsString($locale === 'ru' ? 'паси агнцев Моих' : 'Weide Meine Lämmer', $find($runtime->projector($token), 'peter-step-05-frame-2')['content']['text']);
        foreach ([3, 4] as $j) {
            $this->assertStringContainsString($locale === 'ru' ? 'паси овец Моих' : 'Weide Meine Schafe', $find($runtime->projector($token), 'peter-step-05-frame-'.$j)['content']['text']);
        }
        $end = $find($runtime->projector($token), 'peter-step-05-frame-5')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'какою смертью Пётр прославит Бога' : 'mit welchem Tod Petrus Gott verherrlichen', $end);
        $this->assertStringContainsString($locale === 'ru' ? 'иди за Мною' : 'Folge Mir nach', $end);
        $execute('stage', ['stageId' => 'peter-step-06']);
        $order = $find($runtime->student($session['id'], $participant), 'peter-step-06-order');
        $this->assertArrayNotHasKey('solution', $order);
        $this->assertSame(['event-1', 'event-2', 'event-3', 'event-4'], array_column($order['content']['items'], 'itemId'));
        $this->assertSame('', $find($runtime->projector($token), 'peter-step-06-check')['content']['text']);
        $runtime->answer($session['id'], $participant, 'peter-step-06', 'peter-step-06-order', ['stageId' => 'peter-step-06', 'blockId' => 'peter-step-06-order', 'value' => ['itemIds' => ['event-2', 'event-4', 'event-3', 'event-1']]]);
        $execute('presentation.toggle', ['blockId' => 'peter-step-06-check']);
        $this->assertStringContainsString($locale === 'ru' ? 'Три отречения → Память и горький плач → Встреча после Воскресения → Новое поручение' : 'Drei Verleugnungen → Erinnerung und bitteres Weinen → Begegnung nach der Auferstehung → Ein neuer Auftrag', $find($runtime->projector($token), 'peter-step-06-check')['content']['text']);
        $execute('stage', ['stageId' => 'peter-step-07']);
        foreach (['truth' => 'I blamed Lisa; that was false.', 'repair' => 'I will tell the teacher and team the truth.'] as $suffix => $text) {
            $id = 'peter-step-07-'.$suffix;
            $runtime->answer($session['id'], $participant, 'peter-step-07', $id, ['stageId' => 'peter-step-07', 'blockId' => $id, 'value' => ['text' => $text]]);
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('stage', ['stageId' => 'peter-step-08']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['Плакат нужен завтра', 'Das Plakat wird morgen gebraucht', 'пока не хочет выступать', 'vorerst nicht mit Danja', 'Значит, всё бессмысленно', 'Dann ist alles sinnlos'] as $private) {
                $this->assertStringNotContainsString($private, $public);
            }
        }
        $first = 'Tell the teacher and team Lisa is innocent; apologise and repair the poster.';
        $runtime->answer($session['id'], $participant, 'peter-step-08', 'peter-step-08-first-plan', ['stageId' => 'peter-step-08', 'blockId' => 'peter-step-08-first-plan', 'value' => ['text' => $first]]);
        $execute('stage', ['stageId' => 'peter-step-09']);
        $this->assertStringNotContainsString('Dann ist alles sinnlos', json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE));
        $execute('stage', ['stageId' => 'peter-step-10']);
        $condition = $find($runtime->projector($token), 'peter-step-10-condition')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'с другим участником' : 'mit einem anderen Teilnehmer', $condition);
        $second = 'Respect Lisa’s answer, keep the truth and repair with another participant.';
        $runtime->answer($session['id'], $participant, 'peter-step-10', 'peter-step-10-second-plan', ['stageId' => 'peter-step-10', 'blockId' => 'peter-step-10-second-plan', 'value' => ['text' => $second]]);
        foreach (['peter-step-08-first-plan' => $first, 'peter-step-10-second-plan' => $second] as $id => $text) {
            $answer = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $answer['value']['text']);
            $this->assertNull($answer['grade']);
        }
        $execute('stage', ['stageId' => 'peter-step-11']);
        $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        $this->assertSame('', $find($runtime->projector($token), 'peter-step-11-prayer')['content']['text']);
        $execute('presentation.toggle', ['blockId' => 'peter-step-11-prayer']);
        $this->assertStringContainsString($locale === 'ru' ? 'Можно молча слушать' : 'Still zuhören ist möglich', $find($runtime->projector($token), 'peter-step-11-prayer')['content']['text']);
        $execute('stage', ['stageId' => 'peter-step-12']);
        $this->assertNotSame('finished', $runtime->student($session['id'], $participant)['status']);
        foreach (['peter-step-12-faith' => 'Christ entrusted care for others to Peter.', 'peter-step-12-step' => 'I will correct my false accusation today.'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'peter-step-12', $id, ['stageId' => 'peter-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $own['value']['text']);
            $this->assertNull($own['grade']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('peter-step-12-closing', $finished['closing']['id']);
    }
}
