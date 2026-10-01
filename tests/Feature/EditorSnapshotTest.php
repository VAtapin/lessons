<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\EditorFixture;
use Tests\TestCase;

final class EditorSnapshotTest extends TestCase
{
    use EditorFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editorIdentity();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_release_rehearsal_and_runtime_use_actual_ready_subset_and_never_baseline(): void
    {
        $lesson = $this->editorLesson();
        $working = $this->blankLocale($lesson['document'], 'de');
        $working['stages'][0]['blocks'][0]['content']['ru']['text'] = 'New actual editor text';
        $saved = $this->editorSave($lesson, $working);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $saved['revision'], 'locale' => 'de'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'translation_not_ready');
        $this->assertSame('draft', LessonVersion::findOrFail($lesson['versionId'])->status);
        $this->assertSame($saved['revision'], LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertDatabaseCount('teaching_sessions', 0);
        $rehearsal = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => $saved['revision']])->assertCreated()->json('session');
        $temporary = LessonVersion::findOrFail(TeachingSession::findOrFail($rehearsal['id'])->lesson_version_id);
        $this->assertSame(['ru'], $temporary->document['locales']);
        $this->assertNull($temporary->editor_draft);
        $this->assertSame('New actual editor text', $temporary->document['stages'][0]['blocks'][0]['content']['ru']['text']);
        $this->assertSame($saved['versionId'], LessonMaterial::findOrFail($lesson['id'])->current_version_id);
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $saved['revision']])->assertOk()->json('lesson');
        $version = LessonVersion::findOrFail($released['versionId']);
        $frozen = $version->getAttributes();
        $this->assertSame(['ru'], $version->document['locales']);
        $this->assertSame(['ru', 'de'], $version->editor_draft['locales']);
        $this->assertSame('', $released['document']['content']['de']['title']);
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $released['revision'], 'locale' => 'ru'])->assertCreated()->json('session');
        $edited = $released['document'];
        $edited['stages'][0]['blocks'][0]['content']['ru']['text'] = 'After release';
        $draft = $this->editorSave($released, $edited);
        $this->assertNotSame($released['versionId'], $draft['versionId']);
        $this->assertSame($draft['versionId'], $draft['document']['id']);
        $this->assertSame($draft['versionId'], LessonVersion::findOrFail($draft['versionId'])->document['id']);
        $this->assertSame($frozen, $version->refresh()->getAttributes());
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->assertJsonPath('session.publicStage.blocks.0.content.text', 'New actual editor text');
        $this->getJson('/api/studio/rehearsals/'.$rehearsal['id'].'/preview/student')->assertOk()->assertJsonPath('session.stage.blocks.0.content.text', 'New actual editor text');
        $version->editor_draft = $edited;
        $this->expectException(LogicException::class);
        $version->save();
    }

    public function test_default_ready_is_required_and_explicit_subsets_cannot_rewrite_released_versions(): void
    {
        $lesson = $this->editorLesson();
        $saved = $this->editorSave($lesson, $this->blankLocale($lesson['document'], 'ru'));
        foreach (['release', 'rehearsals', 'sessions'] as $action) {
            $this->postJson('/api/studio/lessons/'.$lesson['id'].'/'.$action, ['expectedRevision' => $saved['revision'], ...($action === 'release' ? [] : ['locale' => 'de'])])
                ->assertUnprocessable()->assertJsonPath('error.code', 'translation_not_ready')->assertJsonPath('readiness.readyLocales', ['de']);
        }
        $ready = $this->editorSave($saved, $lesson['document']);
        foreach ([[], ['ru', 'ru'], ['de'], ['ru', 'zz']] as $locales) {
            $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $ready['revision'], 'locales' => $locales])->assertUnprocessable();
        }
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $ready['revision'], 'locales' => ['ru']])->assertOk()->json('lesson');
        $this->assertSame(['ru'], LessonVersion::findOrFail($released['versionId'])->document['locales']);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $released['revision'], 'locales' => ['ru']])->assertOk()->assertExactJson(['lesson' => $released]);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $released['revision'], 'locales' => ['ru', 'de']])->assertConflict();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $released['revision'], 'locale' => 'de'])->assertUnprocessable();
        // A fresh rehearsal resolves all working ready locales without changing the release subset.
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => $released['revision'], 'locale' => 'de'])->assertCreated()->assertJsonPath('session.locale', 'de');
    }

    public function test_library_extracts_actual_ready_block_despite_partial_material_and_other_blocks(): void
    {
        $lesson = $this->editorLesson();
        $working = $this->blankLocale($lesson['document'], 'de');
        $working['content']['ru']['title'] = '';
        $working['stages'][0]['content']['ru']['title'] = '';
        $working['stages'][0]['blocks'][0]['content']['ru']['text'] = '';
        $working['stages'][0]['blocks'][1]['content']['ru']['question'] = 'Actual saved question';
        $saved = $this->editorSave($lesson, $working);
        $body = ['lessonId' => $lesson['id'], 'expectedLessonRevision' => $saved['revision'], 'blockId' => 'choice-a',
            'title' => 'Ready actual block', 'tags' => [], 'author' => 'Fixture', 'source' => 'Fixture', 'rightsBasis' => 'self_created', 'usageRights' => 'Fixture'];
        $template = $this->postJson('/api/studio/templates', $body)->assertCreated()->json('template');
        $this->assertSame(['ru'], $template['versions'][0]['locales']);
        $this->assertSame('Actual saved question', $template['versions'][0]['block']['content']['ru']['question']);
        $body['blockId'] = 'text-a';
        $this->postJson('/api/studio/templates', $body)->assertUnprocessable()->assertJsonPath('error.code', 'translation_not_ready');
        $body['blockId'] = 'nonexisting';
        $this->postJson('/api/studio/templates', $body)->assertNotFound();
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions/'.$saved['versionId'])->assertOk()
            ->assertJsonPath('version.document.content.ru.title', 'Actual RU')->assertJsonPath('version.editorDocument.content.ru.title', '')
            ->assertJsonPath('version.readiness.readyLocales', []);
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions/'.$saved['versionId'])->assertNotFound();
    }

    public function test_releasing_same_locale_set_in_different_request_order_is_idempotent(): void
    {
        $lesson = $this->editorLesson();
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 1])->assertOk()->json('lesson');
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => $released['revision'], 'locales' => ['de', 'ru']])
            ->assertOk()->assertExactJson(['lesson' => $released]);
    }

    public function test_rehearsal_history_exposes_immutable_owner_snapshot_for_labels_without_authoring_version_access(): void
    {
        $lesson = $this->editorLesson();
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/rehearsals', ['expectedRevision' => 1])->assertCreated()->json('session');
        $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', ['expectedRevision' => 1, 'commandId' => (string) Str::uuid(), 'action' => 'finish', 'payload' => []])->assertOk();
        $model = TeachingSession::findOrFail($session['id']);
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()
            ->assertJsonPath('history.snapshotDocument.stages.0.blocks.1.content.ru.options.0.text', 'A')
            ->assertJsonPath('history.snapshotDocument.id', $model->lesson_version_id)->assertJsonMissing(['joinCode' => $model->join_code]);
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions/'.$model->lesson_version_id)->assertNotFound();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertNotFound();
        $this->withSession(['studio_owner_key' => $this->editorOwner]);
        CarbonImmutable::setTestNow($model->created_at->toImmutable()->addDays(7));
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertNotFound();
    }
}
