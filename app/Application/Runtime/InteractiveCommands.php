<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\ValidationException;
use App\Models\SessionAnswer;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;

final class InteractiveCommands
{
    public function __construct(private RuntimeBlocks $blocks, private RuntimeAnswers $answers) {}

    public function supports(string $action): bool
    {
        return in_array($action, ['block.open', 'block.close', 'block.reveal', 'block.review', 'answer.moderate', 'answer.publish', 'answer.unpublish', 'answer.reply', 'role.assign', 'signal.ack'], true);
    }

    /** Called within the command transaction with the TeachingSession locked. */
    public function apply(TeachingSession $session, LessonDocument $document, string $action, array $payload): void
    {
        if ($session->status === 'finished') {
            throw new ApiProblem('invalid_state', 409);
        }
        $required = match ($action) {
            'block.open', 'block.close', 'block.reveal', 'block.review' => ['blockId'],
            'answer.moderate' => ['answerId', 'expectedAnswerRevision', 'status'],
            'answer.publish', 'answer.unpublish' => ['answerId', 'expectedAnswerRevision'],
            'answer.reply' => ['answerId', 'expectedAnswerRevision', 'text'],
            'role.assign' => ['blockId', 'participantId', 'roleId'],
            'signal.ack' => ['blockId', 'participantId'],
            default => throw new ApiProblem('invalid_action', 422),
        };
        $optional = $action === 'answer.moderate' ? ['displayText'] : [];
        if (array_diff($required, array_keys($payload)) !== [] || array_diff(array_keys($payload), [...$required, ...$optional]) !== []) {
            throw new ApiProblem('invalid_action', 422);
        }
        if (str_starts_with($action, 'answer.')) {
            $this->moderate($session, $document, $action, $payload);

            return;
        }
        if (! is_string($payload['blockId']) || $payload['blockId'] === '' || strlen($payload['blockId']) > 128) {
            throw new ApiProblem('invalid_action', 422);
        }
        $block = $this->blocks->find($document, $payload['blockId'], $session->current_stage_id);
        $this->blocks->interactive($block);
        if (str_starts_with($action, 'block.')) {
            $this->blocks->transition($session, $block, $action);

            return;
        }
        if (! is_string($payload['participantId'])) {
            throw new ApiProblem('invalid_action', 422);
        }
        $participant = SessionParticipant::query()->whereKey($payload['participantId'])->where('teaching_session_id', $session->id)->first()
            ?? throw new ApiProblem('not_found', 404);
        if ($action === 'role.assign') {
            if ($block->type !== 'core.roles') {
                throw new ApiProblem('invalid_action', 422);
            }
            $this->answers->submit($session, $participant, $block, ['roleId' => $payload['roleId']]);

            return;
        }
        if ($block->type !== 'core.signals') {
            throw new ApiProblem('invalid_action', 422);
        }
        $answer = SessionAnswer::query()->where('teaching_session_id', $session->id)->where('session_participant_id', $participant->id)->where('block_id', $block->id)->first();
        if ($answer === null || ! ($this->answers->value($answer)['question'] ?? false)) {
            throw new ApiProblem('invalid_state', 409);
        }
        if (! $answer->acknowledged) {
            $answer->acknowledged = true;
            $answer->revision++;
            $answer->save();
        }
    }

    private function moderate(TeachingSession $session, LessonDocument $document, string $action, array $payload): void
    {
        if (! is_int($payload['answerId']) || $payload['answerId'] < 1
            || ! is_int($payload['expectedAnswerRevision']) || $payload['expectedAnswerRevision'] < 1) {
            throw new ApiProblem('invalid_action', 422);
        }
        $answer = SessionAnswer::query()->whereKey($payload['answerId'])->where('teaching_session_id', $session->id)->first()
            ?? throw new ApiProblem('not_found', 404);
        $block = $this->blocks->find($document, $answer->block_id);
        if ($action !== 'answer.reply' && $block->type !== 'core.free-response') {
            throw new ApiProblem('invalid_action', 422);
        }
        if ($answer->revision !== $payload['expectedAnswerRevision']) {
            throw new ApiProblem('answer_revision_conflict', 409);
        }
        if ($action === 'answer.reply') {
            if (! is_string($payload['text']) || ! mb_check_encoding($payload['text'], 'UTF-8') || trim($payload['text']) === '' || mb_strlen($payload['text']) > 1000) {
                throw new ApiProblem('invalid_action', 422);
            }
            $answer->private_reply = $payload['text'];
            if ($block->type === 'core.signals' && ($answer->value['question'] ?? false)) {
                $answer->acknowledged = true;
            }
            $answer->revision++;
            $answer->save();

            return;
        }
        if ($action === 'answer.moderate') {
            if (! in_array($payload['status'], ['approved', 'rejected'], true)
                || ($payload['status'] === 'rejected' && array_key_exists('displayText', $payload))) {
                throw new ApiProblem('invalid_action', 422);
            }
            $displayText = $payload['status'] === 'approved' ? ($payload['displayText'] ?? $this->answers->value($answer)['text']) : null;
            if (array_key_exists('displayText', $payload) && $payload['displayText'] === null) {
                throw new ApiProblem('invalid_action', 422);
            }
            if ($payload['status'] === 'approved') {
                try {
                    $displayText = $this->blocks->interactive($block)->validateAnswer($block, ['text' => $displayText])['text'];
                } catch (ValidationException) {
                    throw new ApiProblem('invalid_action', 422);
                }
            }
            $changes = ['moderation_status' => $payload['status'], 'display_text' => $displayText, 'published' => false];
        } elseif ($action === 'answer.publish') {
            $this->blocks->find($document, $block->id, $session->current_stage_id);
            if ($answer->moderation_status !== 'approved') {
                throw new ApiProblem('invalid_state', 409);
            }
            $changes = ['published' => true];
        } else {
            $changes = ['published' => false];
        }
        $answer->fill($changes);
        if ($answer->isDirty()) {
            $answer->revision++;
            $answer->save();
        }
    }
}
