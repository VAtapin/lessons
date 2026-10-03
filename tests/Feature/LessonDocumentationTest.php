<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\NeighborDocumentationInstaller;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Catalog\NeighborUpgradeInstaller;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\TeacherDocumentation;
use App\Domain\Lessons\ValidationException;
use App\Models\LessonDocumentation;
use App\Models\LessonVersion;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class LessonDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_documentation_can_be_installed_after_v2_upgrade_without_repinning_or_mutating_releases(): void
    {
        $original = app(NeighborInstaller::class)->install()['entry']->version;
        $originalBefore = $original->getAttributes();
        $upgraded = app(NeighborUpgradeInstaller::class)->install()['entry'];
        $pinBefore = $upgraded->getAttributes();
        $upgradedBefore = $upgraded->version->getAttributes();
        $this->artisan('lessons:install-neighbor-docs')->expectsOutput('Installed: neighbor documentation')->assertSuccessful();
        $this->artisan('lessons:install-neighbor-docs')->expectsOutput('Already installed: neighbor documentation')->assertSuccessful();
        $this->assertSame($pinBefore, $upgraded->fresh()->getAttributes());
        $this->assertSame($originalBefore, $original->fresh()->getAttributes());
        $this->assertSame($upgradedBefore, $upgraded->version->fresh()->getAttributes());
        $this->assertSame(2, LessonVersion::where('lesson_material_id', $original->lesson_material_id)->count());
        $this->assertDatabaseCount('lesson_documentations', 1);
        $this->assertSame($original->id, LessonDocumentation::query()->sole()->lesson_version_id);
        $this->assertSame((require resource_path('content/neighbor-documentation.php')), LessonDocumentation::query()->sole()->payload);
    }

    public function test_full_bilingual_plan_is_copied_into_snapshot_and_remains_teacher_only(): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        $original = $entry->version->document;
        $this->assertTrue(app(NeighborDocumentationInstaller::class)->install());
        $this->assertFalse(app(NeighborDocumentationInstaller::class)->install());
        $this->assertSame($original, $entry->version->fresh()->document);
        $source = require resource_path('content/neighbor-documentation.php');
        foreach (['ru', 'de'] as $locale) {
            $detail = app(CatalogService::class)->detail('kto-moi-blizhnii', $locale);
            $this->assertSame($source['content'][$locale]['plan'], $detail['entry']['documentation']['plan']);
            foreach ($detail['entry']['documentation']['files'] as $file) {
                $this->assertSame($locale, $file['locale']);
                $this->get($file['url'])->assertOk();
            }
            $owner = (string) Str::uuid();
            $session = app(CatalogService::class)->use('kto-moi-blizhnii', $locale, $owner, true)['session'];
            $this->assertSame($source['content'][$locale]['plan'], $session['document']['documentation']['plan']);
            $model = TeachingSession::findOrFail($session['id']);
            $this->assertSame($source, $model->version->document['documentation']);
            $projector = app(RuntimeService::class)->projector($model->projector_token);
            $this->assertArrayNotHasKey('documentation', $projector);
            $this->assertStringNotContainsString('neighbor-plan', json_encode($projector));
            $participant = app(RuntimeService::class)->join($session['joinCode'], 'Synthetic pupil', [])['participant']['id'];
            $student = app(RuntimeService::class)->student($session['id'], $participant);
            $this->assertArrayNotHasKey('documentation', $student);
            $this->assertStringNotContainsString('GZZkS1DThlg', json_encode($student));
        }
    }

    public function test_reusable_documents_download_with_their_real_language_and_hash(): void
    {
        foreach (['neighbor-plan-ru-v1', 'neighbor-plan-de-v1', 'neighbor-presentation-ru-v1'] as $id) {
            $file = app(DocumentationFiles::class)->resolve($id);
            $this->assertSame($file['sha256'], hash_file('sha256', $file['path']));
            $this->get('/lesson-files/'.$id)->assertOk()->assertHeader('Content-Type', $file['mime'])->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->get('/lesson-files/unknown-file')->assertNotFound();
        config(['lesson-files.neighbor-plan-ru-v1.sha256' => str_repeat('0', 64)]);
        $this->get('/lesson-files/neighbor-plan-ru-v1')->assertNotFound();
    }

    public function test_metadata_is_strict_and_mismatched_or_unknown_files_cannot_enter_a_lesson(): void
    {
        $data = require resource_path('content/neighbor-documentation.php');
        foreach ([['video' => ['id' => 'https://evil.invalid', 'locale' => 'ru']], ['content' => ['fr' => ['plan' => 'Wrong language']]], ['schemaVersion' => 99]] as $changes) {
            try {
                TeacherDocumentation::fromArray(array_replace($data, $changes), ['ru', 'de']);
                $this->fail('Malformed documentation must be rejected.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach ([['fileId' => 'unknown', 'kind' => 'plan', 'locale' => 'ru'], ['fileId' => 'neighbor-plan-ru-v1', 'kind' => 'presentation', 'locale' => 'ru']] as $reference) {
            try {
                app(DocumentationFiles::class)->assertDocumentation(TeacherDocumentation::fromArray(array_replace($data, ['files' => [$reference]]), ['ru', 'de']));
                $this->fail('Unknown files must be rejected.');
            } catch (ApiProblem $problem) {
                $this->assertContains($problem->problemCode, ['not_found', 'invalid_document']);
            }
        }
    }

    public function test_sidecar_is_write_once_and_document_projection_has_no_plan_fallback(): void
    {
        app(NeighborDocumentationInstaller::class)->install();
        $record = LessonDocumentation::query()->firstOrFail();
        try {
            $record->source_hash = str_repeat('0', 64);
            $record->save();
            $this->fail('Released documentation is immutable.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }
        $source = require resource_path('content/kto-moi-blizhnii.php');
        $source['document']['documentation'] = ['schemaVersion' => 1, 'content' => ['ru' => ['plan' => 'Russian original']], 'files' => [], 'video' => ['id' => 'GZZkS1DThlg', 'locale' => 'ru']];
        $document = LessonDocument::fromArray($source['document'], BlockRegistry::core());
        $this->assertNull($document->project(Audience::Teacher, 'de')['documentation']['plan']);
        $this->assertSame('ru', $document->project(Audience::Teacher, 'de')['documentation']['video']['locale']);
        $this->assertArrayNotHasKey('documentation', $document->project(Audience::Student, 'ru'));
    }
}
