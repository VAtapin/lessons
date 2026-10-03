<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\EnvyLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EnvyLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_words_reading_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(EnvyLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/envy-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('rose', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-envy-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/a-esli-mne-zavidno/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/envy/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('семнадцати лет', $plans['ru']['plan']);
        $this->assertStringContainsString('zwanzig Silberstücke', $plans['de']['plan']);
        $this->assertStringContainsString('36. Die Midianiter', $plans['de']['plan']);
        $this->assertStringContainsString('Noch keine Wiederbegegnung, Vergebung', $plans['de']['plan']);
        preg_match_all('/\*\*(\d+)\.\*\*/', $raw['bible'], $ruVerses);
        preg_match_all('/^(\d+)\. /m', (require resource_path('content/envy-de.php'))['bible'], $deVerses);
        $this->assertSame(array_map('strval', range(1, 36)), $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/envy-starters.php');
        $this->assertCount(54, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 54);
        $this->assertCount(25, require resource_path('content/envy-cards.php'));
        foreach (require resource_path('content/envy-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $plans['de']['plan']);
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $doc->stages[$card[4]]->content['de']['notes']);
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(EnvyLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 54);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_chapter_ending_stays_tragic_conditions_are_private_and_plans_are_independent(string $locale): void
    {
        app(EnvyLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('a-esli-mne-zavidno', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'envy-step-02']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $this->assertStringNotContainsString('Материалы для учителя', json_encode($dto, JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString('36. Die Midianiter', json_encode($dto, JSON_UNESCAPED_UNICODE));
            $this->assertCount(2, $dto['stage']['blocks']);
        }
        $teacher = $runtime->teacher($owner, $session['id']);
        $notes = collect($teacher['document']['stages'])->firstWhere('id', 'envy-step-02')['content']['notes'];
        $this->assertStringContainsString($locale === 'ru' ? '36. Мадианитяне' : '36. Die Midianiter', $notes);
        $execute('stage', ['stageId' => 'envy-step-03']);
        $roles = $find($runtime->student($session['id'], $participant), 'envy-step-03-roles')['content']['roles'];
        $this->assertSame($locale === 'ru' ? ['Рассказчик', 'Иосиф', 'Иаков', 'Рувим', 'Иуда', 'Брат', 'Торговец'] : ['Erzähler', 'Josef', 'Jakob', 'Ruben', 'Juda', 'Bruder', 'Händler'], array_column($roles, 'text'));
        $execute('role.reveal.next', ['blockId' => 'envy-step-03-roles']);
        $runtime->answer($session['id'], $participant, 'envy-step-03', 'envy-step-03-roles', ['stageId' => 'envy-step-03', 'blockId' => 'envy-step-03-roles', 'value' => ['roleId' => 'role-1']]);
        $this->assertSame('role-1', collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', 'envy-step-03-roles')['value']['roleId']);
        foreach ([2, 13, 4, 14] as $number) {
            $id = 'envy-step-03-frame-'.$number;
            $this->assertEmpty($find($runtime->student($session['id'], $participant), $id)['media']);
            $execute('presentation.toggle', ['blockId' => $id]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $this->assertSame('builtin-envy-'.$number, $find($dto, $id)['media']['image']['assetId']);
            }
        }
        $this->assertStringContainsString($locale === 'ru' ? 'возвратить его к отцу' : 'zu seinem Vater zurückzubringen', $find($runtime->projector($token), 'envy-step-03-frame-13')['content']['text']);
        $this->assertStringContainsString($locale === 'ru' ? 'двадцать сребреников' : 'zwanzig Silberstücke', $find($runtime->projector($token), 'envy-step-03-frame-4')['content']['text']);
        $ending = $find($runtime->projector($token), 'envy-step-03-frame-14')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'не хотел утешиться' : 'wollte sich nicht trösten lassen', $ending);
        $this->assertStringContainsString($locale === 'ru' ? 'Потифару' : 'Potifar', $ending);
        $execute('stage', ['stageId' => 'envy-step-04']);
        $this->assertArrayNotHasKey('solution', $find($runtime->student($session['id'], $participant), 'envy-step-04-order'));
        $this->assertSame('', $find($runtime->projector($token), 'envy-step-04-check')['content']['text']);
        $order = $find($runtime->student($session['id'], $participant), 'envy-step-04-order');
        $this->assertSame(['event-1', 'event-2', 'event-3', 'event-4'], array_column($order['content']['items'], 'itemId'));
        $runtime->answer($session['id'], $participant, 'envy-step-04', 'envy-step-04-order', ['stageId' => 'envy-step-04', 'blockId' => 'envy-step-04-order', 'value' => ['itemIds' => ['event-2', 'event-1', 'event-4', 'event-3']]]);
        $execute('presentation.toggle', ['blockId' => 'envy-step-04-check']);
        $this->assertStringContainsString($locale === 'ru' ? 'Одежда, сны, зависть' : 'Gewand, Träume, Neid', $find($runtime->projector($token), 'envy-step-04-check')['content']['text']);
        $execute('stage', ['stageId' => 'envy-step-05']);
        $this->assertStringContainsString($locale === 'ru' ? 'болью от сравнения' : 'Schmerz durch Vergleich', $find($runtime->projector($token), 'envy-step-05-pair')['content']['text']);
        $execute('stage', ['stageId' => 'envy-step-07']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['Подпись фотографа пропущена', 'Die Angabe des Fotografen fehlt', 'через 15 минут', 'in 15 Minuten', 'кто-то предлагает стереть Олино имя', 'Jemand schlägt vor, Oljas Namen wegzuradieren'] as $private) {
                $this->assertStringNotContainsString($private, $public);
            }
        }
        $first = 'Keep both contributions, add Dima’s name and ask the teacher.';
        $runtime->answer($session['id'], $participant, 'envy-step-07', 'envy-step-07-first-plan', ['stageId' => 'envy-step-07', 'blockId' => 'envy-step-07-first-plan', 'value' => ['text' => $first]]);
        $execute('stage', ['stageId' => 'envy-step-08']);
        $this->assertStringNotContainsString('wegzuradieren', json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE));
        $execute('stage', ['stageId' => 'envy-step-09']);
        $condition = $find($runtime->projector($token), 'envy-step-09-condition')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'кто-то предлагает стереть Олино имя' : 'Jemand schlägt vor, Oljas Namen wegzuradieren', $condition);
        $this->assertStringContainsString($locale === 'ru' ? 'Оля говорит:' : 'Olja sagt:', $condition);
        $second = 'Keep Olja’s name too, thank her and practice photography even if envy remains.';
        $runtime->answer($session['id'], $participant, 'envy-step-09', 'envy-step-09-second-plan', ['stageId' => 'envy-step-09', 'blockId' => 'envy-step-09-second-plan', 'value' => ['text' => $second]]);
        $teacherAnswers = $runtime->teacher($owner, $session['id'])['answers'];
        foreach (['envy-step-07-first-plan' => $first, 'envy-step-09-second-plan' => $second] as $id => $text) {
            $answer = collect($teacherAnswers)->firstWhere('blockId', $id);
            $this->assertSame($text, $answer['value']['text']);
            $this->assertNull($answer['grade']);
        }
        foreach (['envy-step-10', 'envy-step-11'] as $id) {
            $execute('stage', ['stageId' => $id]);
            $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        }
        $execute('stage', ['stageId' => 'envy-step-12']);
        $this->assertNotSame('finished', $runtime->student($session['id'], $participant)['status']);
        foreach (['envy-step-12-faith' => 'The brothers could have returned the coat instead of selling Josef.', 'envy-step-12-step' => 'I can ask for advice and practice my own skill.'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'envy-step-12', $id, ['stageId' => 'envy-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $own['value']['text']);
            $this->assertNull($own['grade']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('envy-step-12-closing', $finished['closing']['id']);
    }
}
