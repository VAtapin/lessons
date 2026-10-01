<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Runtime\RuntimeService;
use App\Http\Controllers\Runtime\RuntimeController;
use App\Models\SessionAnswer;
use App\Models\SessionBlockState;
use App\Models\SessionCommandReceipt;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

final class InteractiveRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        $this->identity($this->owner);
    }

    public function test_lazy_states_transitions_reveal_and_navigation_preserve_attempts_and_answers(): void
    {
        $session = $this->start();
        $this->assertSame(['open', 'prepared', 'prepared', 'prepared', 'prepared', 'prepared', 'open', 'open', 'open'], array_column($session['blockStates'], 'status'));
        $this->projector($session)->assertOk();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk();
        $this->assertDatabaseCount('session_block_states', 0);
        $this->join($session);
        $this->submit($session, 'multiple', ['optionIds' => ['a', 'b']])->assertConflict()->assertJsonPath('error.code', 'invalid_state');
        $this->execute($session, 'block.open', ['blockId' => 'multiple']);
        $this->submit($session, 'multiple', ['optionIds' => ['b', 'a']])->assertOk()->assertJsonPath('session.revision', 2)
            ->assertJsonPath('session.ownAnswers.0.value.optionIds', ['a', 'b'])->assertJsonPath('session.ownAnswers.0.grade', null);
        $this->submit($session, 'multiple', ['optionIds' => ['a', 'b']])->assertOk();
        $this->assertSame(1, SessionAnswer::firstOrFail()->revision);
        $this->projector($session)->assertJsonMissingPath('session.stage.blocks.1.runtime.results');
        $this->execute($session, 'block.close', ['blockId' => 'multiple']);
        $this->submit($session, 'multiple', ['optionIds' => ['a']])->assertConflict();
        $this->execute($session, 'block.open', ['blockId' => 'multiple']);
        $this->execute($session, 'block.close', ['blockId' => 'multiple']);
        $this->execute($session, 'block.reveal', ['blockId' => 'multiple']);
        $this->projector($session)->assertJsonPath('session.stage.blocks.1.runtime.results.optionIds', ['a', 'b']);
        $this->getJson('/api/participation/'.$session['id'])->assertJsonPath('session.ownAnswers.0.grade', true);
        $this->command($session, 'block.open', ['blockId' => 'multiple'])->assertConflict()->assertJsonPath('error.code', 'invalid_state');
        $this->execute($session, 'stage', ['stageId' => 'second']);
        $this->command($session, 'block.close', ['blockId' => 'multiple'])->assertUnprocessable();
        $this->execute($session, 'stage', ['stageId' => 'first']);
        $this->getJson('/api/participation/'.$session['id'])->assertJsonPath('session.stage.blocks.1.runtime.status', 'revealed')
            ->assertJsonPath('session.ownAnswers.0.attemptNo', 1);
        $this->assertDatabaseCount('session_answers', 1);
        $this->assertDatabaseCount('session_block_states', 1);
    }

    public function test_answer_forms_are_strict_and_legacy_rows_are_read_without_backfill(): void
    {
        $session = $this->start();
        $participant = $this->join($session);
        SessionAnswer::create(['teaching_session_id' => $session['id'], 'session_participant_id' => $participant['id'], 'block_id' => 'single', 'option_id' => 'a']);
        $this->getJson('/api/participation/'.$session['id'])->assertJsonPath('session.ownAnswers.0.optionId', 'a')
            ->assertJsonPath('session.ownAnswers.0.value.optionId', 'a')->assertJsonPath('session.ownAnswers.0.grade', null);
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertJsonPath('session.answers.0.grade', true);
        $this->assertNull(SessionAnswer::firstOrFail()->value);
        $base = ['stageId' => 'first', 'blockId' => 'single'];
        foreach ([['optionId' => 'a', 'value' => ['optionId' => 'a']], ['value' => ['optionId' => 'a', 'grade' => true]],
            ['value' => ['optionId' => 'a'], 'attemptNo' => 2], ['value' => ['optionId' => 'a'], 'participantId' => $participant['id']],
            ['value' => ['optionId' => 'a'], 'published' => true], ['value' => null], ['optionId' => ['a']]] as $extra) {
            $this->postJson('/api/participation/'.$session['id'].'/answers', $base + $extra)->assertUnprocessable();
        }
        $this->postJson('/api/participation/'.$session['id'].'/answers', $base + ['optionId' => 'a'])->assertOk();
        $this->assertNull(SessionAnswer::firstOrFail()->value);
        $this->submit($session, 'single', ['optionId' => 'b'])->assertConflict()->assertJsonPath('error.code', 'answer_locked');
        $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => 'roles', 'optionId' => 'a'])->assertUnprocessable();
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'revision' => 1]);
    }

    public function test_multiple_sequence_matching_poll_normalize_values_and_only_reveal_whitelisted_results(): void
    {
        $session = $this->start();
        $this->join($session);
        $values = ['multiple' => ['optionIds' => ['b', 'a']], 'sequence' => ['itemIds' => ['b', 'a']],
            'matching' => ['pairs' => [['leftId' => 'b', 'rightId' => 'a'], ['leftId' => 'a', 'rightId' => 'b']]],
            'poll' => ['optionId' => 'b']];
        foreach ($values as $id => $value) {
            $this->execute($session, 'block.open', ['blockId' => $id]);
            $this->submit($session, $id, $value)->assertOk();
            $this->execute($session, 'block.close', ['blockId' => $id]);
            $this->execute($session, 'block.reveal', ['blockId' => $id]);
        }
        $teacher = $this->getJson('/api/studio/sessions/'.$session['id'])->json('session');
        $this->assertSame([true, true, true, null], array_column($teacher['answers'], 'grade'));
        $this->assertSame(['b', 'a'], $teacher['answers'][1]['value']['itemIds']);
        $this->assertSame([['leftId' => 'a', 'rightId' => 'b'], ['leftId' => 'b', 'rightId' => 'a']], $teacher['answers'][2]['value']['pairs']);
        $public = $this->projector($session)->assertOk()->json('session');
        $this->assertSame(['counts' => [['optionId' => 'a', 'count' => 0], ['optionId' => 'b', 'count' => 1]], 'totalAnswers' => 1], $public['stage']['blocks'][2]['runtime']['results']);
        foreach (['teacherNotes', 'solution', 'Hidden note', 'participants', 'participantId', 'moderation', 'origin'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($public, JSON_THROW_ON_ERROR));
        }
    }

    public function test_prepared_paused_finished_and_timer_pause_follow_session_state_without_reset(): void
    {
        $session = $this->start(true);
        $this->join($session);
        $this->execute($session, 'block.open', ['blockId' => 'poll']);
        $this->submit($session, 'poll', ['optionId' => 'a'])->assertConflict();
        $this->execute($session, 'begin');
        $this->execute($session, 'timer.start', ['seconds' => 20]);
        $this->execute($session, 'timer.pause');
        $this->submit($session, 'poll', ['optionId' => 'a'])->assertOk();
        $this->execute($session, 'pause');
        $this->submit($session, 'poll', ['optionId' => 'a'])->assertConflict();
        $this->execute($session, 'resume');
        $this->submit($session, 'poll', ['optionId' => 'a'])->assertOk();
        $this->execute($session, 'finish');
        $this->command($session, 'block.close', ['blockId' => 'poll'])->assertConflict();
        $this->submit($session, 'poll', ['optionId' => 'a'])->assertConflict();
        $this->assertSame(1, SessionAnswer::firstOrFail()->revision);
    }

    public function test_moderation_preserves_original_and_publication_is_explicit_anonymous_and_active_only(): void
    {
        $session = $this->start();
        $participant = $this->join($session);
        $this->execute($session, 'block.open', ['blockId' => 'free']);
        $original = "  Привет 👋 <b>literal</b>\n ";
        $this->submit($session, 'free', ['text' => $original])->assertOk()->assertJsonPath('session.ownAnswers.0.value.text', $original);
        $answer = SessionAnswer::firstOrFail();
        $this->assertSame($original, $answer->value['text']);
        $this->execute($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 1, 'status' => 'approved', 'displayText' => '<em>Display</em>']);
        $this->projector($session)->assertJsonMissingPath('session.stage.blocks.3.runtime.results');
        $this->execute($session, 'answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => 2]);
        $projector = $this->projector($session)->assertJsonPath('session.stage.blocks.3.runtime.results.published', [['text' => '<em>Display</em>']])->json('session');
        foreach ([$original, $participant['id'], 'Student name', 'answerId', 'displayText', 'moderation'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($projector, JSON_THROW_ON_ERROR));
        }
        $this->getJson('/api/participation/'.$session['id'])->assertJsonMissingPath('session.stage.blocks.3.runtime.results');
        $this->identity((string) Str::uuid());
        $this->join($session, 'Another');
        $student = $this->getJson('/api/participation/'.$session['id'])->assertJsonPath('session.ownAnswers', [])->json('session');
        $this->assertStringNotContainsString('Display', json_encode($student, JSON_THROW_ON_ERROR));
        $this->identity($this->owner, [$session['id'] => $participant['id']]);
        $this->execute($session, 'stage', ['stageId' => 'second']);
        $this->assertStringNotContainsString('Display', $this->projector($session)->getContent());
        $this->command($session, 'answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => 3])->assertUnprocessable();
        $this->execute($session, 'stage', ['stageId' => 'first']);
        $this->execute($session, 'answer.unpublish', ['answerId' => $answer->id, 'expectedAnswerRevision' => 3]);
        $this->projector($session)->assertJsonMissingPath('session.stage.blocks.3.runtime.results');
        $this->execute($session, 'block.close', ['blockId' => 'free']);
        $this->execute($session, 'answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => 4]);
        $this->execute($session, 'block.reveal', ['blockId' => 'free']);
        $this->projector($session)->assertJsonPath('session.stage.blocks.3.runtime.results.published', [['text' => '<em>Display</em>']]);
        $this->execute($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 5, 'status' => 'approved', 'displayText' => '<em>Display</em>']);
        $this->projector($session)->assertJsonMissingPath('session.stage.blocks.3.runtime.results');
        $this->assertSame($original, $answer->fresh()->value['text']);
        $this->assertSame('approved', $answer->fresh()->moderation_status);
    }

    public function test_free_answer_edit_resets_moderation_but_identical_submission_preserves_it_and_stale_revision_conflicts(): void
    {
        $session = $this->start();
        $this->join($session);
        $this->execute($session, 'block.open', ['blockId' => 'free']);
        $this->submit($session, 'free', ['text' => 'First'])->assertOk();
        $answer = SessionAnswer::firstOrFail();
        $this->execute($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 1, 'status' => 'approved']);
        $this->execute($session, 'answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => 2]);
        $this->submit($session, 'free', ['text' => 'First'])->assertOk()->assertJsonPath('session.ownAnswers.0.revision', 3);
        $this->assertTrue($answer->fresh()->published);
        $this->submit($session, 'free', ['text' => 'Edited'])->assertOk()->assertJsonPath('session.ownAnswers.0.revision', 4)
            ->assertJsonPath('session.ownAnswers.0.status', 'pending');
        $this->assertFalse($answer->fresh()->published);
        $this->assertNull($answer->fresh()->display_text);
        $before = SessionCommandReceipt::count();
        $this->command($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 3, 'status' => 'approved', 'displayText' => 'Local editor'])->assertConflict()
            ->assertJsonPath('error.code', 'answer_revision_conflict')->assertJsonPath('session.answers.0.value.text', 'Edited');
        $this->assertSame($before, SessionCommandReceipt::count());
        $this->projector($session)->assertJsonMissingPath('session.stage.blocks.3.runtime.results');
        $this->execute($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 4, 'status' => 'rejected']);
        $this->command($session, 'answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => 5])->assertConflict();
        $this->command($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 5, 'status' => 'rejected', 'displayText' => 'No'])->assertUnprocessable();
        $this->command($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 5, 'status' => 'approved', 'displayText' => '  '])->assertUnprocessable();
    }

    public function test_roles_capacity_release_assignment_and_failed_switch_preserve_prior_claim(): void
    {
        $session = $this->start();
        $first = $this->join($session);
        $this->submit($session, 'roles', ['roleId' => 'a'])->assertOk();
        $this->identity((string) Str::uuid());
        $second = $this->join($session, 'Second');
        $this->submit($session, 'roles', ['roleId' => 'b'])->assertOk();
        $this->submit($session, 'roles', ['roleId' => 'a'])->assertConflict()->assertJsonPath('error.code', 'role_full');
        $this->assertDatabaseHas('session_answers', ['session_participant_id' => $second['id'], 'revision' => 1]);
        $this->assertSame(['roleId' => 'b'], SessionAnswer::where('session_participant_id', $second['id'])->firstOrFail()->value);
        $this->submit($session, 'roles', ['roleId' => 'b'])->assertOk()->assertJsonPath('session.ownAnswers.0.revision', 1);
        $this->identity($this->owner, [$session['id'] => $first['id']]);
        $this->command($session, 'role.assign', ['blockId' => 'roles', 'participantId' => $second['id'], 'roleId' => 'a'])->assertConflict();
        $this->execute($session, 'role.assign', ['blockId' => 'roles', 'participantId' => $first['id'], 'roleId' => null]);
        $this->execute($session, 'role.assign', ['blockId' => 'roles', 'participantId' => $second['id'], 'roleId' => 'a']);
        $this->assertSame(['roleId' => 'a'], SessionAnswer::where('session_participant_id', $second['id'])->firstOrFail()->value);
        $this->projector($session)->assertJsonPath('session.stage.blocks.6.runtime.availability', [
            ['roleId' => 'a', 'used' => 1, 'capacity' => 1], ['roleId' => 'b', 'used' => 0, 'capacity' => 1],
        ]);
        $this->execute($session, 'block.close', ['blockId' => 'roles']);
        $this->command($session, 'role.assign', ['blockId' => 'roles', 'participantId' => $second['id'], 'roleId' => null])->assertConflict();
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'revision' => $session['revision']]);
    }

    public function test_signals_ack_is_server_only_targeted_and_a_new_question_resets_it(): void
    {
        $session = $this->start();
        $participant = $this->join($session);
        $this->submit($session, 'signals', ['ready' => false, 'question' => true])->assertOk();
        $this->submit($session, 'signals', ['ready' => false, 'question' => true, 'acknowledged' => true])->assertUnprocessable();
        $this->execute($session, 'signal.ack', ['blockId' => 'signals', 'participantId' => $participant['id']]);
        $this->getJson('/api/participation/'.$session['id'])->assertJsonPath('session.ownAnswers.0.acknowledged', true)->assertJsonPath('session.ownAnswers.0.revision', 2);
        $this->submit($session, 'signals', ['ready' => true, 'question' => true])->assertOk()->assertJsonPath('session.ownAnswers.0.acknowledged', true);
        $this->submit($session, 'signals', ['ready' => true, 'question' => false])->assertOk()->assertJsonPath('session.ownAnswers.0.acknowledged', false);
        $this->command($session, 'signal.ack', ['blockId' => 'signals', 'participantId' => $participant['id']])->assertConflict();
        $this->submit($session, 'signals', ['ready' => false, 'question' => true])->assertOk()->assertJsonPath('session.ownAnswers.0.acknowledged', false);
        $this->projector($session)->assertJsonMissingPath('session.stage.blocks.7.runtime.results');
        $this->execute($session, 'stage', ['stageId' => 'second']);
        $this->command($session, 'signal.ack', ['blockId' => 'signals', 'participantId' => $participant['id']])->assertUnprocessable();
        $this->execute($session, 'stage', ['stageId' => 'first']);
        $this->execute($session, 'signal.ack', ['blockId' => 'signals', 'participantId' => $participant['id']]);
    }

    public function test_foreign_answers_participants_and_commands_cannot_cross_sessions(): void
    {
        $first = $this->start();
        $second = $this->start();
        $participant = $this->join($second);
        $this->execute($second, 'block.open', ['blockId' => 'free']);
        $this->submit($second, 'free', ['text' => 'Private other room'])->assertOk();
        $answer = SessionAnswer::firstOrFail();
        $this->command($first, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 1, 'status' => 'approved'])->assertNotFound();
        $this->command($first, 'role.assign', ['blockId' => 'roles', 'participantId' => $participant['id'], 'roleId' => 'a'])->assertNotFound();
        $this->command($first, 'signal.ack', ['blockId' => 'signals', 'participantId' => $participant['id']])->assertNotFound();
        $this->join($first);
        $this->getJson('/api/participation/'.$first['id'])->assertJsonPath('session.ownAnswers', []);
        $this->identity((string) Str::uuid());
        $response = $this->command($second, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 1, 'status' => 'approved'])->assertNotFound();
        $this->assertStringNotContainsString('Private other room', $response->getContent());
        $this->assertDatabaseCount('session_command_receipts', 1);
    }

    public function test_interactive_receipts_replay_conflict_and_atomic_rollback_include_block_and_answer_mutations(): void
    {
        $session = $this->start();
        $this->join($session);
        $uuid = (string) Str::uuid();
        $original = $session;
        $session = $this->command($session, 'block.open', ['blockId' => 'free'], $uuid)->assertOk()->json('session');
        $this->command($original, 'block.open', ['blockId' => 'free'], $uuid)->assertOk()->assertJsonPath('session.revision', 2);
        $this->command($original, 'block.open', ['blockId' => 'multiple'], $uuid)->assertConflict()->assertJsonPath('error.code', 'command_conflict');
        $this->command($original, 'block.close', ['blockId' => 'free'])->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $this->submit($session, 'free', ['text' => 'Original'])->assertOk();
        $answer = SessionAnswer::firstOrFail();
        SessionCommandReceipt::creating(fn () => throw new RuntimeException('Receipt rollback'));
        try {
            $this->app->make(RuntimeService::class)->command($this->owner, $session['id'], (string) Str::uuid(), 2, 'answer.moderate',
                ['answerId' => $answer->id, 'expectedAnswerRevision' => 1, 'status' => 'approved']);
            $this->fail('Receipt failure must roll back answer mutation.');
        } catch (RuntimeException $error) {
            $this->assertSame('Receipt rollback', $error->getMessage());
        } finally {
            SessionCommandReceipt::flushEventListeners();
        }
        $this->assertSame('pending', $answer->fresh()->moderation_status);
        $this->assertSame(1, $answer->fresh()->revision);
        $this->assertDatabaseCount('session_command_receipts', 1);
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'revision' => 2]);
    }

    public function test_snapshot_and_case_sensitive_instances_keep_independent_state(): void
    {
        $document = $this->document();
        $copy = $document['stages'][0]['blocks'][1];
        $copy['id'] = 'Multiple';
        $document['stages'][0]['blocks'][] = $copy;
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated()->json('session');
        $this->join($session);
        $this->execute($session, 'block.open', ['blockId' => 'multiple']);
        $this->submit($session, 'multiple', ['optionIds' => ['a', 'b']])->assertOk();
        $this->submit($session, 'Multiple', ['optionIds' => ['a', 'b']])->assertConflict();
        $this->execute($session, 'block.open', ['blockId' => 'Multiple']);
        $this->submit($session, 'Multiple', ['optionIds' => ['b']])->assertOk();
        $this->assertDatabaseCount('session_block_states', 2);
        $this->assertDatabaseCount('session_answers', 2);
        $lesson = $this->getJson('/api/studio/lessons/'.$lesson['id'])->json('lesson');
        $lesson['document']['stages'][0]['blocks'][1]['solution'] = ['optionIds' => ['b']];
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => $lesson['revision'], 'document' => $lesson['document']])->assertOk();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertJsonPath('session.answers.0.grade', true)->assertJsonPath('session.answers.1.grade', false);
    }

    public function test_additive_migration_preserves_legacy_answer_and_rollback_does_not_coerce_nullable_option(): void
    {
        $session = $this->start();
        $participant = $this->join($session);
        $answer = SessionAnswer::create(['teaching_session_id' => $session['id'], 'session_participant_id' => $participant['id'], 'block_id' => 'single', 'option_id' => 'a']);
        $migration = require database_path('migrations/2026_10_01_120000_add_interactive_runtime_state.php');
        $migration->down();
        $this->assertDatabaseHas('session_answers', ['id' => $answer->id, 'option_id' => 'a']);
        $migration->up();
        $this->assertSame('a', $answer->fresh()->option_id);
        $this->assertNull($answer->fresh()->value);
        $this->assertSame(1, $answer->fresh()->revision);
        $this->getJson('/api/participation/'.$session['id'])->assertJsonPath('session.ownAnswers.0.value.optionId', 'a');
    }

    public function test_numeric_role_ids_remain_strings_and_capacity_cannot_be_bypassed(): void
    {
        $document = $this->document();
        $document['stages'][0]['blocks'][6]['content']['ru']['roles'] = [['roleId' => '0', 'text' => 'Zero'], ['roleId' => '1', 'text' => 'One']];
        $document['stages'][0]['blocks'][6]['config']['capacities'] = ['0' => 1, '1' => 1];
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated()->json('session');
        $this->join($session);
        $this->submit($session, 'roles', ['roleId' => '0'])->assertOk();
        $this->identity((string) Str::uuid());
        $this->join($session);
        $this->submit($session, 'roles', ['roleId' => '0'])->assertConflict()->assertJsonPath('error.code', 'role_full');
        $this->projector($session)->assertJsonPath('session.stage.blocks.6.runtime.availability', [
            ['roleId' => '0', 'used' => 1, 'capacity' => 1], ['roleId' => '1', 'used' => 0, 'capacity' => 1],
        ]);
    }

    public function test_answer_validation_rejects_bad_ids_shapes_and_unicode_whitespace_without_any_mutation(): void
    {
        $session = $this->start();
        $this->join($session);
        foreach (['multiple', 'sequence', 'matching', 'free', 'poll'] as $block) {
            $this->execute($session, 'block.open', ['blockId' => $block]);
        }
        foreach ([['multiple', ['optionIds' => ['a', 'a']]], ['multiple', ['optionIds' => ['A']]],
            ['sequence', ['itemIds' => ['a']]], ['sequence', ['itemIds' => ['a', 'a']]],
            ['matching', ['pairs' => [['leftId' => 'a', 'rightId' => 'a'], ['leftId' => 'b', 'rightId' => 'a']]]],
            ['free', ['text' => "\u{00a0}\u{2003}\n"]], ['free', ['text' => str_repeat('😀', 101)]],
            ['poll', ['optionId' => 'not-an-option']], ['roles', ['roleId' => false]],
            ['signals', ['ready' => 1, 'question' => true]], ['signals', ['question' => true]]] as [$block, $value]) {
            $this->submit($session, $block, $value)->assertUnprocessable()->assertJsonPath('error.code', 'invalid_action');
        }
        $this->assertDatabaseCount('session_answers', 0);
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'revision' => $session['revision']]);
        $this->submit($session, 'free', ['text' => str_repeat('😀', 100)])->assertOk();
        $answer = SessionAnswer::firstOrFail();
        $this->command($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => '1', 'status' => 'approved'])->assertUnprocessable();
        $this->command($session, 'answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => 1, 'status' => 'approved', 'displayText' => "\u{00a0}\u{2003}"])->assertUnprocessable();
        $this->assertSame(1, $answer->fresh()->revision);
    }

    public function test_mysql_and_mariadb_migration_has_binary_states_and_nullable_compatible_answer_columns(): void
    {
        $original = Schema::getFacadeRoot();
        try {
            $pdo = fn () => throw new \LogicException('No database connection expected');
            $config = ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'];
            $mysql = new MySqlConnection($pdo, 'migration_test', '', ['driver' => 'mysql'] + $config);
            $maria = new class($pdo, 'migration_test', '', ['driver' => 'mariadb'] + $config) extends MariaDbConnection
            {
                public function getServerVersion(): string
                {
                    return '10.6.23';
                }
            };
            foreach ([$mysql, $maria] as $connection) {
                $connection->useDefaultSchemaGrammar();
                Schema::swap($connection->getSchemaBuilder());
                $migration = require database_path('migrations/2026_10_01_120000_add_interactive_runtime_state.php');
                $sql = implode("\n", array_column($connection->pretend(fn () => $migration->up()), 'query'));
                $this->assertStringContainsString("`block_id` varchar(128) collate 'utf8mb4_bin' not null", $sql);
                $this->assertStringContainsString('unique `session_block_states_session_block_unique`(`teaching_session_id`, `block_id`)', $sql);
                $this->assertStringContainsString('`option_id` varchar(128) null', $sql);
                $this->assertStringContainsString('`value` json null', $sql);
                $this->assertStringContainsString("`revision` int unsigned not null default '1'", $sql);
            }
        } finally {
            Schema::swap($original);
        }
    }

    public function test_polling_block_state_queries_remain_bounded_with_thirty_historical_answers(): void
    {
        foreach ([2, 30] as $count) {
            $document = $this->document();
            $single = $document['stages'][0]['blocks'][0];
            $document['stages'][0]['blocks'] = [];
            for ($index = 0; $index < $count; $index++) {
                $copy = $single;
                $copy['id'] = match ($index) {
                    0 => '0', 1 => '00', default => 'history-'.$index
                };
                $document['stages'][0]['blocks'][] = $copy;
            }
            $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
            $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated()->json('session');
            $participant = $this->join($session);
            foreach ($document['stages'][0]['blocks'] as $block) {
                SessionAnswer::create(['teaching_session_id' => $session['id'], 'session_participant_id' => $participant['id'], 'block_id' => $block['id'], 'option_id' => 'a']);
            }
            SessionBlockState::create(['teaching_session_id' => $session['id'], 'block_id' => '0', 'status' => 'revealed']);
            SessionBlockState::create(['teaching_session_id' => $session['id'], 'block_id' => '00', 'status' => 'closed']);
            $this->execute($session, 'stage', ['stageId' => 'second']);
            $endpoints = ['/api/studio/sessions/'.$session['id'], '/api/participation/'.$session['id'], '/api/projection/'.basename($session['projectorUrl'])];
            foreach ($endpoints as $endpoint) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $response = $this->getJson($endpoint)->assertOk();
                    $queries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains(strtolower($query['query']), 'session_block_states'));
                    $this->assertCount(1, $queries, 'Each polling response must load block states once, regardless of lesson/answer count.');
                    if (str_contains($endpoint, '/participation/')) {
                        $response->assertJsonPath('session.stage.id', 'second')->assertJsonPath('session.stage.blocks.0.runtime.status', 'open')
                            ->assertJsonPath('session.ownAnswers.0.blockId', '0')->assertJsonPath('session.ownAnswers.0.grade', true)
                            ->assertJsonPath('session.ownAnswers.1.blockId', '00')->assertJsonPath('session.ownAnswers.1.grade', null);
                        $this->assertCount($count, $response->json('session.ownAnswers'));
                    } elseif (str_contains($endpoint, '/studio/')) {
                        $response->assertJsonPath('session.blockStates.0.status', 'revealed')->assertJsonPath('session.blockStates.1.status', 'closed');
                    } else {
                        $response->assertJsonMissingPath('session.ownAnswers');
                        $this->assertStringNotContainsString($participant['id'], $response->getContent());
                    }
                } finally {
                    DB::disableQueryLog();
                    DB::flushQueryLog();
                }
            }
            $this->execute($session, 'block.close', ['blockId' => 'other']);
            $this->projector($session)->assertJsonPath('session.stage.blocks.0.runtime.status', 'closed');
            $this->execute($session, 'stage', ['stageId' => 'first']);
            $this->execute($session, 'block.open', ['blockId' => '00']);
            $this->getJson('/api/participation/'.$session['id'])->assertOk()->assertJsonPath('session.stage.blocks.0.runtime.status', 'revealed')
                ->assertJsonPath('session.stage.blocks.1.runtime.status', 'open')->assertJsonPath('session.ownAnswers.1.revision', 1);
            $this->assertDatabaseHas('session_block_states', ['teaching_session_id' => $session['id'], 'block_id' => '00', 'status' => 'open']);
        }
    }

    private function identity(string $owner, array $participants = []): void
    {
        $this->withSession(['studio_owner_key' => $owner, RuntimeController::SESSION_PARTICIPANTS_KEY => $participants]);
    }

    private function start(bool $prepare = false): array
    {
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated()->json('lesson');

        return $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision'], 'prepare' => $prepare])->assertCreated()->json('session');
    }

    private function join(array $session, string $name = 'Student name'): array
    {
        return $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => $name])->assertOk()->json('participant');
    }

    private function submit(array $session, string $blockId, array $value): TestResponse
    {
        return $this->postJson('/api/participation/'.$session['id'].'/answers', ['stageId' => 'first', 'blockId' => $blockId, 'value' => $value]);
    }

    private function command(array $session, string $action, array $payload = [], ?string $uuid = null): TestResponse
    {
        return $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', [
            'commandId' => $uuid ?? (string) Str::uuid(), 'expectedRevision' => $session['revision'], 'action' => $action, 'payload' => $payload,
        ]);
    }

    private function execute(array &$session, string $action, array $payload = []): void
    {
        $session = $this->command($session, $action, $payload)->assertOk()->json('session');
    }

    private function projector(array $session): TestResponse
    {
        return $this->getJson('/api/projection/'.basename($session['projectorUrl']));
    }

    private function document(): array
    {
        $options = [['optionId' => 'a', 'text' => 'A'], ['optionId' => 'b', 'text' => 'B']];
        $items = [['itemId' => 'a', 'text' => 'A'], ['itemId' => 'b', 'text' => 'B']];
        $block = fn (string $id, string $type, array $content, array $config = [], ?array $solution = null): array => [
            'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $content],
            'config' => $config, 'solution' => $solution, 'teacherNotes' => ['ru' => 'Hidden note'],
        ];

        return ['id' => 'interactive-fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
            'content' => ['ru' => ['title' => 'Interactive fixture']], 'stages' => [
                ['id' => 'first', 'content' => ['ru' => ['title' => 'First']], 'blocks' => [
                    $block('single', 'single-choice', ['question' => 'Choose', 'options' => $options], [], ['optionId' => 'a']),
                    $block('multiple', 'multiple-choice', ['question' => 'Choose', 'options' => $options], [], ['optionIds' => ['a', 'b']]),
                    $block('poll', 'poll', ['question' => 'Choose', 'options' => $options]),
                    $block('free', 'free-response', ['question' => 'Write'], ['allowRepeat' => true, 'maxLength' => 100]),
                    $block('sequence', 'sequence', ['question' => 'Order', 'items' => $items], [], ['itemIds' => ['b', 'a']]),
                    $block('matching', 'matching', ['question' => 'Match', 'left' => $items, 'right' => $items], [], ['pairs' => [['leftId' => 'a', 'rightId' => 'b'], ['leftId' => 'b', 'rightId' => 'a']]]),
                    $block('roles', 'roles', ['text' => 'Choose role', 'roles' => [['roleId' => 'a', 'text' => 'A'], ['roleId' => 'b', 'text' => 'B']]], ['capacities' => ['a' => 1, 'b' => 1]]),
                    $block('signals', 'signals', ['text' => 'Ready?']),
                ]],
                ['id' => 'second', 'content' => ['ru' => ['title' => 'Second']], 'blocks' => [
                    $block('other', 'single-choice', ['question' => 'Other', 'options' => $options]),
                ]],
            ]];
    }
}
