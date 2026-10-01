<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Runtime\RuntimeController;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class RuntimeTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        $this->identity($this->owner);
    }

    public function test_teacher_ownership_is_server_session_bound_and_join_code_does_not_grant_teacher_access(): void
    {
        [$lesson, $session] = $this->start();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()
            ->assertJsonPath('session.document.stages.0.content.notes', 'Teacher secret')
            ->assertJsonPath('session.document.stages.0.blocks.0.solution.optionId', 'first');

        $this->identity((string) Str::uuid());
        $this->getJson('/api/studio/sessions/'.$session['id'].'?owner_key='.$this->owner)->assertNotFound();
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => 2, 'owner_key' => $this->owner])->assertNotFound();
        $this->postJson('/api/studio/sessions/'.$session['id'].'/stage', [
            'expectedRevision' => 1, 'stageId' => 'stage-2', 'owner_key' => $this->owner,
        ])->assertNotFound();
        $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => 'Student'])->assertOk();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertNotFound();
        $this->assertDatabaseCount('teaching_sessions', 1);
    }

    public function test_public_states_have_only_the_current_stage_and_no_private_data_or_other_answers(): void
    {
        [, $session] = $this->start();
        $firstParticipant = $this->join($session, 'First');
        $this->answer($session, 'choice-1', 'first')->assertOk();
        $this->identity((string) Str::uuid());
        $secondParticipant = $this->join($session, 'Second');
        $student = $this->getJson('/api/participation/'.$session['id'])->assertOk()->json('session');
        $projector = $this->getJson('/api/projection/'.$this->token($session))->assertOk()->json('session');

        $this->assertSame(['id', 'revision', 'locale', 'currentStageId', 'mode', 'status', 'serverNow', 'timer', 'message', 'wave', 'stage', 'ownAnswers'], array_keys($student));
        $this->assertSame(['id', 'revision', 'locale', 'currentStageId', 'mode', 'status', 'serverNow', 'timer', 'message', 'wave', 'stage'], array_keys($projector));
        $this->assertSame('stage-1', $student['stage']['id']);
        $this->assertSame([], $student['ownAnswers']);
        $this->assertNotSame($firstParticipant['id'], $secondParticipant['id']);
        foreach ([$student, $projector] as $state) {
            $json = json_encode($state, JSON_THROW_ON_ERROR);
            foreach (['Teacher secret', 'Future stage secret', 'notes', 'solution', 'origin', 'template-secret',
                'joinCode', 'projectorUrl', 'owner_key', 'participants', $firstParticipant['id'], 'choice-2'] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
    }

    public function test_participant_ids_in_request_body_cannot_impersonate_another_cookie_session(): void
    {
        [, $session] = $this->start();
        $participant = $this->join($session, 'Same name');
        $this->identity((string) Str::uuid());
        $this->getJson('/api/participation/'.$session['id'].'?participantId='.$participant['id'])->assertNotFound();
        $this->answer($session, 'choice-1', 'first', ['participantId' => $participant['id'], 'name' => 'Same name'])->assertNotFound();
        $other = $this->join($session, 'Same name');
        $this->answer($session, 'choice-1', 'second', ['participantId' => $participant['id']])->assertUnprocessable();
        $this->answer($session, 'choice-1', 'second')->assertOk();
        $this->assertDatabaseHas('session_answers', ['session_participant_id' => $other['id'], 'option_id' => 'second']);
        $this->assertDatabaseMissing('session_answers', ['session_participant_id' => $participant['id']]);
    }

    public function test_answers_validate_active_stage_block_type_and_option_without_changing_revision(): void
    {
        [, $session] = $this->start();
        $this->join($session);
        $this->answer($session, 'choice-1', 'unknown')->assertUnprocessable()->assertJsonPath('error.code', 'invalid_action');
        $this->answer($session, 'text-1', 'first')->assertUnprocessable();
        $this->answer($session, 'missing', 'first')->assertUnprocessable();
        $this->answer($session, 'choice-2', 'first', ['stageId' => 'stage-2'])->assertUnprocessable();
        $this->assertDatabaseCount('session_answers', 0);
        $this->answer($session, 'choice-1', 'first')->assertOk()->assertJsonPath('session.revision', 1);
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'revision' => 1]);
    }

    public function test_duplicate_answer_is_idempotent_but_a_changed_locked_answer_conflicts(): void
    {
        [, $session] = $this->start();
        $this->join($session);
        $this->answer($session, 'choice-1', 'first')->assertOk();
        $answer = SessionAnswer::firstOrFail();
        $this->answer($session, 'choice-1', 'first')->assertOk();
        $this->assertSame($answer->updated_at->toISOString(), $answer->fresh()->updated_at->toISOString());
        $this->answer($session, 'choice-1', 'second')->assertConflict()->assertJsonPath('error.code', 'answer_locked');
        $this->assertDatabaseCount('session_answers', 1);
        $this->assertSame('first', $answer->fresh()->option_id);
    }

    public function test_allow_repeat_updates_one_answer_and_navigation_back_preserves_answers(): void
    {
        [, $session] = $this->start();
        $this->join($session);
        $this->answer($session, 'choice-1', 'first')->assertOk();
        $this->navigate($session, 'stage-2', 1)->assertOk()->assertJsonPath('session.revision', 2);
        $this->answer($session, 'choice-1', 'first')->assertUnprocessable();
        $this->answer($session, 'choice-2', 'first', ['stageId' => 'stage-2'])->assertOk();
        $this->answer($session, 'choice-2', 'second', ['stageId' => 'stage-2'])->assertOk();
        $this->assertDatabaseCount('session_answers', 2);
        $this->navigate($session, 'stage-1', 2)->assertOk()->assertJsonPath('session.revision', 3);
        $state = $this->getJson('/api/participation/'.$session['id'])->assertOk()->json('session');
        $this->assertSame([
            ['blockId' => 'choice-1', 'optionId' => 'first'], ['blockId' => 'choice-2', 'optionId' => 'second'],
        ], array_map(fn (array $answer): array => array_intersect_key($answer, array_flip(['blockId', 'optionId'])), $state['ownAnswers']));
    }

    public function test_navigation_requires_current_revision_and_a_stage_in_the_fixed_document(): void
    {
        [, $session] = $this->start();
        $this->navigate($session, 'stage-2', 1)->assertOk();
        $this->navigate($session, 'stage-1', 1)->assertConflict()->assertJsonPath('error.code', 'revision_conflict');
        $this->navigate($session, 'does-not-exist', 2)->assertUnprocessable();
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()
            ->assertJsonPath('session.revision', 2)->assertJsonPath('session.currentStageId', 'stage-2');
    }

    public function test_separate_sessions_have_independent_navigation_answers_and_cookie_participant_map(): void
    {
        [$lesson, $first] = $this->start();
        $revision = $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->json('lesson.revision');
        $second = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $revision])->assertCreated()->json('session');
        $this->assertNotSame($first['joinCode'], $second['joinCode']);
        $this->assertNotSame($first['projectorUrl'], $second['projectorUrl']);
        $this->assertSame(TeachingSession::findOrFail($first['id'])->lesson_version_id, TeachingSession::findOrFail($second['id'])->lesson_version_id);
        $firstParticipant = $this->join($first);
        $secondParticipant = $this->join($second);
        $this->assertNotSame($firstParticipant['id'], $secondParticipant['id']);
        $this->answer($first, 'choice-1', 'first')->assertOk();
        $this->navigate($first, 'stage-2', 1)->assertOk();
        $this->getJson('/api/participation/'.$second['id'])->assertOk()
            ->assertJsonPath('session.currentStageId', 'stage-1')->assertJsonPath('session.revision', 1)->assertJsonPath('session.ownAnswers', []);
        $this->answer($second, 'choice-1', 'second')->assertOk();
        $this->assertDatabaseCount('session_answers', 2);
    }

    public function test_refresh_and_rejoin_restore_server_state_and_released_snapshot_survives_editor_changes(): void
    {
        [$lesson, $session] = $this->start();
        $participant = $this->join($session);
        $this->answer($session, 'choice-1', 'first')->assertOk();
        $this->navigate($session, 'stage-2', 1)->assertOk();
        $this->assertSame($participant['id'], $this->join($session, 'Changed name')['id']);
        $this->assertDatabaseCount('session_participants', 1);
        $current = $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->json('lesson');
        $edited = $current['document'];
        $edited['stages'][1]['content']['ru']['title'] = 'Edited later';
        $this->putJson('/api/studio/lessons/'.$lesson['id'], ['expectedRevision' => $current['revision'], 'document' => $edited])->assertOk();
        $this->getJson('/api/participation/'.$session['id'])->assertOk()
            ->assertJsonPath('session.stage.content.title', 'Second stage')->assertJsonPath('session.currentStageId', 'stage-2')
            ->assertJsonPath('session.ownAnswers.0.optionId', 'first');
        $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->assertJsonPath('session.answers.0.participantId', $participant['id']);
        $this->getJson('/api/projection/'.$this->token($session))->assertOk()->assertJsonPath('session.stage.content.title', 'Second stage');
    }

    public function test_invalid_locale_rolls_back_release_and_projector_token_cannot_mutate_state(): void
    {
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated()->json('lesson');
        $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision'], 'locale' => 'fr'])->assertUnprocessable();
        $this->assertDatabaseCount('teaching_sessions', 0);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk()->assertJsonPath('lesson.status', 'draft');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision'], 'locale' => 'de'])->assertCreated()->json('session');
        $this->assertSame('de', $session['locale']);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $session['joinCode']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $this->token($session));
        $this->postJson('/api/projection/'.$this->token($session), ['stageId' => 'stage-2'])->assertStatus(405);
        $this->identity((string) Str::uuid());
        $this->postJson('/api/studio/sessions/'.$session['id'].'/stage', [
            'expectedRevision' => 1, 'stageId' => 'stage-2', 'projectorToken' => $this->token($session),
        ])->assertNotFound();
        $this->getJson('/api/projection/'.str_repeat('0', 64))->assertNotFound();
        $this->assertDatabaseHas('teaching_sessions', ['id' => $session['id'], 'current_stage_id' => 'stage-1']);
    }

    public function test_case_sensitive_block_ids_keep_independent_answers(): void
    {
        $document = $this->document();
        $document['stages'][0]['blocks'][0]['id'] = 'Choice';
        $document['stages'][1]['blocks'][0]['id'] = 'choice';
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated()->json('session');
        $this->join($session);
        $this->answer($session, 'Choice', 'first')->assertOk();
        $this->navigate($session, 'stage-2', 1)->assertOk();
        $this->answer($session, 'choice', 'second', ['stageId' => 'stage-2'])->assertOk();
        $this->getJson('/api/participation/'.$session['id'])->assertOk()
            ->assertJsonPath('session.ownAnswers.0.blockId', 'Choice')->assertJsonPath('session.ownAnswers.0.optionId', 'first')
            ->assertJsonPath('session.ownAnswers.1.blockId', 'choice')->assertJsonPath('session.ownAnswers.1.optionId', 'second');
        $this->assertDatabaseCount('session_answers', 2);
    }

    public function test_mysql_and_mariadb_migration_sql_uses_binary_block_id_collation(): void
    {
        $original = Schema::getFacadeRoot();
        try {
            // PDO is never resolved: compile the actual migration without a server.
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
                $migration = require database_path('migrations/2026_09_30_110000_create_teaching_session_tables.php');
                $queries = $connection->pretend(fn () => $migration->up());
                $sql = implode("\n", array_column($queries, 'query'));
                $this->assertStringContainsString("`block_id` varchar(128) collate 'utf8mb4_bin' not null", $sql);
                $this->assertStringContainsString('unique `session_answers_participant_block_unique`(`teaching_session_id`, `session_participant_id`, `block_id`)', $sql);
                $this->assertStringContainsString('`lesson_version_id` char(36) not null', $sql);
            }
        } finally {
            Schema::swap($original);
        }
    }

    public function test_cookie_state_uses_session_blocking_with_a_lock_capable_store(): void
    {
        $this->assertTrue($this->app['session']->shouldBlock());
        $this->assertSame('file', $this->app['session']->blockDriver());
        $this->assertInstanceOf(LockProvider::class, $this->app['cache']->store('file')->getStore());
    }

    public function test_projector_url_uses_supported_ui_locale_and_preserves_english_session_content(): void
    {
        $document = $this->document();
        $document['defaultLocale'] = 'en';
        $document['locales'] = ['en'];
        $document['content'] = ['en' => $document['content']['ru']];
        foreach ($document['stages'] as &$stage) {
            $stage['content'] = ['en' => $stage['content']['ru']];
            foreach ($stage['blocks'] as &$block) {
                $block['content'] = ['en' => $block['content']['ru']];
            }
            unset($block);
        }
        unset($stage);

        config(['app.locale' => 'de']);
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $document])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated()->json('session');
        $this->assertSame('en', $session['locale']);
        $this->assertStringContainsString('/de/project/', $session['projectorUrl']);
        $this->withoutVite()->get($session['projectorUrl'])->assertOk()->assertViewHas('page', 'projector');
        $this->getJson('/api/projection/'.$this->token($session))->assertOk()
            ->assertJsonPath('session.locale', 'en')->assertJsonPath('session.stage.content.title', 'First stage')
            ->assertJsonPath('session.stage.blocks.0.content.question', 'Choose');

        config(['app.locale' => 'en']);
        $fallback = $this->getJson('/api/studio/sessions/'.$session['id'])->assertOk()->json('session');
        $this->assertStringContainsString('/'.config('lessons.ui_locales')[0].'/project/', $fallback['projectorUrl']);
        $this->get($fallback['projectorUrl'])->assertOk()->assertViewHas('page', 'projector');
    }

    private function identity(string $owner): void
    {
        $this->withSession(['studio_owner_key' => $owner, RuntimeController::SESSION_PARTICIPANTS_KEY => []]);
    }

    private function start(): array
    {
        $lesson = $this->postJson('/api/studio/lessons', ['document' => $this->document()])->assertCreated()->json('lesson');
        $session = $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision']])->assertCreated()->json('session');

        return [$lesson, $session];
    }

    private function join(array $session, string $name = 'Student'): array
    {
        return $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => $name])->assertOk()->json('participant');
    }

    private function answer(array $session, string $block, string $option, array $extra = []): TestResponse
    {
        return $this->postJson('/api/participation/'.$session['id'].'/answers', array_replace([
            'stageId' => 'stage-1', 'blockId' => $block, 'optionId' => $option,
        ], $extra));
    }

    private function navigate(array $session, string $stage, int $revision): TestResponse
    {
        return $this->postJson('/api/studio/sessions/'.$session['id'].'/stage', ['expectedRevision' => $revision, 'stageId' => $stage]);
    }

    private function token(array $session): string
    {
        return basename($session['projectorUrl']);
    }

    private function document(): array
    {
        $choice = fn (string $id, bool $repeat) => [
            'id' => $id, 'type' => 'core.single-choice', 'schemaVersion' => 1,
            'content' => ['ru' => ['question' => 'Choose', 'options' => [
                ['optionId' => 'first', 'text' => 'First'], ['optionId' => 'second', 'text' => 'Second'],
            ]], 'de' => ['question' => 'Wählen', 'options' => [
                ['optionId' => 'second', 'text' => 'Zweite'], ['optionId' => 'first', 'text' => 'Erste'],
            ]]],
            'config' => ['allowRepeat' => $repeat], 'solution' => ['optionId' => 'first'],
            'origin' => ['templateId' => 'template-secret', 'versionId' => 'template-version'],
        ];

        return [
            'id' => 'fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru', 'de'],
            'content' => ['ru' => ['title' => 'Runtime fixture'], 'de' => ['title' => 'Runtime Test']],
            'stages' => [
                ['id' => 'stage-1', 'content' => [
                    'ru' => ['title' => 'First stage', 'notes' => 'Teacher secret'], 'de' => ['title' => 'Erste', 'notes' => 'Teacher secret'],
                ], 'blocks' => [$choice('choice-1', false), [
                    'id' => 'text-1', 'type' => 'core.text', 'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Text'], 'de' => ['text' => 'Text']],
                ]]],
                ['id' => 'stage-2', 'content' => [
                    'ru' => ['title' => 'Second stage', 'notes' => 'Future stage secret'], 'de' => ['title' => 'Zweite', 'notes' => 'Future stage secret'],
                ], 'blocks' => [$choice('choice-2', true)]],
            ],
        ];
    }
}
