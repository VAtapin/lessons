<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\AdultForgivenessLessonInstaller;
use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\ForgivenessLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\CatalogEntry;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class AdultForgivenessLessonTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_reading_cards_downloads_and_timing_are_preserved(): void
    {
        $this->withoutVite();
        $result = app(AdultForgivenessLessonInstaller::class)->install();
        $this->assertTrue($result['created']);
        $doc = LessonDocument::fromArray($result['entry']->version->document, app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/adult-forgiveness-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['adults'], $result['entry']->metadata['age']);
        $this->assertCount(8, $doc->stages);
        $this->assertSame([240, 420, 540, 480, 540, 600, 480, 300], array_map(fn ($stage) => $stage->config['durationSeconds'], $doc->stages));
        $this->assertSame(3600, array_sum(array_map(fn ($stage) => $stage->config['durationSeconds'], $doc->stages)));
        foreach (['ru', 'de'] as $locale) {
            $path = base_path('assets/lessons/prostit-znachit-vse-zabyt/'.$locale.'/');
            foreach (['Bible', 'Scenario_general', 'Plan', 'Teacher', 'Profile'] as $name) {
                $this->assertSame(str_replace("\r\n", "\n", file_get_contents($path.$name.'.md')), $raw[$locale][$name]);
                $this->assertStringContainsString($raw[$locale][$name], $doc->documentation->toArray()['content'][$locale]['plan']);
            }
            preg_match_all('/^(\d+) /m', $raw[$locale]['Bible'], $verses);
            $this->assertSame(range(21, 35), array_map('intval', $verses[1]));
            $this->assertStringContainsString($raw[$locale]['Bible'], $doc->stages[1]->content[$locale]['notes']);
            foreach ($doc->stages as $index => $stage) {
                $this->assertStringContainsString($raw[$locale]['stages'][$index]['notes'], $stage->content[$locale]['notes']);
                $scene = $stage->blocks[0]->content[$locale];
                $this->assertSame($raw[$locale]['slides'][$index]['text'][1], $scene['title']);
                $this->assertSame(implode("\n", array_slice($raw[$locale]['slides'][$index]['text'], 2, -1)), $scene['text']);
                $this->assertArrayNotHasKey('notes', $stage->project(Audience::Projector, $locale)['content']);
            }
            $files = app(DocumentationFiles::class)->localizedReferences($doc->documentation, $locale);
            $this->assertCount(5, $files);
            foreach ($files as $reference) {
                $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
                $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
            }
            $detail = app(CatalogService::class)->detail('prostit-znachit-vse-zabyt', $locale);
            $this->assertStringContainsString('<table>', $detail['entry']['documentation']['planHtml']);
            $this->get('/'.$locale.'/catalog/prostit-znachit-vse-zabyt')->assertOk();
        }
        for ($n = 1; $n <= 4; $n++) {
            $card = collect($doc->stages[3]->blocks)->first(fn ($block) => $block->id === 'adult-forgiveness-step-04-card-'.$n);
            $this->assertSame('core.prompt', $card->type);
        }
        $this->assertCount(0, array_filter($doc->stages[7]->blocks, fn ($block) => $block->type === 'core.single-choice'));
        $initial = collect($doc->stages[0]->blocks)->first(fn ($block) => $block->type === 'core.poll');
        $final = collect($doc->stages[7]->blocks)->first(fn ($block) => $block->type === 'core.poll');
        $this->assertSame($initial->content, $final->content);
        $this->assertNull($initial->solution);
        $this->assertNull($final->solution);
        $this->assertGreaterThan(20, $result['templates']['total']);
    }

    public function test_installation_is_repeatable_and_does_not_replace_the_teen_lesson(): void
    {
        $teen = app(ForgivenessLessonInstaller::class)->install()['entry'];
        $before = $teen->version->getAttributes();
        $this->artisan('lessons:install-adult-forgiveness')->assertSuccessful();
        $first = CatalogEntry::where('slug', 'prostit-znachit-vse-zabyt')->sole()->getAttributes();
        $this->artisan('lessons:install-adult-forgiveness')->assertSuccessful();
        $this->assertSame($first, CatalogEntry::where('slug', 'prostit-znachit-vse-zabyt')->sole()->getAttributes());
        $this->assertSame($before, $teen->version->fresh()->getAttributes());
        $this->assertDatabaseCount('catalog_entries', 2);
    }

    public function test_a_conflicting_receipt_preserves_published_content(): void
    {
        $entry = app(AdultForgivenessLessonInstaller::class)->install()['entry'];
        $entry->source_hash = str_repeat('0', 64);
        $entry->save();
        $before = $entry->fresh()->getAttributes();
        try {
            app(AdultForgivenessLessonInstaller::class)->install();
            $this->fail('An incompatible receipt must not replace a published lesson.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('receipt differs', $error->getMessage());
        }
        $this->assertSame($before, $entry->fresh()->getAttributes());
    }

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_runtime_keeps_new_facts_hidden_and_personal_work_private(string $locale): void
    {
        app(AdultForgivenessLessonInstaller::class)->install();
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use('prostit-znachit-vse-zabyt', $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $model = TeachingSession::findOrFail($session['id']);
        $stageId = fn ($n) => 'adult-forgiveness-step-'.sprintf('%02d', $n);
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $answer = function ($n, $suffix, $value) use ($runtime, &$session, $participant, $stageId): void {
            $id = $stageId($n).'-'.$suffix;
            $runtime->answer($session['id'], $participant, $stageId($n), $id, ['stageId' => $stageId($n), 'blockId' => $id, 'value' => $value]);
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $answer(1, 'meaning', ['optionId' => 'forget']);
        $this->assertArrayNotHasKey('summary', $find($runtime->student($session['id'], $participant), $stageId(1).'-meaning'));
        $execute('stage', ['stageId' => $stageId(2)]);
        $answer(2, 'roles', ['roleId' => 'role-3']);
        $this->assertArrayNotHasKey('documentation', $runtime->student($session['id'], $participant));
        $this->assertArrayNotHasKey('documentation', $runtime->projector($model->projector_token));
        $this->assertStringContainsString($locale === 'ru' ? '35 Так и Отец' : '35 So wird auch Mein', $session['document']['stages'][1]['content']['notes']);
        $execute('stage', ['stageId' => $stageId(5)]);
        $answer(5, 'before', ['text' => 'A fictional reply before the new fact.']);
        $fact = $stageId(5).'-new-fact';
        $this->assertArrayNotHasKey('text', $find($runtime->student($session['id'], $participant), $fact)['content']);
        $execute('presentation.toggle', ['blockId' => $fact]);
        $this->assertStringContainsString($locale === 'ru' ? 'Ольга признала' : 'Olga hat', $find($runtime->student($session['id'], $participant), $fact)['content']['text']);
        $answer(5, 'after', ['text' => 'A revised fictional reply.']);
        $teacherAnswers = collect($runtime->teacher($owner, $session['id'])['answers']);
        foreach (['before', 'after'] as $suffix) {
            $saved = $teacherAnswers->firstWhere('blockId', $stageId(5).'-'.$suffix);
            $this->assertNotNull($saved);
            $this->assertNull($saved['grade']);
        }
        $execute('stage', ['stageId' => $stageId(6)]);
        $answer(6, 'apology', ['text' => 'Olga asks forgiveness and offers a realistic correction.']);
        $execute('stage', ['stageId' => $stageId(7)]);
        $repeat = $stageId(7).'-repeat';
        $this->assertArrayNotHasKey('text', $find($runtime->projector($model->projector_token), $repeat)['content']);
        $execute('presentation.toggle', ['blockId' => $repeat]);
        $this->assertStringContainsString($locale === 'ru' ? 'другого человека' : 'anderen Person', $find($runtime->student($session['id'], $participant), $repeat)['content']['text']);
        $answer(7, 'boundary', ['text' => 'Elena limits private details without humiliation.']);
        $execute('stage', ['stageId' => $stageId(8)]);
        $private = $find($runtime->student($session['id'], $participant), $stageId(8).'-private-plan');
        $this->assertSame('core.prompt', $private['type']);
        $this->assertArrayNotHasKey('answer', $private);
        $answer(8, 'meaning', ['optionId' => 'mercy']);
        $answer(8, 'learning', ['text' => 'Receiving mercy should lead to mercy toward the other.']);
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
        $this->assertSame($stageId(8).'-closing', $runtime->student($session['id'], $participant)['closing']['id']);
    }
}
