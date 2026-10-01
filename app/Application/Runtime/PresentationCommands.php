<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\BlockInstance;
use App\Domain\Lessons\InteractiveShape;
use App\Domain\Lessons\LessonDocument;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;

/** Presenter actions run under the existing session lock, revision and actor receipt. */
final readonly class PresentationCommands
{
    public function __construct(private RuntimeBlocks $blocks) {}

    public function supports(string $action): bool
    {
        return in_array($action, ['role.reveal.next', 'role.reveal.reset', 'sequence.select', 'sequence.reset', 'choice.select', 'presentation.toggle', 'presentation.mode', 'board.toggle'], true);
    }

    public function apply(TeachingSession $session, LessonDocument $document, string $action, array $payload): void
    {
        $required = ['blockId', ...match ($action) {
            'sequence.select' => ['itemId'], 'choice.select' => ['optionId'], 'presentation.mode' => ['modeId'], 'board.toggle' => ['answerId'], default => [],
        }];
        if (array_diff($required, array_keys($payload)) !== [] || array_diff(array_keys($payload), $required) !== [] || ! is_string($payload['blockId'] ?? null)) {
            throw new ApiProblem('invalid_action', 422);
        }
        $block = $this->blocks->find($document, $payload['blockId'], $session->current_stage_id);
        $state = $this->blocks->state($session, $block);
        $presentation = $state['presentation'] ?? [];
        if (str_starts_with($action, 'role.reveal.')) {
            $this->requireType($block, 'core.roles');
            $ids = InteractiveShape::ids($block, 'roles', 'roleId');
            $shown = $presentation['revealedRoleIds'] ?? [];
            $presentation['revealedRoleIds'] = $action === 'role.reveal.reset' || count($shown) >= count($ids) ? [] : array_slice($ids, 0, count($shown) + 1);
        } elseif (str_starts_with($action, 'sequence.')) {
            $this->requireType($block, 'core.sequence');
            $selected = $presentation['itemIds'] ?? [];
            if ($action === 'sequence.reset') {
                $presentation = ['itemIds' => []];
            } else {
                $ids = InteractiveShape::ids($block, 'items', 'itemId');
                if (! is_string($payload['itemId']) || ! in_array($payload['itemId'], $ids, true) || in_array($payload['itemId'], $selected, true) || count($selected) >= count($ids)) {
                    throw new ApiProblem('invalid_action', 422);
                }
                $correct = $block->solution['itemIds'][count($selected)] ?? null;
                if ($correct !== null && $payload['itemId'] !== $correct) {
                    $presentation['feedback'] = 'incorrect';
                } else {
                    $selected[] = $payload['itemId'];
                    $presentation = ['itemIds' => $selected, 'feedback' => count($selected) === count($ids) ? 'complete' : 'correct'];
                }
            }
        } elseif ($action === 'choice.select') {
            $this->requireType($block, 'core.single-choice');
            $ids = InteractiveShape::ids($block, 'options', 'optionId');
            if (! is_string($payload['optionId']) || ! in_array($payload['optionId'], $ids, true)) {
                throw new ApiProblem('invalid_action', 422);
            }
            $correct = ($block->solution['optionId'] ?? null) === $payload['optionId'];
            $presentation = ['optionId' => $payload['optionId'], 'feedback' => $correct ? 'correct' : 'incorrect'];
            if ($correct) {
                $this->review($session, $block);
                foreach ($document->stages as $stage) {
                    if ($stage->id === $session->current_stage_id) {
                        foreach ($stage->blocks as $reveal) {
                            if ($reveal->type === 'core.presentation' && $reveal->config['reviewBlockId'] === $block->id) {
                                $this->blocks->savePresentation($session, $reveal, ['visible' => true]);
                            }
                        }
                    }
                }
            }
        } else {
            $this->requireType($block, 'core.presentation');
            if ($action === 'presentation.toggle') {
                if ($block->config['kind'] !== 'reveal') {
                    throw new ApiProblem('invalid_action', 422);
                }
                $presentation['visible'] = ! ($presentation['visible'] ?? false);
                if ($presentation['visible'] && $block->config['reviewBlockId'] !== null) {
                    $this->review($session, $this->blocks->find($document, $block->config['reviewBlockId'], $session->current_stage_id));
                }
            } elseif ($action === 'presentation.mode') {
                if ($block->config['kind'] !== 'discussion' || ! is_string($payload['modeId']) || ! in_array($payload['modeId'], InteractiveShape::ids($block, 'modes', 'modeId'), true)) {
                    throw new ApiProblem('invalid_action', 422);
                }
                $presentation['modeId'] = $payload['modeId'];
            } else {
                if ($block->config['kind'] !== 'response-board' || ! is_int($payload['answerId'])) {
                    throw new ApiProblem('invalid_action', 422);
                }
                $answer = SessionAnswer::query()->whereKey($payload['answerId'])->where('teaching_session_id', $session->id)->whereIn('block_id', $block->config['sourceBlockIds'])
                    ->where('moderation_status', 'approved')->where('published', true)->first() ?? throw new ApiProblem('not_found', 404);
                $answer->discussed = ! $answer->discussed;
                $answer->revision++;
                $answer->save();

                return;
            }
        }
        $this->blocks->savePresentation($session, $block, $presentation);
    }

    private function requireType(BlockInstance $block, string $type): void
    {
        if ($block->type !== $type) {
            throw new ApiProblem('invalid_action', 422);
        }
    }

    private function review(TeachingSession $session, BlockInstance $block): void
    {
        if ($this->blocks->state($session, $block)['status'] === 'revealed') {
            return;
        }
        if ($this->blocks->state($session, $block)['status'] === 'prepared') {
            $this->blocks->transition($session, $block, 'block.open');
        }
        $this->blocks->transition($session, $block, 'block.review');
    }
}
