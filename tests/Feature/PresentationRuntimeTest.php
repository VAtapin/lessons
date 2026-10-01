<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Catalog\NeighborUpgradeInstaller;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PresentationRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    private RuntimeService $runtime;

    private array $session;

    private string $student;

    private string $other;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app(NeighborInstaller::class)->install();
        app(NeighborUpgradeInstaller::class)->install();
        $this->owner = (string) Str::uuid();
        $this->runtime = app(RuntimeService::class);
        $this->session = app(CatalogService::class)->use('kto-moi-blizhnii', 'ru', $this->owner, true)['session'];
        $this->token = TeachingSession::findOrFail($this->session['id'])->projector_token;
        $this->student = $this->runtime->join($this->session['joinCode'], 'Private pupil', [])['participant']['id'];
        $this->other = $this->runtime->join($this->session['joinCode'], 'Other pupil', [])['participant']['id'];
        $this->execute('begin');
    }

    public function test_role_reveal_is_shared_persistent_and_retry_does_not_advance_twice(): void
    {
        $this->execute('stage', ['stageId' => 'neighbor-traveler']);
        $id = (string) Str::uuid();
        $revision = $this->session['revision'];
        $this->session = $this->runtime->command($this->owner, $this->session['id'], $id, $revision, 'role.reveal.next', ['blockId' => 'traveler-roles'])['session'];
        $this->assertSame(['traveler'], $this->block('traveler-roles')['runtime']['presentation']['revealedRoleIds']);
        $this->runtime->command($this->owner, $this->session['id'], $id, $revision, 'role.reveal.next', ['blockId' => 'traveler-roles']);
        $this->assertSame(['traveler'], $this->block('traveler-roles')['runtime']['presentation']['revealedRoleIds']);
        $this->execute('role.reveal.next', ['blockId' => 'traveler-roles']);
        $this->assertSame(['traveler', 'robber_1'], $this->block('traveler-roles')['runtime']['presentation']['revealedRoleIds']);
        $this->submit('traveler-roles', ['roleId' => 'traveler']);
        $this->submit('traveler-roles', ['roleId' => 'samaritan']);
        $roles = $this->block('traveler-roles')['runtime']['availability'];
        $this->assertSame(0, collect($roles)->firstWhere('roleId', 'traveler')['used']);
        $this->assertSame(1, collect($roles)->firstWhere('roleId', 'samaritan')['used']);
        $this->assertStringNotContainsString('Private pupil', json_encode($this->public()));
        $this->execute('role.reveal.reset', ['blockId' => 'traveler-roles']);
        $this->assertSame([], $this->block('traveler-roles')['runtime']['presentation']['revealedRoleIds']);
    }

    public function test_student_path_remains_private_until_one_atomic_review_and_teacher_path_uses_guided_steps(): void
    {
        $this->execute('stage', ['stageId' => 'neighbor-help-path']);
        $this->assertSame('open', $this->block('help-sequence')['runtime']['status']);
        $wrong = ['continue-care', 'approach', 'bring', 'help', 'notice'];
        $this->submit('help-sequence', ['itemIds' => $wrong]);
        $this->assertNull($this->own('help-sequence')['grade']);
        $this->assertArrayNotHasKey('results', $this->block('help-sequence')['runtime']);
        $this->execute('sequence.select', ['blockId' => 'help-sequence', 'itemId' => 'help']);
        $this->assertSame('incorrect', $this->block('help-sequence')['runtime']['presentation']['feedback']);
        $this->assertArrayNotHasKey('itemIds', $this->block('help-sequence')['runtime']['presentation']);
        foreach (['notice', 'approach', 'help', 'bring', 'continue-care'] as $item) {
            $this->execute('sequence.select', ['blockId' => 'help-sequence', 'itemId' => $item]);
        }
        $this->assertSame('complete', $this->block('help-sequence')['runtime']['presentation']['feedback']);
        $this->assertArrayNotHasKey('results', $this->block('help-sequence')['runtime']);
        $this->execute('block.review', ['blockId' => 'help-sequence']);
        $this->assertSame(['notice', 'approach', 'help', 'bring', 'continue-care'], $this->block('help-sequence')['runtime']['results']['itemIds']);
        $this->assertFalse($this->own('help-sequence')['grade']);
        $this->assertSame($wrong, $this->own('help-sequence')['value']['itemIds']);
        $this->expectProblem('invalid_state', fn () => $this->submit('help-sequence', ['itemIds' => array_reverse($wrong)]));
        $this->execute('stage', ['stageId' => 'neighbor-question']);
        $this->execute('stage', ['stageId' => 'neighbor-help-path']);
        $this->assertSame('revealed', $this->block('help-sequence')['runtime']['status']);
    }

    public function test_reveal_toggle_reviews_motive_and_wrong_teacher_choice_does_not_reveal_solution(): void
    {
        $this->execute('stage', ['stageId' => 'neighbor-samaritan']);
        $reveal = collect($this->public()['stage']['blocks'])->firstWhere('type', 'core.presentation');
        $this->assertSame('', $reveal['content']['text']);
        $this->submit('samaritan-motive', ['optionId' => 'reward']);
        $this->execute('presentation.toggle', ['blockId' => $reveal['id']]);
        $this->assertNotSame('', $this->block($reveal['id'])['content']['text']);
        $this->assertSame('compassion', $this->block('samaritan-motive')['runtime']['results']['optionId']);
        $this->assertFalse($this->own('samaritan-motive')['grade']);
        $this->execute('stage', ['stageId' => 'neighbor-question']);
        $choice = collect($this->public()['stage']['blocks'])->firstWhere('type', 'core.single-choice');
        $this->execute('choice.select', ['blockId' => $choice['id'], 'optionId' => 'priest']);
        $this->assertArrayNotHasKey('results', $this->block($choice['id'])['runtime']);
        $this->execute('choice.select', ['blockId' => $choice['id'], 'optionId' => 'samaritan']);
        $this->assertSame('samaritan', $this->block($choice['id'])['runtime']['results']['optionId']);
        $reveal = collect($this->public()['stage']['blocks'])->firstWhere('type', 'core.presentation');
        $this->assertTrue($reveal['runtime']['presentation']['visible']);
        $this->assertNotSame('', $reveal['content']['text']);
    }

    public function test_cross_stage_board_is_anonymous_and_only_contains_published_moderated_answers(): void
    {
        $this->execute('stage', ['stageId' => 'neighbor-priest']);
        $this->submit('priest-excuse', ['text' => 'Private original excuse']);
        $answer = SessionAnswer::query()->where('block_id', 'priest-excuse')->firstOrFail();
        $this->execute('answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => $answer->revision, 'status' => 'approved', 'displayText' => 'Public edited excuse']);
        $this->execute('answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => $answer->fresh()->revision]);
        $this->execute('answer.reply', ['answerId' => $answer->id, 'expectedAnswerRevision' => $answer->fresh()->revision, 'text' => 'Private teacher reply']);
        $this->assertSame('Private teacher reply', $this->own('priest-excuse')['privateReply']);
        $this->assertStringNotContainsString('Private teacher reply', json_encode($this->runtime->student($this->session['id'], $this->other)));
        $this->execute('stage', ['stageId' => 'neighbor-barriers']);
        $board = collect($this->public()['stage']['blocks'])->firstWhere('type', 'core.presentation');
        $this->assertSame([['answerId' => $answer->id, 'text' => 'Public edited excuse', 'discussed' => false]], $board['runtime']['board']);
        $this->execute('board.toggle', ['blockId' => $board['id'], 'answerId' => $answer->id]);
        $this->assertTrue($this->block($board['id'])['runtime']['board'][0]['discussed']);
        foreach (['Private original excuse', 'Private teacher reply', 'Private pupil', 'participantId', 'privateReply'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($this->public()));
        }
        $this->execute('answer.unpublish', ['answerId' => $answer->id, 'expectedAnswerRevision' => $answer->fresh()->revision]);
        $this->assertSame([], $this->block($board['id'])['runtime']['board']);
        $this->expectProblem('not_found', fn () => $this->execute('board.toggle', ['blockId' => $board['id'], 'answerId' => $answer->id]));
    }

    public function test_pending_question_requires_teacher_ack_and_discussion_mode_is_shared(): void
    {
        $signals = collect($this->public()['stage']['blocks'])->firstWhere('type', 'core.signals');
        $this->submit($signals['id'], ['ready' => false, 'question' => true]);
        $this->expectProblem('question_pending', fn () => $this->submit($signals['id'], ['ready' => true, 'question' => false]));
        $answer = SessionAnswer::query()->where('block_id', $signals['id'])->firstOrFail();
        $this->execute('answer.reply', ['answerId' => $answer->id, 'expectedAnswerRevision' => $answer->revision, 'text' => 'Сейчас подойду']);
        $this->assertTrue($this->own($signals['id'])['acknowledged']);
        $this->submit($signals['id'], ['ready' => true, 'question' => false]);
        $this->assertSame(1, $this->runtime->student($this->session['id'], $this->student)['kindnessPoints']);
        $this->submit($signals['id'], ['ready' => true, 'question' => false]);
        $this->assertSame(1, $this->runtime->student($this->session['id'], $this->student)['kindnessPoints']);
        $this->execute('stage', ['stageId' => 'neighbor-newcomer']);
        $discussion = collect($this->public()['stage']['blocks'])->first(fn ($block) => $block['type'] === 'core.presentation' && $block['config']['kind'] === 'discussion');
        $mode = $discussion['content']['modes'][1]['modeId'];
        $this->execute('presentation.mode', ['blockId' => $discussion['id'], 'modeId' => $mode]);
        $this->assertSame($mode, $this->block($discussion['id'])['runtime']['presentation']['modeId']);
        $this->expectProblem('invalid_action', fn () => $this->execute('presentation.mode', ['blockId' => $discussion['id'], 'modeId' => 'invalid']));
    }

    private function execute(string $action, array $payload = []): void
    {
        $this->session = $this->runtime->command($this->owner, $this->session['id'], (string) Str::uuid(), $this->session['revision'], $action, $payload)['session'];
    }

    private function submit(string $blockId, array $value): void
    {
        $this->runtime->answer($this->session['id'], $this->student, $this->session['currentStageId'], $blockId, ['stageId' => $this->session['currentStageId'], 'blockId' => $blockId, 'value' => $value]);
    }

    private function public(): array
    {
        return $this->runtime->projector($this->token);
    }

    private function block(string $id): array
    {
        return collect($this->public()['stage']['blocks'])->firstWhere('id', $id);
    }

    private function own(string $id): array
    {
        return collect($this->runtime->student($this->session['id'], $this->student)['ownAnswers'])->firstWhere('blockId', $id);
    }

    private function expectProblem(string $code, callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a rejected operation.');
        } catch (ApiProblem $problem) {
            $this->assertSame($code, $problem->problemCode);
        }
    }
}
