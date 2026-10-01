<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\History\RetentionService;
use App\Application\Studio\EditorSaveRequest;
use App\Application\Studio\SaveReceiptRetention;
use App\Application\Studio\StudioService;
use App\Models\GuestWorkspaceClaim;
use App\Models\LessonMaterial;
use App\Models\LessonSaveReceipt;
use App\Models\LessonVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\EditorFixture;
use Tests\TestCase;

final class EditorSaveTest extends TestCase
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

    public function test_partial_and_zero_ready_drafts_persist_exact_strings_without_changing_strict_baseline(): void
    {
        $lesson = $this->editorLesson();
        $baseline = LessonVersion::findOrFail($lesson['versionId'])->document;
        $working = $this->blankLocale($lesson['document'], 'de');
        $working['content']['ru']['title'] = 'Working title';
        $saved = $this->editorSave($lesson, $working);
        $this->assertSame(['ru'], $saved['readiness']['readyLocales']);
        $this->assertSame('draft', $saved['readiness']['locales'][1]['status']);
        $this->assertSame($working, $saved['document']);
        $this->assertSame($baseline, LessonVersion::findOrFail($lesson['versionId'])->document);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->assertExactJson(['lesson' => $saved]);
        $this->getJson('/api/studio/lessons')->assertOk()->assertJsonPath('lessons.0.title', 'Working title');
        $zero = $this->editorSave($saved, $this->blankLocale($working, 'ru'));
        $this->assertSame([], $zero['readiness']['readyLocales']);
        $this->getJson('/api/studio/lessons')->assertOk()->assertJsonPath('lessons.0.title', '');
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => $zero['revision'], 'document' => $baseline])
            ->assertConflict()->assertJsonPath('error.code', 'editor_update_required')->assertJsonPath('lesson.document', $zero['document']);
        $this->assertDatabaseCount('lesson_save_receipts', 2);
    }

    public function test_first_create_remains_strict_and_partial_input_does_not_create_synthetic_baseline(): void
    {
        $this->postJson('/api/studio/lessons', ['document' => $this->blankLocale($this->editorDocument(), 'de')])
            ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_document');
        $this->assertDatabaseCount('lesson_materials', 0);
        $this->assertDatabaseCount('lesson_versions', 0);
        $this->assertDatabaseCount('lesson_save_receipts', 0);
    }

    public function test_uuid_replay_is_canonical_once_and_returns_fresh_lesson_after_later_save(): void
    {
        $lesson = $this->editorLesson();
        $body = $this->editorBody($lesson);
        $url = '/api/studio/lessons/'.$lesson['id'];
        $first = $this->putJson($url, $body)->assertOk()->json();
        $reordered = ['document' => array_reverse($body['document'], true), 'expectedRevision' => $body['expectedRevision'], 'saveId' => strtoupper($body['saveId'])];
        $this->putJson($url, $reordered)->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('lesson_save_receipts', 1);
        $this->assertSame(2, LessonMaterial::findOrFail($lesson['id'])->revision);
        $working = $first['lesson']['document'];
        $working['content']['ru']['title'] = 'Later accepted edit';
        $later = $this->editorSave($first['lesson'], $working);
        $ack = $this->putJson($url, $body)->assertOk()->json();
        $this->assertSame(2, $ack['appliedRevision']);
        $this->assertSame(3, $ack['lesson']['revision']);
        $this->assertSame($later, $ack['lesson']);
        $changed = $body;
        $changed['document']['content']['ru']['title'] = 'Different same UUID';
        $this->putJson($url, $changed)->assertConflict()->assertJsonPath('error.code', 'save_conflict')->assertJsonPath('lesson', $later);
        $body['saveId'] = (string) Str::uuid();
        $this->putJson($url, $body)->assertConflict()->assertJsonPath('error.code', 'revision_conflict')->assertJsonPath('lesson', $later);
        $this->assertDatabaseCount('lesson_save_receipts', 2);
    }

    public function test_fingerprint_distinguishes_original_object_list_integer_float_whitespace_and_list_order(): void
    {
        $id = (string) Str::uuid();
        $fingerprint = fn (string $value) => EditorSaveRequest::fromJson('{"saveId":"'.$id.'","expectedRevision":1,"document":{"value":'.$value.'}}')->fingerprint;
        $this->assertNotSame($fingerprint('{}'), $fingerprint('[]'));
        $this->assertNotSame($fingerprint('1'), $fingerprint('1.0'));
        $this->assertNotSame($fingerprint('" x "'), $fingerprint('"x"'));
        $this->assertNotSame($fingerprint('[1,2]'), $fingerprint('[2,1]'));
        $this->assertSame($fingerprint('{"a":1,"b":2}'), $fingerprint('{"b":2,"a":1}'));
        $this->assertSame($fingerprint('"ä"'), $fingerprint('"\u00e4"'));
    }

    public function test_invalid_shape_media_envelope_and_foreign_owner_never_write_receipts_or_revision(): void
    {
        $lesson = $this->editorLesson();
        foreach (['unknown_type', 'bad_solution', 'foreign_media', 'missing_locale'] as $case) {
            $document = $this->blankLocale($lesson['document'], 'de');
            if ($case === 'unknown_type') {
                $document['stages'][0]['blocks'][0]['type'] = 'unregistered';
            } elseif ($case === 'bad_solution') {
                $document['stages'][0]['blocks'][1]['solution']['optionId'] = 'unknown';
            } elseif ($case === 'missing_locale') {
                unset($document['stages'][0]['blocks'][0]['content']['de']);
            } else {
                $document['stages'][0]['blocks'][0] = ['id' => 'image', 'type' => 'core.image', 'schemaVersion' => 1,
                    'content' => ['ru' => ['alt' => 'Image'], 'de' => ['alt' => '']], 'media' => ['image' => ['assetId' => (string) Str::uuid(), 'versionId' => (string) Str::uuid()]]];
            }
            $response = $this->putJson('/api/studio/lessons/'.$lesson['id'], $this->editorBody($lesson, $document))->assertUnprocessable()
                ->assertJsonPath('error.code', 'invalid_editor_document')->assertJsonStructure(['issues' => [['code', 'path']]]);
            $this->assertStringNotContainsString('Private stage note', $response->getContent());
        }
        $body = $this->editorBody($lesson);
        $body['unexpected'] = true;
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $body)->assertUnprocessable();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $body)->assertNotFound();
        $this->assertDatabaseCount('lesson_save_receipts', 0);
        $this->assertSame(1, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertNull(LessonVersion::findOrFail($lesson['versionId'])->editor_draft);
    }

    public function test_expired_receipts_stop_acknowledging_and_bounded_cleanup_does_not_mutate_versions(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 12:00:00.123456 UTC');
        $lesson = $this->editorLesson();
        $body = $this->editorBody($lesson);
        $saved = $this->putJson('/api/studio/lessons/'.$lesson['id'], $body)->assertOk()->json('lesson');
        $version = LessonVersion::findOrFail($saved['versionId'])->getAttributes();
        $created = LessonSaveReceipt::firstOrFail()->created_at;
        CarbonImmutable::setTestNow($created->addDays(30)->subMicrosecond());
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $body)->assertOk();
        CarbonImmutable::setTestNow($created->addDays(30));
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $body)->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $retention = app(RetentionService::class);
        $this->assertSame(1, $retention->run(true, 1)['saveReceiptsDeleted']);
        $this->assertDatabaseCount('lesson_save_receipts', 1);
        $this->assertSame(1, $retention->run(false, 1)['saveReceiptsDeleted']);
        $this->assertSame(0, $retention->run(false, 1)['saveReceiptsDeleted']);
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $body)->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $this->assertSame($version, LessonVersion::findOrFail($saved['versionId'])->getAttributes());
    }

    public function test_receipt_insert_failure_rolls_back_released_fork_and_revision(): void
    {
        $lesson = $this->editorLesson();
        $released = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/release', ['expectedRevision' => 1])->assertOk()->json('lesson');
        $before = LessonVersion::findOrFail($released['versionId'])->getAttributes();
        $body = $this->editorBody($released, $this->blankLocale($released['document'], 'de'));
        LessonSaveReceipt::creating(fn () => throw new RuntimeException('Injected fixture receipt failure'));
        try {
            app(StudioService::class)->saveEditor($this->editorOwner, $lesson['id'], EditorSaveRequest::fromJson(json_encode($body, JSON_THROW_ON_ERROR)));
            $this->fail('Receipt insert failure must roll back the fork.');
        } catch (RuntimeException $failure) {
            $this->assertSame('Injected fixture receipt failure', $failure->getMessage());
        } finally {
            LessonSaveReceipt::flushEventListeners();
        }
        $this->assertSame(2, LessonMaterial::findOrFail($lesson['id'])->revision);
        $this->assertSame($released['versionId'], LessonMaterial::findOrFail($lesson['id'])->current_version_id);
        $this->assertSame($before, LessonVersion::findOrFail($released['versionId'])->getAttributes());
        $this->assertDatabaseCount('lesson_versions', 1);
        $this->assertDatabaseCount('lesson_save_receipts', 0);
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $body)->assertOk()->assertJsonPath('appliedRevision', 3);
    }

    public function test_receipt_cleanup_is_bounded_and_retries_claim_changed_owner_without_touching_workspace(): void
    {
        CarbonImmutable::setTestNow('2026-10-01 12:00:00 UTC');
        $lesson = $this->editorLesson();
        $saved = $this->editorSave($lesson, $lesson['document']);
        $saved = $this->editorSave($saved, $saved['document']);
        $old = LessonSaveReceipt::orderBy('id')->get();
        CarbonImmutable::setTestNow('2026-10-31 12:00:00 UTC');
        $saved = $this->editorSave($saved, $saved['document']);
        $before = LessonVersion::findOrFail($saved['versionId'])->getAttributes();
        $user = User::factory()->create(['owner_key' => (string) Str::uuid()]);
        $connection = DB::connection();
        $original = $connection->getEventDispatcher();
        $isolated = clone $original;
        $connection->setEventDispatcher($isolated);
        $moved = false;
        $isolated->listen(QueryExecuted::class, function (QueryExecuted $query) use (&$moved, $lesson, $user): void {
            if (! $moved && preg_match('/select ["`]?owner_key["`]? from ["`]?lesson_materials/', $query->sql)) {
                // Controlled discovery interleaving; real concurrent saves use MariaDB workers.
                $moved = true;
                DB::table('lesson_materials')->where('id', $lesson['id'])->update(['owner_key' => $user->owner_key]);
                GuestWorkspaceClaim::create(['source_owner_key' => $this->editorOwner, 'target_user_id' => $user->id, 'target_owner_key' => $user->owner_key, 'result' => ['status' => 'claimed']]);
            }
        });
        try {
            $this->assertSame(1, app(SaveReceiptRetention::class)->run(false, 1));
        } finally {
            $connection->setEventDispatcher($original);
        }
        $this->assertTrue($moved);
        $this->assertDatabaseMissing('lesson_save_receipts', ['id' => $old[0]->id]);
        $this->assertDatabaseHas('lesson_save_receipts', ['id' => $old[1]->id]);
        $this->assertSame(1, app(SaveReceiptRetention::class)->run(false, 1));
        $this->assertSame(0, app(SaveReceiptRetention::class)->run(false, 1));
        $this->assertDatabaseCount('lesson_save_receipts', 1);
        $this->assertSame($before, LessonVersion::findOrFail($saved['versionId'])->getAttributes());
        $this->assertSame($saved['revision'], LessonMaterial::findOrFail($saved['id'])->revision);
        $this->assertDatabaseHas('guest_workspace_claims', ['source_owner_key' => $this->editorOwner]);
        $this->assertDatabaseHas('lesson_materials', ['id' => $saved['id'], 'owner_key' => $user->owner_key]);
    }
}
