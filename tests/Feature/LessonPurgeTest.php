<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Account\AuthService;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Studio\StudioService;
use App\Models\CatalogEntry;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\TeachingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\HistoryFixture;
use Tests\TestCase;

final class LessonPurgeTest extends TestCase
{
    use HistoryFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->historyIdentity();
    }

    private function trash(array $lesson): array
    {
        return $this->postJson('/api/studio/lessons/'.$lesson['id'].'/archive', ['expectedRevision' => $lesson['revision'], 'archived' => true])->assertOk()->json('lesson');
    }

    private function selected(array ...$lessons): array
    {
        return array_map(fn (array $lesson): array => ['id' => $lesson['id'], 'expectedRevision' => $lesson['revision']], $lessons);
    }

    public function test_archive_timestamp_records_the_transition_and_changes_only_after_restoration_and_another_archive(): void
    {
        $lesson = $this->historyLesson();
        $this->assertArrayHasKey('archivedAt', $this->getJson('/api/studio/lessons')->json('lessons.0'));
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', 'UTC'));
        $trashed = $this->trash($lesson);
        $this->assertSame('2026-10-01T12:00:00.000000Z', $trashed['archivedAt']);
        $this->travelTo(CarbonImmutable::parse('2026-10-01 13:00:00', 'UTC'));
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/archive', ['expectedRevision' => 2, 'archived' => true])->assertOk()->assertJsonPath('lesson.archivedAt', $trashed['archivedAt']);
        $restored = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/archive', ['expectedRevision' => 2, 'archived' => false])->assertOk()->assertJsonPath('lesson.archivedAt', null)->json('lesson');
        $again = $this->trash($restored);
        $this->assertSame('2026-10-01T13:00:00.000000Z', $again['archivedAt']);
    }

    public function test_single_permanent_removal_is_irrecoverable_in_authoring_and_preserves_every_snapshot_and_save_receipt(): void
    {
        $lesson = $this->historyLesson();
        $save = ['saveId' => (string) Str::uuid(), 'expectedRevision' => 1, 'document' => $lesson['document']];
        $saved = $this->putJson('/api/studio/lessons/'.$lesson['id'], $save)->assertOk()->json('lesson');
        $before = LessonVersion::query()->get()->map->getAttributes()->all();
        $trashed = $this->trash($saved);
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/purge', ['expectedRevision' => $trashed['revision']])->assertOk()->assertExactJson(['deletedIds' => [$lesson['id']]]);
        $material = LessonMaterial::findOrFail($lesson['id']);
        $this->assertNotNull($material->purged_at);
        $this->assertSame($trashed['revision'] + 1, $material->revision);
        $this->assertSame($before, LessonVersion::query()->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('lesson_save_receipts', 1);
        $this->getJson('/api/studio/lessons')->assertExactJson(['lessons' => []]);
        $this->getJson('/api/studio/lessons?archived=1')->assertExactJson(['lessons' => []]);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertNotFound();
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions')->assertNotFound();
        $this->getJson('/api/studio/lessons/'.$lesson['id'].'/versions/'.$lesson['versionId'])->assertNotFound();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/archive', ['expectedRevision' => $material->revision, 'archived' => false])->assertNotFound();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/purge', ['expectedRevision' => $material->revision])->assertNotFound();
        $this->putJson('/api/studio/lessons/'.$lesson['id'], $save)->assertNotFound();
        foreach (['release', 'sessions', 'rehearsals'] as $action) {
            $this->postJson('/api/studio/lessons/'.$lesson['id'].'/'.$action, ['expectedRevision' => $material->revision])->assertNotFound();
        }
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/favorite', ['favorite' => true])->assertNotFound();
    }

    public function test_running_class_answers_and_history_survive_permanent_removal_and_finish_removes_only_the_dashboard_entry(): void
    {
        $session = $this->historySession();
        $this->historyParticipant($session);
        $this->historyCommand($session, 'block.open', ['blockId' => 'free']);
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'free', 'value' => ['text' => 'Private student answer']])->assertOk();
        $version = LessonVersion::findOrFail($session['document']['id']);
        $versions = LessonVersion::query()->get()->map->getAttributes()->all();
        $before = TeachingSession::findOrFail($session['id'])->getAttributes();
        $trashed = $this->trash(['id' => $version->lesson_material_id, 'revision' => $version->material->revision]);
        $this->postJson('/api/studio/lessons/'.$trashed['id'].'/purge', ['expectedRevision' => $trashed['revision']])->assertOk();
        $this->assertSame($before, TeachingSession::findOrFail($session['id'])->getAttributes());
        $this->assertSame($versions, LessonVersion::query()->get()->map->getAttributes()->all());
        $this->getJson('/api/studio/sessions?status=active')->assertJsonCount(1, 'sessions')->assertJsonPath('sessions.0.lessonPurged', true);
        $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonPath('session.ownAnswers.0.value.text', 'Private student answer');
        $this->getJson('/api/projection/'.basename($session['projectorUrl']))->assertOk();
        $this->postJson('/api/studio/sessions/'.$session['id'].'/again', [])->assertNotFound();
        $this->historyCommand($session, 'finish');
        $this->getJson('/api/studio/sessions?status=active')->assertExactJson(['sessions' => [], 'nextCursor' => null]);
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()->assertJsonPath('history.status', 'finished')->assertJsonPath('history.lessonPurged', true)->assertJsonPath('history.answers.0.value.text', 'Private student answer')->assertJsonPath('history.snapshotDocument', $version->document);
        $this->assertDatabaseCount('teaching_sessions', 1);
        $this->assertDatabaseCount('session_participants', 1);
        $this->assertDatabaseCount('session_answers', 1);
    }

    public function test_bulk_is_an_explicit_owner_snapshot_and_newly_trashed_copies_are_not_removed(): void
    {
        $first = $this->trash($this->historyLesson());
        $second = $this->trash($this->historyLesson());
        $selected = $this->selected($first, $second);
        $new = $this->trash($this->historyLesson());
        $this->postJson('/api/studio/lessons/trash/purge', ['lessons' => $selected])->assertOk()->assertExactJson(['deletedIds' => [$first['id'], $second['id']]]);
        $this->getJson('/api/studio/lessons?archived=1')->assertJsonCount(1, 'lessons')->assertJsonPath('lessons.0.id', $new['id']);
        $this->assertNull(LessonMaterial::findOrFail($new['id'])->purged_at);
        $this->assertDatabaseCount('lesson_materials', 3);
        $this->assertDatabaseCount('lesson_versions', 3);
    }

    public function test_stale_restore_foreign_owner_and_active_copy_block_the_whole_bulk(): void
    {
        $first = $this->trash($this->historyLesson());
        $second = $this->trash($this->historyLesson());
        $path = '/api/studio/lessons/trash/purge';
        $this->postJson($path, ['lessons' => [['id' => $first['id'], 'expectedRevision' => $first['revision']], ['id' => $second['id'], 'expectedRevision' => 1]]])->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $this->postJson('/api/studio/lessons/'.$second['id'].'/archive', ['expectedRevision' => $second['revision'], 'archived' => false])->assertOk();
        $this->postJson($path, ['lessons' => $this->selected($first, $second)])->assertConflict();
        $this->postJson($path, ['lessons' => [['id' => $first['id'], 'expectedRevision' => 2], ['id' => $second['id'], 'expectedRevision' => 3]]])->assertConflict()->assertJsonPath('error.code', 'invalid_state');
        $this->assertNull(LessonMaterial::findOrFail($first['id'])->purged_at);
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->postJson('/api/studio/lessons/'.$first['id'].'/purge', ['expectedRevision' => 2])->assertNotFound();
        $this->postJson($path, ['lessons' => $this->selected($first)])->assertNotFound();
        $this->assertNull(LessonMaterial::findOrFail($first['id'])->purged_at);
    }

    public function test_purge_envelopes_are_strict_bounded_and_duplicate_free(): void
    {
        $lesson = $this->trash($this->historyLesson());
        foreach ([[], ['expectedRevision' => '2'], ['expectedRevision' => 0], ['expectedRevision' => 2, 'confirm' => true]] as $body) {
            $this->postJson('/api/studio/lessons/'.$lesson['id'].'/purge', $body)->assertUnprocessable();
        }
        $selected = $this->selected($lesson);
        foreach ([[], ['lessons' => 'all'], ['lessons' => [$selected[0], $selected[0]]], ['lessons' => [['id' => 'bad', 'expectedRevision' => 2]]], ['lessons' => [['id' => $lesson['id'], 'expectedRevision' => '2']]], ['lessons' => $selected, 'owner_key' => $this->historyOwner], ['lessons' => array_fill(0, 101, $selected[0])]] as $body) {
            $this->postJson('/api/studio/lessons/trash/purge', $body)->assertUnprocessable();
        }
        $this->postJson('/api/studio/lessons/trash/purge', ['lessons' => []])->assertOk()->assertExactJson(['deletedIds' => []]);
        $this->assertNull(LessonMaterial::findOrFail($lesson['id'])->purged_at);
    }

    public function test_late_bulk_failure_rolls_back_all_markers_and_revisions(): void
    {
        $first = $this->trash($this->historyLesson());
        $second = $this->trash($this->historyLesson());
        $before = LessonMaterial::query()->orderBy('id')->get()->map->getAttributes()->all();
        LessonMaterial::updating(function (LessonMaterial $material) use ($second): void {
            if ($material->id === $second['id'] && $material->purged_at !== null) {
                throw new RuntimeException('Simulated interruption.');
            }
        });
        try {
            app(StudioService::class)->purge($this->historyOwner, $this->selected($first, $second));
            $this->fail('Expected the transaction interruption.');
        } catch (RuntimeException $failure) {
            $this->assertSame('Simulated interruption.', $failure->getMessage());
            $this->assertSame($before, LessonMaterial::query()->orderBy('id')->get()->map->getAttributes()->all());
        }
    }

    public function test_catalog_pinned_source_cannot_be_permanently_removed(): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        $material = $entry->version->material;
        $this->withSession(['studio_owner_key' => $material->owner_key]);
        $trashed = $this->trash(['id' => $material->id, 'revision' => $material->revision]);
        $before = CatalogEntry::findOrFail($entry->id)->getAttributes();
        $this->postJson('/api/studio/lessons/'.$material->id.'/purge', ['expectedRevision' => $trashed['revision']])->assertConflict()->assertJsonPath('error.code', 'catalog_source_protected');
        $this->assertNull($material->fresh()->purged_at);
        $this->assertSame($before, CatalogEntry::findOrFail($entry->id)->getAttributes());
    }

    public function test_claim_preserves_deleted_history_ownership_without_resurrecting_the_personal_copy(): void
    {
        $session = $this->historySession();
        $version = LessonVersion::findOrFail($session['document']['id']);
        $trashed = $this->trash(['id' => $version->lesson_material_id, 'revision' => $version->material->revision]);
        $this->postJson('/api/studio/lessons/'.$trashed['id'].'/purge', ['expectedRevision' => $trashed['revision']])->assertOk();
        $marker = LessonMaterial::findOrFail($trashed['id'])->purged_at;
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password, AuthService::GUEST_PROOF => $this->historyOwner]);
        $this->getJson('/api/account/guest-claim')->assertOk()->assertJsonPath('claim.counts.lessons', 0)->assertJsonPath('claim.counts.sessions', 1);
        $this->postJson('/api/account/guest-claim')->assertOk();
        $this->assertSame($user->refresh()->owner_key, LessonMaterial::findOrFail($trashed['id'])->owner_key);
        $this->assertEquals($marker, LessonMaterial::findOrFail($trashed['id'])->purged_at);
        $this->getJson('/api/studio/lessons?archived=1')->assertExactJson(['lessons' => []]);
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()->assertJsonPath('history.lessonPurged', true);
    }

    public function test_history_question_snapshot_is_owner_only_and_unavailable_after_details_expire(): void
    {
        $this->historyIdentity(account: true);
        $session = $this->historySession();
        $this->historyParticipant($session);
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()->assertJsonPath('history.snapshotDocument', TeachingSession::findOrFail($session['id'])->version->document);
        $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonMissingPath('session.snapshotDocument');
        $this->getJson('/api/projection/'.basename($session['projectorUrl']))->assertOk()->assertJsonMissingPath('session.snapshotDocument');
        $this->historyCommand($session, 'finish');
        $model = TeachingSession::findOrFail($session['id']);
        $this->travelTo($model->finished_at->addDays(30));
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertOk()->assertJsonPath('history.detailsAvailable', false)->assertJsonPath('history.snapshotDocument', null)->assertJsonCount(0, 'history.answers')->assertJsonCount(0, 'history.participants');
        $this->getJson('/api/participation/'.$session['id'])->assertNotFound();
        $this->getJson('/api/projection/'.basename($session['projectorUrl']))->assertNotFound();
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->withSession(['studio_owner_key' => (string) Str::uuid()]);
        $this->getJson('/api/studio/sessions/'.$session['id'].'/history')->assertNotFound();
    }
}
