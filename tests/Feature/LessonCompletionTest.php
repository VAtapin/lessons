<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\GenerosityLessonInstaller;
use App\Application\Catalog\LazarusLessonInstaller;
use App\Application\Catalog\ReviewedLessonInstaller;
use App\Application\Catalog\VineyardLessonInstaller;
use App\Application\Runtime\RuntimeService;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\LessonVersion;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LessonCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vineyard_upgrade_keeps_original_version_copies_and_classes(): void
    {
        $old = require resource_path('content/vineyard.php');
        $old['versionId'] = (string) Str::uuid();
        $old['sourceRevision'] = 'retired-test-release';
        $original = app(ReviewedLessonInstaller::class)->install($old);
        $snapshot = $original['entry']->version->document;
        $copy = app(CatalogService::class)->use($old['slug'], 'ru', (string) Str::uuid(), true);
        $session = TeachingSession::findOrFail($copy['session']['id']);
        $sessionSnapshot = $session->version->document;

        $receipt = array_intersect_key($old, array_flip(['materialId', 'versionId', 'ownerKey', 'slug', 'sourceRevision']));
        $receipt['sourceHash'] = $original['entry']->source_hash;
        $receipt['documentHash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $receipt['metadataHash'] = hash('sha256', json_encode($original['entry']->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $invalidReceipt = array_replace($receipt, ['documentHash' => str_repeat('0', 64)]);
        try {
            app(ReviewedLessonInstaller::class)->upgradeRetired($invalidReceipt, require resource_path('content/vineyard.php'));
            $this->fail('A modified retired document must not be upgraded.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Retired source receipt differs', $error->getMessage());
        }
        $this->assertSame($old['versionId'], $original['entry']->fresh()->lesson_version_id);
        $upgraded = app(ReviewedLessonInstaller::class)->upgradeRetired($receipt, require resource_path('content/vineyard.php'));

        $this->assertTrue($upgraded['created']);
        $this->assertNotSame($old['versionId'], $upgraded['entry']->lesson_version_id);
        $this->assertSame($snapshot, LessonVersion::query()->findOrFail($old['versionId'])->document);
        $this->assertSame($sessionSnapshot, $session->fresh()->version->document);
        $this->assertSame('Почему ему столько же, сколько мне?', $upgraded['entry']->metadata['translations']['ru']['title']);
        $this->assertFalse(app(VineyardLessonInstaller::class)->install()['created']);
    }

    public function test_retired_vineyard_files_resolve_to_corrected_downloads(): void
    {
        $this->assertFileDoesNotExist(resource_path('content/vineyard-v1.php'));
        $this->assertDirectoryDoesNotExist(base_path('assets/lessons/_versions/vineyard-v1'));
        foreach (config('lesson-files') as $id => $file) {
            if (str_starts_with($id, 'vineyard-file-') && str_ends_with($id, '-v1')) {
                $resolved = app(DocumentationFiles::class)->resolve($id);
                $this->assertStringContainsString('pochemu-emu-bolshe-chem-mne', $resolved['path']);
                $this->assertSame($file['sha256'], hash_file('sha256', $resolved['path']));
            }
        }
        foreach (config('german-lesson-files.sets.vineyard') as $id) {
            $resolved = app(DocumentationFiles::class)->resolve($id);
            $this->assertStringContainsString('pochemu-emu-bolshe-chem-mne', $resolved['path']);
            $this->assertStringNotContainsString('_versions', $resolved['path']);
        }
    }

    public function test_vineyard_tasks_keep_equal_gift_after_new_information(): void
    {
        $source = require resource_path('content/vineyard.php');
        $raw = json_decode(file_get_contents(resource_path('content/vineyard-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Каждому по динарию', implode("\n", $raw['slides'][2]['slide']));
        $this->assertStringContainsString('разном времени труда', $raw['passport']);
        $this->assertStringContainsString('Царство Небесное', $raw['teacherPreparation']);
        foreach (['ru', 'de'] as $locale) {
            $text = implode("\n", array_map(fn ($b) => $b['content'][$locale]['text'] ?? '', $source['document']['stages'][8]['blocks']));
            $this->assertStringContainsString($locale === 'ru' ? 'Порции остаются одинаковыми' : 'Portionen bleiben gleich', $text);
            $this->assertStringNotContainsString($locale === 'ru' ? 'пять минут' : 'fünf Minuten', $text);
            $this->assertStringNotContainsString($locale === 'ru' ? 'новый набор' : 'neuer Malkasten', $text);
        }
    }

    public function test_generosity_preserves_both_complete_readings_and_salvation_by_god(): void
    {
        $result = app(GenerosityLessonInstaller::class)->install();
        $source = require resource_path('content/generosity.php');
        $doc = LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
        $this->assertCount(12, $doc->stages);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        foreach (['ru', 'de'] as $locale) {
            $reading = implode("\n", array_map(fn ($b) => $b->content[$locale]['text'] ?? '', $doc->stages[1]->blocks));
            preg_match_all('/^(\d+)\. /m', $reading, $verses);
            $this->assertSame(array_merge(range(15, 21), range(16, 26)), array_map('intval', $verses[1]));
            $this->assertStringContainsString($locale === 'ru' ? 'следуй за Мною' : 'folge mir', $reading);
            $this->assertStringContainsString($locale === 'ru' ? 'Богу же всё возможно' : 'Gott aber sind alle Dinge möglich', $reading);
            $this->assertCount(6, $doc->stages[2]->blocks[2]->content[$locale]['roles']);
        }
        $this->assertCount(6, array_filter($doc->stages[2]->blocks, fn ($b) => str_contains($b->id, '-frame-')));
        $this->assertTrue($result['created']);
        $this->assertFalse(app(GenerosityLessonInstaller::class)->install()['created']);
        $this->assertDownloads($doc);
    }

    public function test_lazarus_has_all_verses_christian_center_and_no_answer_deadline(): void
    {
        $result = app(LazarusLessonInstaller::class)->install();
        $source = require resource_path('content/lazarus.php');
        $doc = LessonDocument::fromArray($source['document'], app(BlockRegistry::class));
        $raw = json_decode(file_get_contents(resource_path('content/lazarus-source.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(12, $doc->stages);
        $this->assertSame(2700, array_sum(array_map(fn ($s) => $s->config['durationSeconds'], $doc->stages)));
        foreach (['ru', 'de'] as $locale) {
            $reading = implode("\n", array_map(fn ($b) => $b->content[$locale]['text'] ?? '', $doc->stages[1]->blocks));
            preg_match_all($locale === 'ru' ? '/Ин\. 11:(\d+)\./u' : '/Johannes 11,(\d+)\./u', $reading, $verses);
            $this->assertSame(range(1, 45), array_map('intval', $verses[1]));
            $this->assertStringContainsString($locale === 'ru' ? 'Иисус прослезился' : 'Jesus weinte', $reading);
            $this->assertStringContainsString($locale === 'ru' ? 'Я есмь воскресение и жизнь' : 'Ich bin die Auferstehung und das Leben', $reading);
            $this->assertCount(6, $doc->stages[2]->blocks[2]->content[$locale]['roles']);
            $this->assertStringContainsString($locale === 'ru' ? 'не знаем' : 'kennen wir nicht', $raw[$locale]['stages'][3]['notes']);
            $this->assertStringContainsString($locale === 'ru' ? 'Не обещайте' : 'Keine Heilung', $raw[$locale]['preparation']);
            $this->assertFalse(collect($doc->stages[10]->blocks)->contains(fn ($b) => $b->type === 'core.free-response'));
            $this->assertCount(2, array_filter($doc->stages[11]->blocks, fn ($b) => $b->type === 'core.free-response'));
        }
        $this->assertCount(6, $raw['ru']['frames']);
        $this->assertCount(6, $raw['de']['frames']);
        $this->assertCount(24, $raw['cards']);
        $this->assertTrue($result['created']);
        $this->assertFalse(app(LazarusLessonInstaller::class)->install()['created']);
        $this->assertDownloads($doc);
    }

    public static function completedLessons(): array
    {
        return [['generosity', 'ru'], ['generosity', 'de'], ['lazarus', 'ru'], ['lazarus', 'de']];
    }

    #[DataProvider('completedLessons')]
    public function test_completed_lessons_reveal_reading_and_preserve_independent_answers(string $name, string $locale): void
    {
        app($name === 'generosity' ? GenerosityLessonInstaller::class : LazarusLessonInstaller::class)->install();
        $source = require resource_path('content/'.$name.'.php');
        $runtime = app(RuntimeService::class);
        $owner = (string) Str::uuid();
        $session = app(CatalogService::class)->use($source['slug'], $locale, $owner, true)['session'];
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $execute = function ($action, $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $find = fn ($dto, $id) => collect($dto['stage']['blocks'])->firstWhere('id', $id);
        $execute('begin');
        $execute('stage', ['stageId' => $name.'-step-02']);
        foreach ($source['document']['stages'][1]['blocks'] as $block) {
            if (! str_contains($block['id'], '-reading-')) {
                continue;
            }
            $this->assertSame('', $find($runtime->projector($token), $block['id'])['content']['text']);
            $execute('presentation.toggle', ['blockId' => $block['id']]);
            $this->assertSame($block['content'][$locale]['text'], $find($runtime->projector($token), $block['id'])['content']['text']);
        }
        $execute('stage', ['stageId' => $name.'-step-03']);
        $rolesId = $name.'-step-03-roles';
        $this->assertCount(6, $find($runtime->student($session['id'], $participant), $rolesId)['content']['roles']);
        $execute('role.reveal.next', ['blockId' => $rolesId]);
        $this->assertSame(['role-1'], $find($runtime->student($session['id'], $participant), $rolesId)['runtime']['presentation']['revealedRoleIds']);
        foreach ([8 => 'first-plan', 9 => 'second-plan'] as $stage => $suffix) {
            $stageId = $name.'-step-'.sprintf('%02d', $stage);
            $execute('stage', ['stageId' => $stageId]);
            $id = $stageId.'-'.$suffix;
            $runtime->answer($session['id'], $participant, $stageId, $id, ['stageId' => $stageId, 'blockId' => $id, 'value' => ['text' => $suffix]]);
        }
        foreach ([8 => 'first-plan', 9 => 'second-plan'] as $stage => $suffix) {
            $id = $name.'-step-'.sprintf('%02d', $stage).'-'.$suffix;
            $answer = collect($runtime->teacher($owner, $session['id'])['answers'])->firstWhere('blockId', $id);
            $this->assertSame($suffix, $answer['value']['text']);
            $this->assertNull($answer['grade']);
        }
        $execute('stage', ['stageId' => $name.'-step-11']);
        $this->assertFalse(collect($runtime->student($session['id'], $participant)['stage']['blocks'])->contains(fn ($b) => $b['type'] === 'core.free-response'));
        $execute('stage', ['stageId' => $name.'-step-12']);
        foreach ($source['document']['stages'][11]['blocks'] as $block) {
            if ($block['type'] !== 'core.free-response') {
                continue;
            }
            $id = $block['id'];
            $runtime->answer($session['id'], $participant, $name.'-step-12', $id, ['stageId' => $name.'-step-12', 'blockId' => $id, 'value' => ['text' => $id]]);
            $this->assertSame($id, collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $id)['value']['text']);
        }
        $execute('finish');
        $this->assertSame('finished', $runtime->student($session['id'], $participant)['status']);
    }

    private function assertDownloads(LessonDocument $doc): void
    {
        $files = app(DocumentationFiles::class);
        foreach (['ru', 'de'] as $locale) {
            $references = $files->localizedReferences($doc->documentation, $locale);
            $this->assertCount(5, $references);
            foreach ($references as $reference) {
                $file = $files->resolve($reference['fileId']);
                $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
            }
        }
    }
}
