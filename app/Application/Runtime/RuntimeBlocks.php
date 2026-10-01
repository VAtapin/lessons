<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\InteractiveBlockType;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\Stage;
use App\Models\SessionAnswer;
use App\Models\SessionBlockState;
use App\Models\TeachingSession;

final class RuntimeBlocks
{
    public function __construct(private BlockRegistry $registry) {}

    public function find(LessonDocument $document, string $blockId, ?string $stageId = null): BlockInstance
    {
        foreach ($document->stages as $stage) {
            if ($stageId !== null && $stage->id !== $stageId) {
                continue;
            }
            foreach ($stage->blocks as $block) {
                if ($block->id === $blockId) {
                    return $block;
                }
            }
        }

        throw new ApiProblem('invalid_action', 422);
    }

    public function interactive(BlockInstance $block): InteractiveBlockType
    {
        $type = $this->registry->resolve($block->type, $block->schemaVersion);
        if (! $type instanceof InteractiveBlockType) {
            throw new ApiProblem('invalid_action', 422);
        }

        return $type;
    }

    public function state(TeachingSession $session, BlockInstance $block): array
    {
        $status = SessionBlockState::query()->where('teaching_session_id', $session->id)->where('block_id', $block->id)->value('status')
            ?? $this->interactive($block)->initialState();

        return ['blockId' => $block->id, 'status' => $status, 'attemptNo' => 1];
    }

    /** A fresh per-response read snapshot; mutations use state() under the session lock. */
    public function snapshot(TeachingSession $session): array
    {
        return SessionBlockState::query()->where('teaching_session_id', $session->id)->pluck('status', 'block_id')->all();
    }

    public function readState(BlockInstance $block, array $snapshot): array
    {
        return ['blockId' => $block->id, 'status' => $snapshot[$block->id] ?? $this->interactive($block)->initialState(), 'attemptNo' => 1];
    }

    public function allStates(LessonDocument $document, array $snapshot): array
    {
        $states = [];
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                if ($this->registry->resolve($block->type, $block->schemaVersion) instanceof InteractiveBlockType) {
                    $states[] = $this->readState($block, $snapshot);
                }
            }
        }

        return $states;
    }

    public function transition(TeachingSession $session, BlockInstance $block, string $action): void
    {
        $current = $this->state($session, $block)['status'];
        $next = match (true) {
            $action === 'block.open' && in_array($current, ['prepared', 'closed'], true) => 'open',
            $action === 'block.close' && $current === 'open' => 'closed',
            $action === 'block.reveal' && $current === 'closed' => 'revealed',
            default => throw new ApiProblem('invalid_state', 409),
        };
        SessionBlockState::updateOrCreate(['teaching_session_id' => $session->id, 'block_id' => $block->id], ['status' => $next, 'attempt_no' => 1]);
    }

    /** Authored audience filtering runs before runtime fields and media URLs are added. */
    public function project(Stage $stage, TeachingSession $session, Audience $audience, array $snapshot, bool $detailsAvailable = true): array
    {
        $view = $stage->project($audience, $session->locale);
        foreach ($stage->blocks as $index => $block) {
            $type = $this->registry->resolve($block->type, $block->schemaVersion);
            if (! $type instanceof InteractiveBlockType) {
                continue;
            }
            $state = $this->readState($block, $snapshot);
            $runtime = ['status' => $state['status'], 'attemptNo' => 1];
            if ($block->type === 'core.roles') {
                $runtime['availability'] = $this->availability($session, $block);
            }
            if ($block->type === 'core.free-response' && $audience === Audience::Projector && $detailsAvailable) {
                $published = SessionAnswer::query()->where('teaching_session_id', $session->id)
                    ->where('block_id', $block->id)->where('moderation_status', 'approved')->where('published', true)
                    ->orderBy('id')->get()->map(fn (SessionAnswer $answer): array => ['text' => $answer->display_text])->all();
                if ($published !== []) {
                    $runtime['results'] = ['published' => $published];
                }
            } elseif ($state['status'] === 'revealed') {
                if ($block->type === 'core.poll') {
                    $runtime['results'] = $this->pollResults($session, $block);
                } elseif (($result = $type->publicResult($block)) !== null) {
                    $runtime['results'] = $result;
                }
            }
            $view['blocks'][$index]['runtime'] = $runtime;
        }

        return $view;
    }

    public function availability(TeachingSession $session, BlockInstance $block): array
    {
        $answers = SessionAnswer::query()->where('teaching_session_id', $session->id)->where('block_id', $block->id)->get();
        $availability = [];
        foreach ($block->config['capacities'] as $roleId => $capacity) {
            $roleId = (string) $roleId;
            $availability[] = ['roleId' => $roleId, 'used' => $answers->filter(fn (SessionAnswer $answer): bool => ($answer->value['roleId'] ?? null) === $roleId)->count(), 'capacity' => $capacity];
        }

        return $availability;
    }

    private function pollResults(TeachingSession $session, BlockInstance $block): array
    {
        $answers = SessionAnswer::query()->where('teaching_session_id', $session->id)->where('block_id', $block->id)->get();
        $counts = [];
        foreach ($block->content[$session->locale]['options'] as $option) {
            $counts[] = ['optionId' => $option['optionId'], 'count' => $answers->filter(fn (SessionAnswer $answer): bool => ($answer->value['optionId'] ?? null) === $option['optionId'])->count()];
        }

        return ['counts' => $counts, 'totalAnswers' => $answers->count()];
    }
}
