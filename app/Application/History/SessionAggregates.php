<?php

declare(strict_types=1);

namespace App\Application\History;

use App\Application\Runtime\RuntimeAnswers;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\InteractiveBlockType;
use App\Domain\Lessons\LessonDocument;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;

final readonly class SessionAggregates
{
    public function __construct(private BlockRegistry $registry, private RuntimeAnswers $answers) {}

    public function snapshot(TeachingSession $session): array
    {
        $document = LessonDocument::fromArray($session->version->document, $this->registry);
        $answers = SessionAnswer::query()->where('teaching_session_id', $session->id)->get()->groupBy('block_id');
        $result = [];
        foreach ($document->stages as $stage) {
            foreach ($stage->blocks as $block) {
                $type = $this->registry->resolve($block->type, $block->schemaVersion);
                if (! $type instanceof InteractiveBlockType) {
                    continue;
                }
                $row = ['stageId' => $stage->id, 'blockId' => $block->id, 'type' => $block->type,
                    'schemaVersion' => $block->schemaVersion, 'submittedCount' => 0, 'gradedCount' => 0, 'correctCount' => 0, 'incorrectCount' => 0];
                $content = $block->content[$session->locale];
                if (in_array($block->type, ['core.single-choice', 'core.multiple-choice', 'core.poll'], true)) {
                    $row['options'] = array_map(fn (array $option): array => ['optionId' => $option['optionId'], 'count' => 0], $content['options']);
                } elseif ($block->type === 'core.roles') {
                    $row['roles'] = array_map(fn (array $role): array => ['roleId' => $role['roleId'], 'count' => 0], $content['roles']);
                } elseif ($block->type === 'core.signals') {
                    $row['signals'] = ['readyCount' => 0, 'questionCount' => 0];
                }
                foreach ($answers->get($block->id, []) as $answer) {
                    $value = $this->answers->value($answer);
                    $row['submittedCount']++;
                    $grade = $type->grade($block, $value);
                    if ($grade !== null) {
                        $row['gradedCount']++;
                        $row[$grade ? 'correctCount' : 'incorrectCount']++;
                    }
                    foreach ($row['options'] ?? [] as $index => $option) {
                        if (in_array($option['optionId'], $value['optionIds'] ?? [$value['optionId'] ?? null], true)) {
                            $row['options'][$index]['count']++;
                        }
                    }
                    foreach ($row['roles'] ?? [] as $index => $role) {
                        if ($role['roleId'] === ($value['roleId'] ?? null)) {
                            $row['roles'][$index]['count']++;
                        }
                    }
                    if (isset($row['signals'])) {
                        $row['signals']['readyCount'] += (int) $value['ready'];
                        $row['signals']['questionCount'] += (int) $value['question'];
                    }
                }
                $result[] = $row;
            }
        }

        return $result;
    }
}
