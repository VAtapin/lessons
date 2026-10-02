<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\DocumentationFiles;
use App\Application\Catalog\IllustratedLessonsInstaller;
use App\Application\Catalog\ReviewedLessonInstaller;
use App\Application\Catalog\TalentLessonInstaller;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\CommonTemplate;
use App\Models\LessonVersion;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class IllustratedLessonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_six_releases_preserve_tasks_old_files_copies_classes_and_hidden_status(): void
    {
        $oldEntry = app(TalentLessonInstaller::class)->install()['entry'];
        $oldDocument = $oldEntry->version->document;
        $oldFiles = [];
        foreach ($oldDocument['documentation']['files'] as $file) {
            $oldFiles[$file['fileId']] = hash_file('sha256', app(DocumentationFiles::class)->resolve($file['fileId'])['path']);
        }
        $session = app(CatalogService::class)->use($oldEntry->slug, 'de', (string) Str::uuid(), true)['session'];
        $snapshot = TeachingSession::findOrFail($session['id'])->toArray();
        $oldEntry->update(['status' => 'hidden']);
        $result = app(IllustratedLessonsInstaller::class)->install();
        $this->assertSame('hidden', $oldEntry->fresh()->status);
        $this->assertSame($oldDocument, LessonVersion::findOrFail($oldDocument['id'])->document);
        $this->assertSame($snapshot, TeachingSession::findOrFail($session['id'])->toArray());
        $sources = (static fn () => require resource_path('content/illustrated-releases.php'))();
        foreach ($sources as $key => $source) {
            $this->assertTrue($result[$key]['created']);
            $entry = $result[$key]['entry'];
            $doc = LessonDocument::fromArray($entry->version->document, app(BlockRegistry::class));
            $this->assertCount(count($source['old']['document']['stages']), $doc->stages);
            foreach ($source['old']['document']['stages'] as $i => $stage) {
                $this->assertSame($stage['content'], $source['next']['document']['stages'][$i]['content']);
                $this->assertSame($stage['config'], $source['next']['document']['stages'][$i]['config']);
                foreach ($stage['blocks'] as $block) {
                    $this->assertSame($block['content'], collect($source['next']['document']['stages'][$i]['blocks'])->firstWhere('id', $block['id'])['content']);
                    if (! in_array($block['type'], ['core.presentation', 'core.image'], true)) {
                        $this->assertSame($block, collect($source['next']['document']['stages'][$i]['blocks'])->firstWhere('id', $block['id']));
                    }
                }
            }
            foreach ($doc->documentation->toArray()['files'] as $reference) {
                $file = app(DocumentationFiles::class)->resolve($reference['fileId']);
                $this->get($file['url'])->assertOk()->assertHeader('content-type', $file['mime']);
            }
        }
        foreach ($oldFiles as $id => $hash) {
            $this->assertSame($hash, hash_file('sha256', app(DocumentationFiles::class)->resolve($id)['path']));
        }
        $this->assertCount(7, $sources['words']['next']['document']['documentation']['files']);
        $this->assertSame(80, $result['templates']['total']);
        $repeat = app(IllustratedLessonsInstaller::class)->install();
        foreach (array_keys($sources) as $key) {
            $this->assertFalse($repeat[$key]['created']);
        }
    }

    public function test_upgrade_rejects_a_drifted_receipt_without_new_versions_or_republication(): void
    {
        $entry = app(TalentLessonInstaller::class)->install()['entry'];
        $entry->update(['status' => 'hidden']);
        $source = (require resource_path('content/illustrated-releases.php'))['talent'];
        $source['old']['sourceRevision'] = 'unexpected';
        try {
            app(ReviewedLessonInstaller::class)->upgrade($source['old'], $source['next']);
            $this->fail('Receipt drift must be rejected.');
        } catch (RuntimeException) {
            $this->assertDatabaseMissing('lesson_versions', ['id' => $source['next']['versionId']]);
            $this->assertSame('hidden', $entry->fresh()->status);
            $this->assertSame($source['old']['versionId'], $entry->fresh()->lesson_version_id);
        }
    }

    public function test_a_failed_last_upgrade_rolls_back_all_six_releases_and_shared_blocks(): void
    {
        $entry = app(TalentLessonInstaller::class)->install()['entry'];
        $entry->update(['status' => 'hidden']);
        DB::table('catalog_entries')->where('id', $entry->id)->update(['source_revision' => 'unexpected']);
        $versions = LessonVersion::count();
        $templates = CommonTemplate::count();
        try {
            app(IllustratedLessonsInstaller::class)->install();
            $this->fail('A conflicting receipt must roll back the complete update.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('catalog_entries', 1);
            $this->assertDatabaseCount('lesson_versions', $versions);
            $this->assertDatabaseCount('common_templates', $templates);
            $this->assertSame('hidden', $entry->fresh()->status);
        }
    }
}
