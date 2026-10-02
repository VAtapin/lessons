<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\FriendsLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FriendsLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_screens_full_plan_images_downloads_and_reusable_blocks_are_preserved(): void
    {
        $result = app(FriendsLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/friends-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(['Начало занятия', 'Чтение Евангелия', 'Евангельская сценка', 'Что сделали друзья и Христос', 'События по порядку', 'Четыре части одного дела', 'Приглашение к общей игре', 'Помощь в обычном дне', 'Разговор с другом', 'Молитва о друзьях', 'Мой шаг на неделю', 'Завершение и обратная связь'], array_column($raw['screens'], 'title'));
        $this->assertSame(['ru', 'de'], $doc->locales);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        $normalize = fn ($s) => preg_replace('/\s+/u', ' ', str_replace('\\n', ' ', $s));
        $images = [];
        foreach ($doc->stages as $i => $stage) {
            $this->assertSame($raw['screens'][$i]['title'], $stage->content['ru']['title']);
            $this->assertStringContainsString($raw['screens'][$i]['notes'], $stage->content['ru']['notes']);
            $this->assertNotEmpty($stage->content['de']['notes']);
            $this->assertSame('cobalt', $stage->config['theme']);
            $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, 'de')['content']);
            $text = $normalize(json_encode(array_map(fn ($b) => $b->content['ru'], $stage->blocks), JSON_UNESCAPED_UNICODE));
            foreach ($raw['screens'][$i]['slides'] as $number) {
                if ($number === 1) {
                    $this->assertStringContainsString($raw['title'], $text);

                    continue;
                }
                foreach ($raw['slides'][$number - 1]['slide'] as $line) {
                    $this->assertStringContainsString($normalize($line), $text);
                }
            }
            foreach ($stage->blocks as $block) {
                if (isset($block->media['image'])) {
                    $asset = $block->media['image']['assetId'];
                    $images[] = $asset;
                    $n = (int) str_replace('builtin-friends-', '', $asset);
                    $this->assertSame(hash_file('sha256', base_path('assets/lessons/chetvero-druzey/images/'.sprintf('%02d.png', $n))), hash_file('sha256', base_path('assets/library/friends/'.sprintf('%02d.png', $n))));
                }
            }
        }
        $this->assertCount(14, array_unique($images));
        $plans = $doc->documentation->toArray()['content'];
        foreach (['handout', 'teacherPreparation'] as $section) {
            $this->assertStringContainsString($raw[$section], $plans['ru']['plan']);
        }
        $this->assertStringContainsString('12 Он тотчас встал', $plans['ru']['plan']);
        $this->assertStringContainsString('12 Er stand sofort auf', $plans['de']['plan']);
        $this->assertCount(5, $doc->documentation->toArray()['files']);
        foreach ($doc->documentation->toArray()['files'] as $reference) {
            $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
            $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
        }
        $this->assertDatabaseCount('common_templates', count((require resource_path('content/friends-starters.php'))['templates']));
        $result['entry']->update(['status' => 'hidden']);
        $this->assertFalse(app(FriendsLessonInstaller::class)->install()['created']);
        $this->assertDatabaseCount('common_templates', count((require resource_path('content/friends-starters.php'))['templates']));
        $this->assertSame('hidden', $result['entry']->fresh()->status);
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_staged_forgiveness_then_healing_private_team_cards_and_independent_final_answers(string $locale): void
    {
        app(FriendsLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('chetvero-druzey', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => 'friends-step-03']);
        foreach ([4 => 6, 5 => 3] as $frame => $asset) {
            $id = 'friends-step-03-frame-'.$frame;
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $hidden = $find($dto, $id);
                $this->assertArrayNotHasKey('title', $hidden['content']);
                $this->assertEmpty($hidden['media']);
            }
            $execute('presentation.toggle', ['blockId' => $id]);
            foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
                $shown = $find($dto, $id);
                $this->assertTrue($shown['runtime']['presentation']['visible']);
                $this->assertSame('builtin-friends-'.$asset, $shown['media']['image']['assetId']);
            }
        }
        $execute('presentation.toggle', ['blockId' => 'friends-step-03-frame-5']);
        $this->assertTrue($find($runtime->student($session['id'], $participant), 'friends-step-03-frame-4')['runtime']['presentation']['visible']);
        $execute('stage', ['stageId' => 'friends-step-05']);
        $sequence = $find($runtime->student($session['id'], $participant), 'friends-step-05-sequence');
        $this->assertArrayNotHasKey('solution', $sequence);
        $this->assertNotSame(['event-2', 'event-4', 'event-1', 'event-3'], array_column($sequence['content']['items'], 'itemId'));
        $execute('stage', ['stageId' => 'friends-step-06']);
        $public = json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Саша пропустил встречу и пока не знает правил.', $public);
        $this->assertStringNotContainsString('Bis zum Spielbeginn bleiben drei Minuten.', $public);
        foreach ([10, 11] as $n) {
            $execute('stage', ['stageId' => 'friends-step-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT)]);
            $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        }
        $execute('stage', ['stageId' => 'friends-step-12']);
        foreach (['friends-step-12-christ' => 'Простил и исцелил', 'friends-step-12-help' => 'Хочешь, я помогу?'] as $id => $text) {
            $runtime->answer($session['id'], $participant, 'friends-step-12', $id, ['stageId' => 'friends-step-12', 'blockId' => $id, 'value' => ['text' => $text]]);
            $own = collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id);
            $this->assertSame($text, $own['value']['text']);
        }
        $execute('finish');
        $finished = $runtime->student($session['id'], $participant);
        $this->assertSame('finished', $finished['status']);
        $this->assertSame('friends-step-12-closing', $finished['closing']['id']);
    }
}
