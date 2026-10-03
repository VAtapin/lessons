<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\AngerLessonInstaller;
use App\Application\Catalog\CatalogService;
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

final class AngerLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_words_reading_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(AngerLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/anger-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('graphite', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-anger-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/ya-razozlilsya-chto-teper/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/anger/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(13, array_unique($images));
        foreach (range(1, 14) as $n) {
            $this->assertSame(hash_file('sha256', base_path('assets/lessons/ya-razozlilsya-chto-teper/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/anger/'.sprintf('%02d.png', $n))));
        }
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('Авель погиб', $plans['ru']['plan']);
        $this->assertStringContainsString('keine Versöhnung der Brüder', $plans['de']['plan']);
        $this->assertStringContainsString('Gott wird nicht dargestellt', $plans['de']['plan']);
        preg_match_all('/\*\*(?:Быт\. 4:|Еф\. 4:)(\d+)\.\*\*/u', $raw['bible'], $ruVerses);
        preg_match_all('/^(?:Genesis|Epheser) 4,(\d+)\. /m', (require resource_path('content/anger-de.php'))['bible'], $deVerses);
        $this->assertSame(array_map('strval', array_merge(range(1, 16), range(26, 32))), $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertStringContainsString('[пойдём в поле]', $raw['bible']);
        $this->assertStringContainsString('[Gehen wir aufs Feld]', (require resource_path('content/anger-de.php'))['bible']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/anger-starters.php');
        $this->assertCount(50, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 50);
        $this->assertCount(21, require resource_path('content/anger-cards.php'));
        foreach (require resource_path('content/anger-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $plans['de']['plan']);
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $doc->stages[$card[4]]->content['de']['notes']);
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(AngerLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 50);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_warning_tragedy_full_ephesians_and_independent_plans(string $locale): void
    {
        app(AngerLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('ya-razozlilsya-chto-teper', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'anger-step-02']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $this->assertCount(2, $dto['stage']['blocks']);
            $this->assertStringNotContainsString('Vorbereitung für die Lehrkraft', json_encode($dto, JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString('Быт. 4:16. И пошёл', json_encode($dto, JSON_UNESCAPED_UNICODE));
        }
        $notes = collect($runtime->teacher($owner, $session['id'])['document']['stages'])->firstWhere('id', 'anger-step-02')['content']['notes'];
        $this->assertStringContainsString($locale === 'ru' ? 'Быт. 4:16.' : 'Genesis 4,16.', $notes);
        $execute('stage', ['stageId' => 'anger-step-03']);
        $this->assertSame($locale === 'ru' ? ['Рассказчик', 'Каин', 'Авель'] : ['Erzähler', 'Kain', 'Abel'], array_column($find($runtime->student($session['id'], $participant), 'anger-step-03-roles')['content']['roles'], 'text'));
        $execute('role.reveal.next', ['blockId' => 'anger-step-03-roles']);
        $runtime->answer($session['id'], $participant, 'anger-step-03', 'anger-step-03-roles', ['stageId' => 'anger-step-03', 'blockId' => 'anger-step-03-roles', 'value' => ['roleId' => 'role-1']]);
        $this->assertSame('role-1', collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', 'anger-step-03-roles')['value']['roleId']);
        foreach ([2, 3, 13, 4] as $number) {
            $id = 'anger-step-03-frame-'.$number;
            $this->assertEmpty($find($runtime->projector($token), $id)['media']);
            $execute('presentation.toggle', ['blockId' => $id]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $this->assertSame('builtin-anger-'.$number, $find($dto, $id)['media']['image']['assetId']);
            }
        }
        $this->assertStringContainsString($locale === 'ru' ? 'ты господствуй над ним' : 'du sollst über sie herrschen', $find($runtime->projector($token), 'anger-step-03-frame-3')['content']['text']);
        $this->assertStringContainsString($locale === 'ru' ? 'и убил его' : 'und tötete ihn', $find($runtime->projector($token), 'anger-step-03-frame-13')['content']['text']);
        $ending = $find($runtime->projector($token), 'anger-step-03-frame-4')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'не убил его' : 'ihn tötet', $ending);
        $this->assertStringContainsString($locale === 'ru' ? 'земле Нод' : 'Land Nod', $ending);
        $execute('stage', ['stageId' => 'anger-step-04']);
        $order = $find($runtime->student($session['id'], $participant), 'anger-step-04-order');
        $this->assertArrayNotHasKey('solution', $order);
        $this->assertSame(['event-1', 'event-2', 'event-3', 'event-4'], array_column($order['content']['items'], 'itemId'));
        foreach (['anger-step-04-check', 'anger-step-04-reading'] as $id) {
            $this->assertSame('', $find($runtime->projector($token), $id)['content']['text']);
        }
        $runtime->answer($session['id'], $participant, 'anger-step-04', 'anger-step-04-order', ['stageId' => 'anger-step-04', 'blockId' => 'anger-step-04-order', 'value' => ['itemIds' => ['event-2', 'event-4', 'event-1', 'event-3']]]);
        $execute('presentation.toggle', ['blockId' => 'anger-step-04-check']);
        $this->assertStringContainsString($locale === 'ru' ? 'Авель погибает' : 'Abel stirbt', $find($runtime->projector($token), 'anger-step-04-check')['content']['text']);
        $execute('presentation.toggle', ['blockId' => 'anger-step-04-reading']);
        $reading = $find($runtime->projector($token), 'anger-step-04-reading')['content']['text'];
        foreach (range(26, 32) as $number) {
            $this->assertStringContainsString(($locale === 'ru' ? 'Еф. 4:' : 'Epheser 4,').$number.'.', $reading);
        }
        $execute('stage', ['stageId' => 'anger-step-05']);
        $this->assertStringContainsString($locale === 'ru' ? 'по трём группам' : 'drei Gruppen', $find($runtime->projector($token), 'anger-step-05-pair')['content']['text']);
        $execute('stage', ['stageId' => 'anger-step-06']);
        foreach (['pause' => 'I need five minutes and will return at 14:05.', 'action' => 'Put the phone down and relax my hands.'] as $suffix => $text) {
            $id = 'anger-step-06-'.$suffix;
            $runtime->answer($session['id'], $participant, 'anger-step-06', $id, ['stageId' => 'anger-step-06', 'blockId' => $id, 'value' => ['text' => $text]]);
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('stage', ['stageId' => 'anger-step-07']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['Скриншот переслал другой участник', 'Ein anderer Teilnehmer leitete', 'Через десять минут', 'In zehn Minuten', 'Рома признал', 'Roma gab zu', 'зовёт Мишу на драку', 'ruft Mischa zu einer Schlägerei'] as $private) {
                $this->assertStringNotContainsString($private, $public);
            }
        }
        $first = 'Do not send a threat; check the original with the teacher.';
        $runtime->answer($session['id'], $participant, 'anger-step-07', 'anger-step-07-first-plan', ['stageId' => 'anger-step-07', 'blockId' => 'anger-step-07-first-plan', 'value' => ['text' => $first]]);
        $execute('stage', ['stageId' => 'anger-step-08']);
        $this->assertStringNotContainsString('Roma gab zu', json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE));
        $execute('stage', ['stageId' => 'anger-step-09']);
        $condition = $find($runtime->projector($token), 'anger-step-09-condition')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'Разозлился после проигрыша' : 'nach der Niederlage wütend', $condition);
        $this->assertStringContainsString($locale === 'ru' ? 'зовёт Мишу на драку' : 'ruft Mischa zu einer Schlägerei', $condition);
        $second = 'Refuse the fight, seek help, ask Roma to correct the message in the same chat.';
        $runtime->answer($session['id'], $participant, 'anger-step-09', 'anger-step-09-second-plan', ['stageId' => 'anger-step-09', 'blockId' => 'anger-step-09-second-plan', 'value' => ['text' => $second]]);
        foreach (['anger-step-07-first-plan' => $first, 'anger-step-09-second-plan' => $second] as $id => $text) {
            $answer = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $answer['value']['text']);
            $this->assertNull($answer['grade']);
        }
        foreach (['anger-step-10', 'anger-step-11'] as $id) {
            $execute('stage', ['stageId' => $id]);
            $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        }
        $execute('stage', ['stageId' => 'anger-step-12']);
        $this->assertNotSame('finished', $runtime->student($session['id'], $participant)['status']);
        foreach (['anger-step-12-faith' => 'Kain could have listened to the warning before the field.', 'anger-step-12-step' => 'I will put the phone down and ask for a pause.'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'anger-step-12', $id, ['stageId' => 'anger-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $own['value']['text']);
            $this->assertNull($own['grade']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('anger-step-12-closing', $finished['closing']['id']);
    }
}
