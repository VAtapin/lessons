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
use Carbon\CarbonImmutable;

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

    public function isTask(BlockInstance $block): bool
    {
        return $block->type !== 'core.presentation' && $this->registry->resolve($block->type, $block->schemaVersion) instanceof InteractiveBlockType;
    }

    public function state(TeachingSession $session, BlockInstance $block): array
    {
        $row = SessionBlockState::query()->where('teaching_session_id', $session->id)->where('block_id', $block->id)->first();
        $state = $this->readState($block, $row === null ? [] : [$block->id => ['status' => $row->status, 'presentation' => $row->presentation]]);
        if ($state['status'] === 'open' && in_array($block->id, $this->expiredTaskIds($session), true)) {
            $state['status'] = 'closed';
        }

        return $state;
    }

    private function expiredTaskIds(TeachingSession $session): array
    {
        $stage = collect($session->version->document['stages'])->firstWhere('id', $session->current_stage_id);

        return ($stage['config']['closeOnTimer'] ?? false) && (new SessionTimer)->project($session, CarbonImmutable::now('UTC'))['status'] === 'expired'
            ? array_column($stage['blocks'], 'id') : [];
    }

    /** Caller holds the session lock. Clearing/restarting an expired timer cannot reopen its tasks. */
    public function freezeExpiredTasks(TeachingSession $session): void
    {
        $ids = $this->expiredTaskIds($session);
        if ($ids === []) {
            return;
        }
        $snapshot = $this->snapshot($session);
        foreach ($ids as $id) {
            if (($snapshot[$id]['status'] ?? null) === 'closed') {
                SessionBlockState::updateOrCreate(['teaching_session_id' => $session->id, 'block_id' => $id], ['status' => 'closed', 'attempt_no' => 1]);
            }
        }
    }

    /** A fresh per-response read snapshot; mutations use state() under the session lock. */
    public function snapshot(TeachingSession $session): array
    {
        $snapshot = SessionBlockState::query()->where('teaching_session_id', $session->id)->get()->mapWithKeys(
            fn (SessionBlockState $row) => [$row->block_id => ['status' => $row->status, 'presentation' => $row->presentation]],
        )->all();
        foreach ($this->expiredTaskIds($session) as $id) {
            $authored = collect($session->version->document['stages'])->firstWhere('id', $session->current_stage_id);
            $block = collect($authored['blocks'])->firstWhere('id', $id);
            $type = $this->registry->resolve($block['type'], $block['schemaVersion']);
            if ($type instanceof InteractiveBlockType && ($snapshot[$id]['status'] ?? $type->initialState()) === 'open') {
                $snapshot[$id]['status'] = 'closed';
            }
        }

        return $snapshot;
    }

    public function readState(BlockInstance $block, array $snapshot): array
    {
        $stored = $snapshot[$block->id] ?? null;
        $state = ['blockId' => $block->id, 'status' => $stored['status'] ?? $this->interactive($block)->initialState(), 'attemptNo' => 1];
        if (! empty($stored['presentation'])) {
            $state['presentation'] = $stored['presentation'];
        }

        return $state;
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
            $action === 'block.review' && in_array($current, ['open', 'closed'], true) => 'revealed',
            default => throw new ApiProblem('invalid_state', 409),
        };
        SessionBlockState::updateOrCreate(['teaching_session_id' => $session->id, 'block_id' => $block->id], ['status' => $next, 'attempt_no' => 1]);
    }

    public function savePresentation(TeachingSession $session, BlockInstance $block, array $presentation): void
    {
        SessionBlockState::updateOrCreate(['teaching_session_id' => $session->id, 'block_id' => $block->id],
            ['status' => $this->state($session, $block)['status'], 'attempt_no' => 1, 'presentation' => $presentation]);
    }

    public function openTasks(TeachingSession $session, LessonDocument $document): void
    {
        foreach ($document->stages as $stage) {
            if ($stage->id !== $session->current_stage_id || ! ($stage->config['openTasks'] ?? false)) {
                continue;
            }
            if ($stage->config['sequentialTasks'] ?? false) {
                $tasks = array_values(array_filter($stage->blocks, fn ($block) => $this->isTask($block)));
                foreach (array_slice($tasks, 1) as $future) {
                    SessionBlockState::firstOrCreate(['teaching_session_id' => $session->id, 'block_id' => $future->id], ['status' => 'prepared', 'attempt_no' => 1]);
                }
            }
            foreach ($stage->blocks as $block) {
                if (($stage->config['sequentialTasks'] ?? false) && ! $this->isTask($block)) {
                    continue;
                }
                if ($this->registry->resolve($block->type, $block->schemaVersion) instanceof InteractiveBlockType
                    && $this->state($session, $block)['status'] === 'prepared') {
                    $this->transition($session, $block, 'block.open');
                }
                if ($stage->config['sequentialTasks'] ?? false) {
                    break;
                }
            }
        }
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
            $runtime = ['status' => $state['status'], 'attemptNo' => 1, 'mode' => $session->mode];
            if (isset($state['presentation'])) {
                $runtime['presentation'] = $state['presentation'];
            }
            if ($block->type === 'core.presentation') {
                $presentation = $state['presentation'] ?? [];
                if ($block->config['kind'] === 'reveal' && ! ($presentation['visible'] ?? false)) {
                    $view['blocks'][$index]['content']['text'] = '';
                    unset($view['blocks'][$index]['content']['quote'], $view['blocks'][$index]['content']['source'], $view['blocks'][$index]['content']['table']);
                    if (isset($block->media['image'])) {
                        unset($view['blocks'][$index]['content']['title'], $view['blocks'][$index]['content']['subtitle']);
                        $view['blocks'][$index]['media'] = [];
                    }
                }
                if ($block->config['kind'] === 'response-board' && $detailsAvailable) {
                    $runtime['board'] = SessionAnswer::query()->where('teaching_session_id', $session->id)
                        ->whereIn('block_id', $block->config['sourceBlockIds'])->where('moderation_status', 'approved')->where('published', true)
                        ->orderByDesc('id')->get()->unique('display_text')->take($block->config['maxItems'])
                        ->map(fn (SessionAnswer $answer): array => ['answerId' => $answer->id, 'text' => $answer->display_text, 'discussed' => (bool) $answer->discussed])->values()->all();
                }
            }
            if ($block->type === 'core.roles') {
                $runtime['availability'] = $this->availability($session, $block);
                if ($detailsAvailable) {
                    $runtime['summary'] = ['counts' => array_map(fn (array $role): array => ['optionId' => $role['roleId'], 'count' => $role['used']], $runtime['availability']),
                        'totalAnswers' => array_sum(array_column($runtime['availability'], 'used'))];
                }
            }
            if ($detailsAvailable && $block->type === 'core.signals') {
                $answers = SessionAnswer::query()->where('teaching_session_id', $session->id)->where('block_id', $block->id)->get();
                $runtime['summary'] = ['totalAnswers' => $answers->count(),
                    'ready' => $answers->filter(fn (SessionAnswer $answer): bool => (bool) ($answer->value['ready'] ?? false))->count(),
                    'question' => $answers->filter(fn (SessionAnswer $answer): bool => (bool) ($answer->value['question'] ?? false) && ! $answer->acknowledged)->count()];
            }
            if ($detailsAvailable && in_array($block->type, ['core.poll', 'core.single-choice', 'core.multiple-choice'], true)
                && (! ($stage->config['closeOnTimer'] ?? false) || $state['status'] === 'revealed')) {
                $runtime['summary'] = $this->pollResults($session, $block);
            }
            if ($block->type === 'core.free-response' && $audience === Audience::Projector && $detailsAvailable) {
                $published = SessionAnswer::query()->where('teaching_session_id', $session->id)
                    ->where('block_id', $block->id)->where('moderation_status', 'approved')->where('published', true)
                    ->orderBy('id')->get()->map(fn (SessionAnswer $answer): array => ['text' => $answer->display_text])->all();
                if ($published !== []) {
                    $runtime['results'] = ['published' => $published];
                }
            } elseif ($state['status'] === 'revealed') {
                if ($block->type === 'core.poll' || ($block->type === 'core.multiple-choice' && $block->solution === null)) {
                    $runtime['results'] = $this->pollResults($session, $block);
                } elseif (($result = $type->publicResult($block)) !== null) {
                    $runtime['results'] = $result;
                }
            }
            $view['blocks'][$index]['runtime'] = $runtime;
        }

        if ($stage->config['sequentialTasks'] ?? false) {
            $tasks = array_values(array_filter($stage->blocks, fn ($block) => $this->isTask($block)));
            $active = $tasks[0]->id ?? null;
            foreach ($tasks as $task) {
                if ($this->readState($task, $snapshot)['status'] !== 'prepared') {
                    $active = $task->id;
                }
            }
            $ids = array_column($tasks, 'id');
            $view['blocks'] = array_values(array_filter($view['blocks'], fn ($block) => ! in_array($block['id'], $ids, true) || $block['id'] === $active));
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
            $counts[] = ['optionId' => $option['optionId'], 'count' => $answers->filter(fn (SessionAnswer $answer): bool => $block->type === 'core.multiple-choice'
                ? in_array($option['optionId'], $answer->value['optionIds'] ?? [], true)
                : ($answer->value['optionId'] ?? $answer->option_id) === $option['optionId'])->count()];
        }

        return ['counts' => $counts, 'totalAnswers' => $answers->count()];
    }
}
