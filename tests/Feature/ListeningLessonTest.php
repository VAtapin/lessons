<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\LeadershipLessonInstaller;
use App\Application\Catalog\ListeningLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\CatalogEntry;
use App\Models\LessonMaterial;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ListeningLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_formats_preserve_source_readings_downloads_and_install_without_duplicates(): void
    {
        $result = app(ListeningLessonInstaller::class)->install();
        $raw = json_decode(file_get_contents(resource_path('content/listening-source.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($result['entries'] as $variant => $installed) {
            $this->assertTrue($installed['created']);
            $this->assertSame(['adults'], $installed['entry']->metadata['age']);
            $doc = LessonDocument::fromArray($installed['entry']->version->document, app(BlockRegistry::class));
            $this->assertCount(10, $doc->stages);
            $this->assertSame($variant === 'general' ? 3600 : 4500, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
            foreach (['ru', 'de'] as $locale) {
                $path = base_path('assets/lessons/pochemu-my-ne-slyshim-drug-druga/'.$locale.'/');
                $this->assertSame(str_replace("\r\n", "\n", file_get_contents($path.'Scenario_'.$variant.'.md')), $raw[$locale]['variants'][$variant]['scenario']);
                $this->assertSame(str_replace("\r\n", "\n", file_get_contents($path.'Bible.md')), $raw[$locale]['Bible']);
                foreach ($doc->stages as $i => $stage) {
                    $source = $raw[$locale]['variants'][$variant]['stages'][$i];
                    $this->assertStringContainsString($source['notes'], $stage->content[$locale]['notes']);
                    $this->assertSame($source['minutes'] * 60, $stage->config['durationSeconds']);
                    $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, $locale)['content']);
                }
                $this->assertStringContainsString($raw[$locale]['Bible'], $doc->stages[3]->content[$locale]['notes']);
                preg_match_all('/^(\d+) /m', $raw[$locale]['Bible'], $matches);
                $this->assertSame([19, 20, 25, 26, 27, 28, 29, 30, 31, 32], array_map('intval', $matches[1]));
                $files = app(DocumentationFiles::class)->localizedReferences($doc->documentation, $locale);
                $this->assertCount(5, $files);
                $this->assertStringContainsString($locale === 'ru' ? 'Контекст Иак. 1:21–22' : 'Kontext Jakobus 1,21–22', $doc->documentation->toArray()['content'][$locale]['plan']);
                foreach ($files as $reference) {
                    $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
                    $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
                    if ($file['kind'] === 'presentation') {
                        $this->assertStringContainsString('slides-'.$variant, $reference['fileId']);
                    }
                }
            }
            foreach ([5, 7, 8] as $index) {
                $this->assertCount(0, array_filter($doc->stages[$index]->blocks, fn ($b) => $b->type === 'core.free-response'));
            }
            $this->assertCount($variant === 'couples' ? 0 : 1, array_filter($doc->stages[9]->blocks, fn ($b) => $b->type === 'core.free-response'));
        }
        $this->assertSame(64, $result['templates']['total']);
        $repeat = app(ListeningLessonInstaller::class)->install();
        foreach ($repeat['entries'] as $entry) {
            $this->assertFalse($entry['created']);
        }
        $this->assertSame(2, CatalogEntry::query()->where('slug', 'like', 'pochemu-my-ne-slyshim-drug-druga%')->count());
    }

    public static function formats(): array
    {
        return [['ru', 'general'], ['de', 'general'], ['ru', 'couples'], ['de', 'couples']];
    }

    public function test_conflict_in_second_format_rolls_back_first_format(): void
    {
        $existing = app(LeadershipLessonInstaller::class)->install()['entry'];
        $conflict = $existing->replicate(['id']);
        $conflict->id = (string) Str::uuid();
        $conflict->slug = 'pochemu-my-ne-slyshim-drug-druga-suprugi';
        $conflict->save();
        $before = LessonMaterial::count();
        try {
            app(ListeningLessonInstaller::class)->install();
            $this->fail('A conflicting second source must abort installation.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('receipt differs', $error->getMessage());
        }
        $this->assertSame($before, LessonMaterial::count());
        $this->assertFalse(CatalogEntry::query()->where('slug', 'pochemu-my-ne-slyshim-drug-druga')->exists());
        $this->assertSame($existing->lesson_version_id, $conflict->fresh()->lesson_version_id);
    }

    #[DataProvider('formats')]
    public function test_live_answers_hidden_background_reading_and_private_pair_work(string $locale, string $variant): void
    {
        app(ListeningLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $slug = 'pochemu-my-ne-slyshim-drug-druga'.($variant === 'couples' ? '-suprugi' : '');
        $session = app(CatalogService::class)->use($slug, $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $prefix = 'listening-'.$variant;
        $stageId = fn ($n) => $prefix.'-step-'.sprintf('%02d', $n);
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $answer = function ($n, $suffix, $value) use ($runtime, &$session, $participant, $stageId): void {
            $id = $stageId($n).'-'.$suffix;
            $runtime->answer($session['id'], $participant, $stageId($n), $id, ['stageId' => $stageId($n), 'blockId' => $id, 'value' => $value]);
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $answer(1, 'barrier', ['optionId' => 'choice-1']);
        $this->assertArrayNotHasKey('summary', $find($runtime->projector($token), $stageId(1).'-barrier')['runtime']);
        $execute('block.review', ['blockId' => $stageId(1).'-barrier']);
        $this->assertSame(1, $find($runtime->projector($token), $stageId(1).'-barrier')['runtime']['results']['totalAnswers']);
        $execute('stage', ['stageId' => $stageId(2)]);
        foreach ([$runtime->student($session['id'], $participant), $runtime->projector($token)] as $dto) {
            $public = json_encode($dto, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString($locale === 'ru' ? 'тревожное сообщение' : 'beunruhigende Nachricht', $public);
            $this->assertArrayNotHasKey('notes', $dto['stage']['content']);
        }
        $answer(2, 'heard', ['text' => 'First interpretation of fictional scene']);
        $execute('stage', ['stageId' => $stageId(3)]);
        $answer(3, 'reply', ['optionId' => 'choice-3']);
        $saved = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $stageId(3).'-reply');
        $this->assertNull($saved['grade']);
        $this->assertSame('', $find($runtime->projector($token), $stageId(3).'-review')['content']['text']);
        $execute('stage', ['stageId' => $stageId(4)]);
        $phrase = $locale === 'ru' ? 'гнев человека не творит правды Божией' : 'Zorn wirkt nicht Gottes Gerechtigkeit';
        $this->assertStringContainsString($phrase, $runtime->teacher($owner, $session['id'])['document']['stages'][3]['content']['notes']);
        $this->assertStringNotContainsString($phrase, json_encode($runtime->projector($token), JSON_UNESCAPED_UNICODE));
        $execute('stage', ['stageId' => $stageId(5)]);
        $this->assertSame('', $find($runtime->projector($token), $stageId(5).'-background')['content']['text']);
        foreach ([1 => 1, 2 => 2, 3 => 1, 4 => 2] as $card => $category) {
            $answer(5, 'fact-'.$card, ['optionId' => 'choice-'.$category]);
            $this->assertArrayNotHasKey('results', $find($runtime->projector($token), $stageId(5).'-fact-'.$card)['runtime']);
            $execute('block.review', ['blockId' => $stageId(5).'-fact-'.$card]);
            $this->assertSame('choice-'.$category, $find($runtime->projector($token), $stageId(5).'-fact-'.$card)['runtime']['results']['optionId']);
        }
        $execute('presentation.toggle', ['blockId' => $stageId(5).'-background']);
        $this->assertStringContainsString($locale === 'ru' ? 'тревожное сообщение' : 'beunruhigende Nachricht', $find($runtime->projector($token), $stageId(5).'-background')['content']['text']);
        $execute('stage', ['stageId' => $stageId(7)]);
        $answer(7, 'new-dialog', ['text' => 'New fictional dialogue']);
        $answers = collect($runtime->teacher($owner, $session['id'])['answers']);
        $this->assertSame('First interpretation of fictional scene', $answers->firstWhere('blockId', $stageId(2).'-heard')['value']['text']);
        $this->assertSame('New fictional dialogue', $answers->firstWhere('blockId', $stageId(7).'-new-dialog')['value']['text']);
        $execute('stage', ['stageId' => $stageId(9)]);
        $this->assertCount(0, array_filter($runtime->student($session['id'], $participant)['stage']['blocks'], fn ($b) => $b['type'] === 'core.free-response'));
        $execute('stage', ['stageId' => $stageId(10)]);
        if ($variant === 'general') {
            $answer(10, 'exit', ['text' => 'What would you like me to understand?']);
        }
        $this->assertSame('running', $runtime->student($session['id'], $participant)['status']);
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame($stageId(10).'-closing', $runtime->student($session['id'], $participant)['closing']['id']);
    }
}
