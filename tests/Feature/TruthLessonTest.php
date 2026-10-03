<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\TruthLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TruthLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_source_words_readings_cards_media_downloads_and_repeat_installation(): void
    {
        $result = app(TruthLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/truth-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString(str_replace('**', '', $raw['screens'][$i]['notes']), $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('steel', $stage->config['theme']);
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
                    $n = (int) str_replace('builtin-truth-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/mozhno-li-sovrat-radi-dobra/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/truth/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation', 'script', 'bible', 'roleplay', 'plan', 'passport'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('Drei alternative Antworten, keine drei aufeinanderfolgenden Ereignisse', $plans['de']['plan']);
        $this->assertStringContainsString('Keine öffentliche Beichte', $plans['de']['plan']);
        preg_match_all('/\*\*Еф\. 4:(\d+)\.\*\*/u', $raw['bible'], $ruVerses);
        preg_match_all('/^Epheser 4,(\d+)\. /m', (require resource_path('content/truth-de.php'))['bible'], $deVerses);
        $this->assertSame(array_map('strval', range(25, 32)), $ruVerses[1]);
        $this->assertSame($ruVerses[1], $deVerses[1]);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $pack = require resource_path('content/truth-starters.php');
        $this->assertCount(59, $pack['templates']);
        $this->assertDatabaseCount('common_templates', 59);
        $this->assertCount(22, require resource_path('content/truth-cards.php'));
        foreach (require resource_path('content/truth-cards.php') as $card) {
            $this->assertStringContainsString($card[0]."\n".$card[1], $raw['handout']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($plans['de']['plan']));
            $this->assertStringContainsString($card[0]."\n".$card[1], $doc->stages[$card[4]]->content['ru']['notes']);
            $this->assertStringContainsString($normalize($card[2]."\n".$card[3]), $normalize($doc->stages[$card[4]]->content['de']['notes']));
        }
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(TruthLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', 59);
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_alternative_answers_revealed_roles_private_facts_and_independent_plans(string $locale): void
    {
        app(TruthLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('mozhno-li-sovrat-radi-dobra', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'truth-step-02']);
        $this->assertSame('', $find($runtime->projector($token), 'truth-step-02-reading')['content']['text']);
        $execute('presentation.toggle', ['blockId' => 'truth-step-02-reading']);
        $reading = $find($runtime->projector($token), 'truth-step-02-reading')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'Еф. 4:25.' : 'Epheser 4,25.', $reading);
        $this->assertStringContainsString($locale === 'ru' ? 'Еф. 4:32.' : 'Epheser 4,32.', $reading);
        $execute('stage', ['stageId' => 'truth-step-03']);
        $roles = $find($runtime->student($session['id'], $participant), 'truth-step-03-roles');
        $this->assertCount(4, $roles['content']['roles']);
        $this->assertEmpty($roles['runtime']['presentation']['revealedRoleIds'] ?? []);
        $execute('role.reveal.next', ['blockId' => 'truth-step-03-roles']);
        $roles = $find($runtime->student($session['id'], $participant), 'truth-step-03-roles');
        $this->assertSame(['role-1'], $roles['runtime']['presentation']['revealedRoleIds']);
        $runtime->answer($session['id'], $participant, 'truth-step-03', 'truth-step-03-roles', ['stageId' => 'truth-step-03', 'blockId' => 'truth-step-03-roles', 'value' => ['roleId' => 'role-1']]);
        foreach ([2, 3, 4] as $j) {
            $this->assertSame('', $find($runtime->projector($token), 'truth-step-03-frame-'.$j)['content']['text']);
            $execute('presentation.toggle', ['blockId' => 'truth-step-03-frame-'.$j]);
            $text = $find($runtime->projector($token), 'truth-step-03-frame-'.$j)['content']['text'];
            $this->assertStringContainsString($locale === 'ru' ? 'Перед каждой версией возвращаемся к этому условию' : 'Vor jeder Version kehren wir zu dieser Bedingung zurück', $text);
            $this->assertStringContainsString($locale === 'ru' ? 'не закончен' : 'nicht fertig', $text);
            if ($j === 3) {
                $this->assertStringContainsString($locale === 'ru' ? 'Миша слышит неверный вывод и не уточняет' : 'Mischa hört den falschen Schluss und präzisiert nicht', $text);
            }
            if ($j === 4) {
                $this->assertStringContainsString($locale === 'ru' ? 'теперь обсуждение' : 'jetzt Besprechung', $text);
                $this->assertStringContainsString($locale === 'ru' ? 'Обещать готовность будем по результату' : 'Bereitschaft versprechen wir nach dem Ergebnis', $text);
            }
        }
        $execute('stage', ['stageId' => 'truth-step-04']);
        $order = $find($runtime->student($session['id'], $participant), 'truth-step-04-order');
        $this->assertArrayNotHasKey('solution', $order);
        $this->assertSame(['step-1', 'step-2', 'step-3', 'step-4'], array_column($order['content']['items'], 'itemId'));
        $runtime->answer($session['id'], $participant, 'truth-step-04', 'truth-step-04-order', ['stageId' => 'truth-step-04', 'blockId' => 'truth-step-04-order', 'value' => ['itemIds' => ['step-3', 'step-1', 'step-4', 'step-2']]]);
        $execute('presentation.toggle', ['blockId' => 'truth-step-04-check']);
        $this->assertStringContainsString($locale === 'ru' ? 'Выяснить факты → Подумать о слушателе → Ответить честно → Помочь делом' : 'Fakten klären → An den Zuhörer denken → Ehrlich antworten → Durch Handeln helfen', $find($runtime->projector($token), 'truth-step-04-check')['content']['text']);
        $execute('stage', ['stageId' => 'truth-step-05']);
        foreach (['impression' => 'The listener expects the whole project.', 'clarify' => 'Part is ready; I can help with the rest.'] as $suffix => $text) {
            $id = 'truth-step-05-'.$suffix;
            $runtime->answer($session['id'], $participant, 'truth-step-05', $id, ['stageId' => 'truth-step-05', 'blockId' => $id, 'value' => ['text' => $text]]);
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('stage', ['stageId' => 'truth-step-07']);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            foreach (['порванными или отсутствующими страницами', 'zerrissenen oder fehlenden Seiten', 'уже написал в общий чат', 'schrieb schon in den Gruppenchat', 'Учитель подтвердил передачу завтра'] as $private) {
                $this->assertStringNotContainsString($private, $public);
            }
        }
        $first = 'Check all books, separate damaged ones and agree the real delivery with the teacher.';
        $runtime->answer($session['id'], $participant, 'truth-step-07', 'truth-step-07-first-plan', ['stageId' => 'truth-step-07', 'blockId' => 'truth-step-07-first-plan', 'value' => ['text' => $first]]);
        $execute('stage', ['stageId' => 'truth-step-08']);
        $this->assertStringNotContainsString('schrieb schon in den Gruppenchat', json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE));
        $execute('stage', ['stageId' => 'truth-step-09']);
        $condition = $find($runtime->projector($token), 'truth-step-09-condition')['content']['text'];
        $this->assertStringContainsString($locale === 'ru' ? 'уже написал в общий чат' : 'schrieb schon in den Gruppenchat', $condition);
        $second = 'Correct the message in the same chat and contact the teacher who confirmed delivery.';
        $runtime->answer($session['id'], $participant, 'truth-step-09', 'truth-step-09-second-plan', ['stageId' => 'truth-step-09', 'blockId' => 'truth-step-09-second-plan', 'value' => ['text' => $second]]);
        foreach (['truth-step-07-first-plan' => $first, 'truth-step-09-second-plan' => $second] as $id => $text) {
            $answer = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $answer['value']['text']);
            $this->assertNull($answer['grade']);
        }
        $execute('stage', ['stageId' => 'truth-step-11']);
        $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        $this->assertSame('', $find($runtime->projector($token), 'truth-step-11-prayer')['content']['text']);
        $execute('presentation.toggle', ['blockId' => 'truth-step-11-prayer']);
        $this->assertStringContainsString($locale === 'ru' ? 'Можно молча слушать' : 'Still zuhören ist möglich', $find($runtime->projector($token), 'truth-step-11-prayer')['content']['text']);
        $execute('stage', ['stageId' => 'truth-step-12']);
        $this->assertNotSame('finished', $runtime->student($session['id'], $participant)['status']);
        foreach (['truth-step-12-truth' => 'When truthful words deliberately create a false impression.', 'truth-step-12-help' => 'I can tell the facts and help finish the work.'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'truth-step-12', $id, ['stageId' => 'truth-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $this->assertSame($text, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('truth-step-12-closing', $finished['closing']['id']);
    }
}
