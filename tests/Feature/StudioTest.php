<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

final class StudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_is_saved_with_normalized_document_and_server_version_identity(): void
    {
        $owner = (string) Str::uuid();
        $response = $this->withSession(['studio_owner_key' => $owner])->postJson('/api/studio/lessons', [
            'document' => $this->document(), 'owner_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonStructure(['lesson' => ['id', 'revision', 'status', 'versionId', 'document']]);
        $lesson = $response->json('lesson');
        $this->assertSame(1, $lesson['revision']);
        $this->assertSame('draft', $lesson['status']);
        $this->assertTrue(Str::isUuid($lesson['id']));
        $this->assertTrue(Str::isUuid($lesson['versionId']));
        $this->assertSame($lesson['versionId'], $lesson['document']['id']);
        $this->assertNotSame('client-document', $lesson['document']['id']);
        $this->assertSame(['layout' => 'vertical'], $lesson['document']['stages'][0]['config']);
        $this->assertSame(['format' => 'plain'], $lesson['document']['stages'][0]['blocks'][0]['config']);
        $this->assertSame($owner, LessonMaterial::findOrFail($lesson['id'])->owner_key);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->assertExactJson(['lesson' => $lesson]);
        $this->getJson('/api/studio/lessons')->assertOk()->assertJsonPath('lessons.0.title', 'Русское название')
            ->assertJsonCount(1, 'lessons')->assertJsonMissing(['owner_key' => $owner]);
    }

    public function test_guest_identity_is_created_without_a_supplied_owner(): void
    {
        $response = $this->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated();
        $owner = LessonMaterial::findOrFail($response->json('lesson.id'))->owner_key;
        $this->assertTrue(Str::isUuid($owner));
        $response->assertSessionHas('studio_owner_key', $owner);
        $this->getJson('/api/studio/lessons')->assertOk()->assertJsonCount(1, 'lessons');
    }

    public function test_another_guest_cannot_list_read_save_or_release_the_material(): void
    {
        $firstOwner = (string) Str::uuid();
        $lesson = $this->withSession(['studio_owner_key' => $firstOwner])
            ->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated()->json('lesson');
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/lessons')->assertOk()->assertExactJson(['lessons' => []]);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertNotFound()->assertExactJson(['error' => ['code' => 'not_found']]);
        $this->putJson('/api/studio/lessons/'.$lesson['id'], [
            'expectedRevision' => 1, 'document' => $this->document(), 'owner_key' => $firstOwner,
        ])->assertNotFound()->assertExactJson(['error' => ['code' => 'not_found']]);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', [
            'expectedRevision' => 1, 'owner_key' => $firstOwner,
        ])->assertNotFound()->assertExactJson(['error' => ['code' => 'not_found']]);
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertSame('draft', LessonVersion::findOrFail($lesson['versionId'])->status);
    }

    public function test_unknown_material_is_not_found(): void
    {
        $id = (string) Str::uuid();
        $this->getJson('/api/studio/lessons/'.$id)->assertNotFound();
        $this->putJson('/api/studio/lessons/'.$id, [])->assertNotFound();
        $this->postJson('/api/studio/lessons/'.$id.'/release', [])->assertNotFound();
    }

    public function test_invalid_and_unknown_block_documents_are_rejected_without_writes(): void
    {
        $document = $this->document();
        $document['stages'][0]['blocks'][0]['type'] = 'untrusted.script';
        $this->postJson('/api/studio/lessons', ['document' => $document])->assertUnprocessable()
            ->assertExactJson(['error' => ['code' => 'invalid_document']]);
        $document = $this->document();
        unset($document['content']['de']);
        $this->postJson('/api/studio/lessons', ['document' => $document])->assertUnprocessable()
            ->assertExactJson(['error' => ['code' => 'invalid_document']]);
        $this->assertDatabaseCount('lesson_materials', 0);
        $this->assertDatabaseCount('lesson_versions', 0);
    }

    public function test_unknown_media_and_wrong_asset_version_pair_are_rejected(): void
    {
        foreach ([['builtin-conversation', 'unknown-version'], ['unknown-asset', 'builtin-conversation-v1']] as [$asset, $version]) {
            $this->postJson('/api/studio/lessons', ['document' => $this->imageDocument($asset, $version)])
                ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'invalid_media']]);
        }
        $this->assertDatabaseCount('lesson_materials', 0);
        $this->postJson('/api/studio/lessons', ['document' => $this->imageDocument('builtin-conversation', 'builtin-conversation-v1')])
            ->assertCreated()->assertJsonPath('lesson.document.stages.0.blocks.0.config.fit', 'contain');
    }

    public function test_stale_revision_cannot_overwrite_a_newer_draft_or_release_it(): void
    {
        $lesson = $this->createLesson();
        $document = $lesson['document'];
        $document['content']['ru']['title'] = 'Изменение первого окна';
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $document])
            ->assertOk()->assertJsonPath('lesson.revision', 2)->assertJsonPath('lesson.versionId', $lesson['versionId']);
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $this->document()])
            ->assertConflict()->assertExactJson(['error' => ['code' => 'revision_conflict']]);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 1])
            ->assertConflict()->assertExactJson(['error' => ['code' => 'revision_conflict']]);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()
            ->assertJsonPath('lesson.document.content.ru.title', 'Изменение первого окна')->assertJsonPath('lesson.status', 'draft');
        $this->assertDatabaseCount('lesson_versions', 1);
    }

    public function test_released_snapshot_is_immutable_and_next_edit_creates_a_new_draft(): void
    {
        $lesson = $this->createLesson();
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 1])
            ->assertOk()->assertJsonPath('lesson.status', 'released')->assertJsonPath('lesson.revision', 2)->json('lesson');
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 2])
            ->assertOk()->assertExactJson(['lesson' => $released]);
        $document = $released['document'];
        $document['content']['ru']['title'] = 'Следующий черновик';
        $draft = $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 2, 'document' => $document])
            ->assertOk()->assertJsonPath('lesson.status', 'draft')->assertJsonPath('lesson.revision', 3)->json('lesson');
        $this->assertNotSame($released['versionId'], $draft['versionId']);
        $this->assertSame($draft['versionId'], $draft['document']['id']);
        $this->assertSame($released['document'], LessonVersion::findOrFail($released['versionId'])->document);
        $this->assertSame('released', LessonVersion::findOrFail($released['versionId'])->status);
        $this->assertDatabaseCount('lesson_versions', 2);
        $this->assertSame('text-1', $draft['document']['stages'][0]['blocks'][0]['id']);
    }

    public function test_model_refuses_to_modify_a_released_version(): void
    {
        $lesson = $this->createLesson();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 1])->assertOk();
        $version = LessonVersion::findOrFail($lesson['versionId']);
        $version->document = $this->document();
        $this->expectException(LogicException::class);
        $version->save();
    }

    public function test_save_and_release_validate_media_and_failed_operations_preserve_revision(): void
    {
        $lesson = $this->createLesson();
        $invalid = $this->imageDocument('builtin-conversation', 'missing-version');
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $invalid])
            ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'invalid_media']]);
        $this->assertSame($lesson['document'], LessonVersion::findOrFail($lesson['versionId'])->document);
        // Simulate a media reference becoming unavailable after the draft was stored.
        $invalid['id'] = $lesson['versionId'];
        LessonVersion::findOrFail($lesson['versionId'])->update(['document' => $invalid]);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 1])
            ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'invalid_media']]);
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertSame('draft', LessonVersion::findOrFail($lesson['versionId'])->status);
    }

    public function test_revision_is_required_and_title_uses_document_default_locale(): void
    {
        $document = $this->document();
        $document['defaultLocale'] = 'de';
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $this->getJson('/api/studio/lessons')->assertOk()->assertJsonPath('lessons.0.title', 'Deutscher Titel');
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['document' => $document])->assertUnprocessable();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 0])->assertUnprocessable();
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
    }

    public function test_authored_empty_notes_captions_and_text_whitespace_survive_create_and_save(): void
    {
        $document = $this->document();
        $image = $this->imageDocument('builtin-conversation', 'builtin-conversation-v1')['stages'][0]['blocks'][0];
        foreach ($document['locales'] as $locale) {
            $document['stages'][0]['content'][$locale]['notes'] = '';
            $document['stages'][0]['blocks'][0]['content'][$locale]['text'] = "\n  Authored text with spaces  \n";
            $image['content'][$locale]['caption'] = '';
        }
        $document['stages'][0]['blocks'][] = $image;
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        foreach ($document['locales'] as $locale) {
            $this->assertSame('', $lesson['document']['stages'][0]['content'][$locale]['notes']);
            $this->assertSame('', $lesson['document']['stages'][0]['blocks'][1]['content'][$locale]['caption']);
            $this->assertSame("\n  Authored text with spaces  \n", $lesson['document']['stages'][0]['blocks'][0]['content'][$locale]['text']);
        }

        $updated = $lesson['document'];
        foreach ($document['locales'] as $locale) {
            $updated['stages'][0]['blocks'][0]['content'][$locale]['text'] = "  Revised text\nSecond line  \n";
        }
        $saved = $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $updated])
            ->assertOk()->assertJsonPath('lesson.revision', 2)->json('lesson');
        $this->assertSame($updated, $saved['document']);
        $this->assertSame($updated, LessonVersion::findOrFail($saved['versionId'])->document);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->assertExactJson(['lesson' => $saved]);
    }

    public function test_required_whitespace_only_text_is_still_invalid_without_form_transforms(): void
    {
        $document = $this->document();
        $document['stages'][0]['blocks'][0]['content']['ru']['text'] = " \t\n  ";
        $this->postJson('/api/studio/lessons', ['document' => $document])->assertUnprocessable()
            ->assertExactJson(['error' => ['code' => 'invalid_document']]);
        $this->assertDatabaseCount('lesson_materials', 0);
        $lesson = $this->createLesson();
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => 1, 'document' => $document])
            ->assertUnprocessable()->assertExactJson(['error' => ['code' => 'invalid_document']]);
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertSame($lesson['document'], LessonVersion::findOrFail($lesson['versionId'])->document);
    }

    private function createLesson(): array
    {
        return $this->withSession(['studio_owner_key' => (string) Str::uuid()])
            ->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated()->json('lesson');
    }

    private function document(): array
    {
        return ['id' => 'client-document', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'],
            'content' => ['ru' => ['title' => 'Русское название'], 'de' => ['title' => 'Deutscher Titel']],
            'stages' => [['id' => 'stage-1', 'content' => ['ru' => ['title' => 'Этап'], 'de' => ['title' => 'Schritt']],
                'blocks' => [['id' => 'text-1', 'type' => 'core.text', 'schemaVersion' => 1,
                    'content' => ['ru' => ['text' => 'Текст'], 'de' => ['text' => 'Text']]]]]]];
    }

    private function imageDocument(string $asset, string $version): array
    {
        $document = $this->document();
        $document['stages'][0]['blocks'][0] = ['id' => 'image-1', 'type' => 'core.image', 'schemaVersion' => 1,
            'content' => ['ru' => ['alt' => 'Разговор'], 'de' => ['alt' => 'Gespräch']],
            'media' => ['image' => ['assetId' => $asset, 'versionId' => $version]]];

        return $document;
    }
}
