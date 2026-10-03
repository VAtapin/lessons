<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\LostSheepLessonInstaller;
use App\Domain\Lessons\TeacherDocumentation;
use App\Models\CatalogTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GermanMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_adapted_pack_has_german_downloads_and_originals_are_preserved(): void
    {
        $files = app(DocumentationFiles::class);
        foreach (['neighbor', 'words', 'zakkhei', 'judge', 'sheep', 'talent', 'anger', 'choices', 'envy', 'forgiveness', 'friends', 'joseph', 'peter', 'pressure', 'spasibo', 'strakh', 'truth', 'vineyard'] as $key) {
            $this->assertArrayHasKey($key, config('german-lesson-files.sets'));
        }
        $this->assertEqualsCanonicalizing(array_keys(config('german-lesson-files.sets')), array_values(array_unique(array_values(config('german-lesson-files.sourceFiles')))));
        foreach (config('german-lesson-files.sourceFiles') as $sourceId => $set) {
            $original = config('lesson-files.'.$sourceId);
            $documentation = TeacherDocumentation::fromArray(['schemaVersion' => 1, 'content' => [], 'files' => [
                ['fileId' => $sourceId, 'kind' => $original['kind'], 'locale' => $original['locale']],
            ]], ['ru', 'de']);
            $before = $documentation->toArray();
            $references = $files->localizedReferences($documentation, 'de');
            $this->assertSame(config('german-lesson-files.sets.'.$set), array_column($references, 'fileId'));
            $this->assertSame(['de'], array_values(array_unique(array_column($references, 'locale'))));
            $this->assertSame($before, $documentation->toArray());
        }
        foreach (config('german-lesson-files.files') as $id => $expected) {
            $file = $files->resolve($id);
            $this->assertGreaterThan(1000, $file['bytes']);
            $this->assertSame('de', $file['locale']);
            $this->get($file['url'])->assertOk()->assertHeader('Content-Type', $expected['mime']);
        }
        $id = array_key_first(config('german-lesson-files.files'));
        config(['german-lesson-files.files.'.$id.'.sha256' => str_repeat('0', 64)]);
        $this->get('/lesson-files/'.$id)->assertNotFound();
    }

    public function test_only_reviewed_games_are_listed_and_old_questions_term_is_hidden(): void
    {
        app(LostSheepLessonInstaller::class)->install();
        foreach (['ru', 'de'] as $locale) {
            $games = $this->getJson('/api/catalog?format=game&locale='.$locale)->assertOk()->assertJsonCount(2, 'entries');
            $this->assertSame([1, 3], array_column($games->json('entries'), 'stageIndex'));
            $games->assertJsonMissingPath('entries.0.teacherNotes')->assertJsonMissingPath('entries.0.solution');
        }
        CatalogTerm::create(['id' => (string) Str::uuid(), 'kind' => 'format', 'key' => 'questions', 'labels' => ['ru' => 'Вопросы', 'de' => 'Fragen'], 'active' => true, 'revision' => 1]);
        $terms = $this->getJson('/api/catalog/taxonomy?locale=de')->assertOk()->json('terms');
        $this->assertNotContains('questions', array_column($terms, 'key'));
        $this->getJson('/api/catalog?format=questions')->assertOk()->assertJsonCount(0, 'entries');
    }
}
