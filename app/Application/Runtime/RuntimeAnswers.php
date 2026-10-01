<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\SessionAnswer;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;

final class RuntimeAnswers
{
    public function __construct(private RuntimeBlocks $blocks) {}

    public function value(SessionAnswer $answer): array
    {
        return $answer->value ?? ['optionId' => $answer->option_id];
    }

    /** Caller holds the TeachingSession row lock for the entire write. */
    public function submit(TeachingSession $session, SessionParticipant $participant, BlockInstance $block, array $value): SessionAnswer
    {
        if ($this->blocks->state($session, $block)['status'] !== 'open') {
            throw new ApiProblem('invalid_state', 409);
        }
        try {
            $value = $this->blocks->interactive($block)->validateAnswer($block, $value);
        } catch (ValidationException) {
            throw new ApiProblem('invalid_action', 422);
        }
        $answer = SessionAnswer::query()->where('teaching_session_id', $session->id)
            ->where('session_participant_id', $participant->id)->where('block_id', $block->id)->first();
        if ($answer !== null && $this->value($answer) === $value) {
            return $answer;
        }
        if ($answer !== null && ! in_array($block->type, ['core.roles', 'core.signals'], true) && ! $block->config['allowRepeat']) {
            throw new ApiProblem('answer_locked', 409);
        }
        if ($block->type === 'core.roles' && $value['roleId'] !== null) {
            foreach ($this->blocks->availability($session, $block) as $role) {
                if ($role['roleId'] === $value['roleId'] && $role['used'] >= $role['capacity']) {
                    throw new ApiProblem('role_full', 409);
                }
            }
        }
        $previous = $answer !== null ? $this->value($answer) : null;
        $answer ??= new SessionAnswer(['teaching_session_id' => $session->id, 'session_participant_id' => $participant->id, 'block_id' => $block->id, 'revision' => 0]);
        $answer->revision++;
        $answer->value = $value;
        $answer->option_id = $block->type === 'core.single-choice' ? $value['optionId'] : null;
        if ($block->type === 'core.free-response') {
            $answer->moderation_status = 'pending';
            $answer->display_text = null;
            $answer->published = false;
        }
        if ($block->type === 'core.signals' && (! $value['question'] || ! ($previous['question'] ?? false))) {
            $answer->acknowledged = false;
        }
        $answer->save();

        return $answer;
    }

    public function teacher(TeachingSession $session, LessonDocument $document): array
    {
        return SessionAnswer::query()->where('teaching_session_id', $session->id)->orderBy('id')->get()
            ->map(function (SessionAnswer $answer) use ($document): array {
                $block = $this->blocks->find($document, $answer->block_id);
                $dto = $this->base($answer, $block) + ['participantId' => $answer->session_participant_id,
                    'grade' => $this->blocks->interactive($block)->grade($block, $this->value($answer)),
                    'moderation' => $block->type === 'core.free-response' ? ['status' => $answer->moderation_status, 'displayText' => $answer->display_text, 'published' => $answer->published] : null,
                    'acknowledged' => $block->type === 'core.signals' && $answer->acknowledged];

                return $dto;
            })->all();
    }

    public function own(TeachingSession $session, LessonDocument $document, SessionParticipant $participant, array $snapshot): array
    {
        return SessionAnswer::query()->where('teaching_session_id', $session->id)->where('session_participant_id', $participant->id)->orderBy('id')->get()
            ->map(function (SessionAnswer $answer) use ($document, $snapshot): array {
                $block = $this->blocks->find($document, $answer->block_id);
                $dto = $this->base($answer, $block) + ['status' => $block->type === 'core.free-response' ? $answer->moderation_status : 'submitted',
                    'grade' => $this->blocks->readState($block, $snapshot)['status'] === 'revealed'
                        ? $this->blocks->interactive($block)->grade($block, $this->value($answer)) : null];
                if ($block->type === 'core.signals') {
                    $dto['acknowledged'] = $answer->acknowledged;
                }

                return $dto;
            })->all();
    }

    private function base(SessionAnswer $answer, BlockInstance $block): array
    {
        $dto = ['id' => $answer->id, 'revision' => $answer->revision, 'blockId' => $answer->block_id, 'attemptNo' => 1, 'value' => $this->value($answer)];
        if ($block->type === 'core.single-choice') {
            $dto['optionId'] = $this->value($answer)['optionId'];
        }

        return $dto;
    }
}
