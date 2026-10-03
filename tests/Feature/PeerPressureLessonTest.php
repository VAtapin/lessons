<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\PeerPressureLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PeerPressureLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_words_reading_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(PeerPressureLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/pressure-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('indigo', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-pressure-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/vse-tak-delayut/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/pressure/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('Если же и не будет того', $plans['ru']['plan']);
        $this->assertStringContainsString('Auch wenn es nicht geschieht', $plans['de']['plan']);
        $this->assertStringContainsString('Verse 91–95 entsprechen 24–28', $plans['de']['plan']);
        $this->assertStringContainsString('95. Da sagte Nebukadnezar', $plans['de']['plan']);
        preg_match_all('/\*\*(\d+)\.\*\*/', $raw['bible'], $ruVerses);
        preg_match_all('/^(\d+)\. /m', (require resource_path('content/pressure-de.php'))['bible'], $deVerses);
        $this->assertSame(['1', '4', '5', '6', '7', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '24', '49', '50', '51', '91', '92', '93', '94', '95'], $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/pressure-starters.php');
        $this->assertCount(52, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 52);
        $this->assertCount(23, require resource_path('content/pressure-cards.php'));
        foreach (require resource_path('content/pressure-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $plans['de']['plan']);
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($card[2]."\n".$card[3], $doc->stages[$card[4]]->content['de']['notes']);
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(PeerPressureLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 52);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_choice_precedes_miracle_team_conditions_are_private_and_plans_remain_independent(string $locale): void
    {
        app(PeerPressureLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('vse-tak-delayut', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'pressure-step-02']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $this->assertStringNotContainsString('Материалы для учителя', json_encode($dto, JSON_UNESCAPED_UNICODE));
            $this->assertStringNotContainsString('95. Da sagte Nebukadnezar', json_encode($dto, JSON_UNESCAPED_UNICODE));
            $this->assertCount(2, $dto['stage']['blocks']);
        }
        $teacher = $runtime->teacher($owner, $session['id']);
        $notes = collect($teacher['document']['stages'])->firstWhere('id', 'pressure-step-02')['content']['notes'];
        $this->assertStringContainsString($locale === 'ru' ? '95. Тогда Навуходоносор' : '95. Da sagte Nebukadnezar', $notes);
        $execute('stage', ['stageId' => 'pressure-step-03']);
        $roles = $find($runtime->student($session['id'], $participant), 'pressure-step-03-roles')['content']['roles'];
        $this->assertCount(5, $roles);
        $this->assertSame($locale === 'ru' ? ['Рассказчик-глашатай', 'Царь', 'Седрах', 'Мисах', 'Авденаго'] : ['Erzähler und Herold', 'König', 'Schadrach', 'Meschach', 'Abed-Nego'], array_column($roles, 'text'));
        foreach ([13, 3, 4, 14] as $number) {
            $id = 'pressure-step-03-frame-'.$number;
            $this->assertEmpty($find($runtime->student($session['id'], $participant), $id)['media']);
            $execute('presentation.toggle', ['blockId' => $id]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $this->assertSame('builtin-pressure-'.$number, $find($dto, $id)['media']['image']['assetId']);
            }
        }
        $this->assertStringContainsString($locale === 'ru' ? 'Если же и не будет того' : 'Auch wenn es nicht geschieht', $find($runtime->projector($token), 'pressure-step-03-frame-3')['content']['text']);
        $this->assertStringContainsString($locale === 'ru' ? 'Ангел Господень' : 'Engel des Herrn', $find($runtime->projector($token), 'pressure-step-03-frame-4')['content']['text']);
        $this->assertStringContainsString($locale === 'ru' ? '95. Тогда Навуходоносор' : '95. Da sagte Nebukadnezar', $find($runtime->projector($token), 'pressure-step-03-frame-14')['content']['text']);
        $execute('stage', ['stageId' => 'pressure-step-04']);
        $this->assertArrayNotHasKey('solution', $find($runtime->student($session['id'], $participant), 'pressure-step-04-order'));
        $this->assertSame('', $find($runtime->projector($token), 'pressure-step-04-check')['content']['text']);
        $order = $find($runtime->student($session['id'], $participant), 'pressure-step-04-order');
        $this->assertSame(['event-1', 'event-2', 'event-3', 'event-4'], array_column($order['content']['items'], 'itemId'));
        $runtime->answer($session['id'], $participant, 'pressure-step-04', 'pressure-step-04-order', ['stageId' => 'pressure-step-04', 'blockId' => 'pressure-step-04-order', 'value' => ['itemIds' => ['event-2', 'event-4', 'event-1', 'event-3']]]);
        $execute('presentation.toggle', ['blockId' => 'pressure-step-04-check']);
        $this->assertStringContainsString($locale === 'ru' ? 'Повеление царя' : 'Befehl des Königs', $find($runtime->projector($token), 'pressure-step-04-check')['content']['text']);
        $execute('stage', ['stageId' => 'pressure-step-07']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['Двое участников тоже сомневаются', 'Zwei andere zweifeln auch', 'Классный руководитель сейчас в соседнем кабинете', 'Die Klassenleitung ist jetzt im Nachbarraum', 'в чате снова требуют переслать фото', 'Im Chat verlangt man erneut'] as $private) {
                $this->assertStringNotContainsString($private, $public);
            }
        }
        $runtime->answer($session['id'], $participant, 'pressure-step-07', 'pressure-step-07-first-plan', ['stageId' => 'pressure-step-07', 'blockId' => 'pressure-step-07-first-plan', 'value' => ['text' => 'I will not forward it and will talk to him personally.']]);
        $execute('stage', ['stageId' => 'pressure-step-08']);
        $this->assertStringNotContainsString('Im Chat verlangt man erneut', json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE));
        $execute('stage', ['stageId' => 'pressure-step-09']);
        $this->assertStringContainsString($locale === 'ru' ? 'в чате снова требуют переслать фото' : 'Im Chat verlangt man erneut', $find($runtime->projector($token), 'pressure-step-09-condition')['content']['text']);
        $runtime->answer($session['id'], $participant, 'pressure-step-09', 'pressure-step-09-second-plan', ['stageId' => 'pressure-step-09', 'blockId' => 'pressure-step-09-second-plan', 'value' => ['text' => 'I will leave the chat and go with a friend to a teacher.']]);
        $teacherAnswers = $runtime->teacher($owner, $session['id'])['answers'];
        foreach (['pressure-step-07-first-plan', 'pressure-step-09-second-plan'] as $id) {
            $answer = collect($teacherAnswers)->firstWhere('blockId', $id);
            $this->assertNotNull($answer);
            $this->assertNull($answer['grade']);
        }
        foreach (['pressure-step-10', 'pressure-step-11'] as $id) {
            $execute('stage', ['stageId' => $id]);
            $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        }
        $execute('stage', ['stageId' => 'pressure-step-12']);
        $this->assertNotSame('finished', $runtime->student($session['id'], $participant)['status']);
        foreach (['pressure-step-12-faith' => 'They stayed faithful to God before the miracle.', 'pressure-step-12-step' => 'I can refuse harm and ask an adult for help.'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'pressure-step-12', $id, ['stageId' => 'pressure-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $own['value']['text']);
            $this->assertNull($own['grade']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('pressure-step-12-closing', $finished['closing']['id']);
    }
}
