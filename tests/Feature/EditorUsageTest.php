<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LessonVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\EditorFixture;
use Tests\TestCase;

final class EditorUsageTest extends TestCase
{
    use EditorFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorIdentity();
        Storage::fake('media');
    }

    public function test_media_usage_tracks_partial_draft_actual_pairs_and_deduplicates_released_snapshot_editor_refs(): void
    {
        $first = $this->upload('First');
        $second = $this->upload('Second');
        $lesson = $this->editorLesson();
        $working = $lesson['document'];
        $working['stages'][0]['blocks'][0] = $this->image($first);
        $saved = $this->editorSave($lesson, $working);
        $working = $this->blankLocale($saved['document'], 'de');
        $working['stages'][0]['blocks'][0] = $this->image($second, true);
        $partial = $this->editorSave($saved, $working);
        $this->assertSame($first['id'], LessonVersion::findOrFail($partial['versionId'])->document['stages'][0]['blocks'][0]['media']['image']['assetId']);
        $this->getJson('/api/studio/media/'.$first['id'])->assertOk()->assertJsonPath('asset.usages', []);
        $usage = $this->getJson('/api/studio/media/'.$second['id'])->assertOk()->json('asset.usages');
        $this->assertCount(1, $usage);
        $this->assertSame('editor', $usage[0]['contentSource']);
        $this->assertSame($second['currentVersionId'], $usage[0]['versionId']);
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $partial['revision']])->assertOk()->json('lesson');
        $usage = $this->getJson('/api/studio/media/'.$second['id'])->assertOk()->json('asset.usages');
        $this->assertCount(1, $usage);
        $this->assertSame('snapshot', $usage[0]['contentSource']);
        $working = $released['document'];
        $working['stages'][0]['blocks'][0] = $this->image($first, true);
        $fork = $this->editorSave($released, $working);
        $oldUsage = $this->getJson('/api/studio/media/'.$second['id'])->assertOk()->json('asset.usages');
        $this->assertCount(1, $oldUsage);
        $this->assertSame($released['versionId'], $oldUsage[0]['lessonVersionId']);
        $newUsage = $this->getJson('/api/studio/media/'.$first['id'])->assertOk()->json('asset.usages');
        $this->assertCount(1, $newUsage);
        $this->assertSame('editor', $newUsage[0]['contentSource']);
        $this->assertSame($fork['versionId'], $newUsage[0]['lessonVersionId']);
        $preview = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/preview', ['expectedRevision' => $fork['revision'], 'document' => $fork['document'],
            'audience' => 'projector', 'locale' => 'de', 'stageId' => 'stage-a'])->assertOk()->json('preview');
        $this->assertSame('/media/owned/'.$first['id'].'/'.$first['currentVersionId'], $preview['stage']['blocks'][0]['resources']['image']);
        $this->get($preview['stage']['blocks'][0]['resources']['image'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $foreignOwner = (string) Str::uuid();
        $this->withSession(['studio_owner_key' => $foreignOwner]);
        $foreign = $this->upload('Foreign');
        $this->withSession(['studio_owner_key' => $this->editorOwner]);
        $working['stages'][0]['blocks'][0] = $this->image($foreign, true);
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $this->editorBody($fork, $working))->assertUnprocessable()->assertJsonPath('issues.0.code', 'invalid_media');
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/preview', ['expectedRevision' => $fork['revision'], 'document' => $working,
            'audience' => 'student', 'locale' => 'de', 'stageId' => 'stage-a'])->assertUnprocessable()->assertJsonPath('issues.0.code', 'invalid_media');
    }

    public function test_template_usage_reads_actual_partial_draft_origin_and_preserves_exact_released_usage(): void
    {
        $lesson = $this->editorLesson();
        $templates = [];
        foreach (['First', 'Second'] as $title) {
            $templates[] = $this->postJson('/api/studio/templates', ['lessonId' => $lesson['id'], 'expectedLessonRevision' => $lesson['revision'], 'blockId' => 'choice-a',
                'title' => $title, 'tags' => [], 'author' => 'Fixture', 'source' => 'Fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Fixture'])
                ->assertCreated()->json('template');
        }
        $working = $lesson['document'];
        $working['stages'][0]['blocks'][1]['origin'] = ['templateId' => $templates[0]['id'], 'versionId' => $templates[0]['currentVersionId']];
        $saved = $this->editorSave($lesson, $working);
        $working = $this->blankLocale($saved['document'], 'de');
        $working['stages'][0]['blocks'][1]['origin'] = ['templateId' => $templates[1]['id'], 'versionId' => $templates[1]['currentVersionId']];
        $saved = $this->editorSave($saved, $working);
        $this->getJson('/api/studio/templates/'.$templates[0]['id'])->assertOk()->assertJsonPath('template.usages', []);
        $this->getJson('/api/studio/templates/'.$templates[1]['id'])->assertOk()->assertJsonPath('template.usages.0.contentSource', 'editor')->assertJsonCount(1, 'template.usages');
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $saved['revision']])->assertOk()->json('lesson');
        $this->getJson('/api/studio/templates/'.$templates[1]['id'])->assertOk()->assertJsonPath('template.usages.0.contentSource', 'snapshot')->assertJsonCount(1, 'template.usages');
        $working = $released['document'];
        $working['stages'][0]['blocks'][1]['origin']['versionId'] = (string) Str::uuid();
        $this->editorSave($released, $working);
        $this->getJson('/api/studio/templates/'.$templates[1]['id'])->assertOk()->assertJsonPath('template.usages.0.lessonVersionId', $released['versionId'])->assertJsonCount(1, 'template.usages');
    }

    private function upload(string $title): array
    {
        return $this->post('/api/studio/media', ['title' => $title, 'tags' => '[]', 'author' => 'Fixture', 'source' => 'Fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Fixture',
            'file' => UploadedFile::fake()->image('fixture.png', 8, 8)], ['Accept' => 'application/json'])->assertCreated()->json('asset');
    }

    private function image(array $asset, bool $partial = false): array
    {
        return ['id' => 'image-a', 'type' => 'core.image', 'schemaVersion' => 1,
            'content' => ['ru' => ['alt' => 'Image RU', 'caption' => ''], 'de' => ['alt' => $partial ? '' : 'Image DE', 'caption' => '']],
            'media' => ['image' => ['assetId' => $asset['id'], 'versionId' => $asset['currentVersionId']]]];
    }
}
