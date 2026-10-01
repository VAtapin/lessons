<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\ApiProblem;
use App\Domain\Lessons\LessonDocument;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class RuntimeCommands
{
    public function __construct(private SessionTimer $timer, private InteractiveCommands $interactive, private PresentationCommands $presentation, private RuntimeBlocks $blocks) {}

    public function fingerprint(int $revision, string $action, array $payload, array $extraFields = []): string
    {
        return hash('sha256', json_encode([$revision, $action, $this->canonical($payload), $this->canonical($extraFields)], JSON_THROW_ON_ERROR));
    }

    public function apply(TeachingSession $session, LessonDocument $document, string $action, array $payload, CarbonImmutable $now): void
    {
        if ($this->presentation->supports($action)) {
            if ($session->status === 'finished') {
                throw new ApiProblem('invalid_state', 409);
            }
            $this->presentation->apply($session, $document, $action, $payload);

            return;
        }
        if ($this->interactive->supports($action)) {
            $this->interactive->apply($session, $document, $action, $payload);

            return;
        }
        $this->validate($action, $payload);
        if ($session->status === 'finished') {
            throw new ApiProblem('invalid_state', 409);
        }

        switch ($action) {
            case 'begin':
                $this->requireStatus($session, 'prepared');
                $session->status = 'running';
                $this->blocks->openTasks($session, $document);
                break;
            case 'pause':
                $this->requireStatus($session, 'running');
                $session->timer_resume_on_session_resume = $this->timer->project($session, $now)['status'] === 'running';
                if ($session->timer_resume_on_session_resume) {
                    $this->timer->pause($session, $now);
                }
                $session->status = 'paused';
                break;
            case 'resume':
                $this->requireStatus($session, 'paused');
                if ($session->timer_resume_on_session_resume) {
                    $this->timer->resume($session, $now);
                }
                $session->timer_resume_on_session_resume = false;
                $session->status = 'running';
                break;
            case 'finish':
                if ($this->timer->project($session, $now)['status'] === 'running') {
                    $this->timer->pause($session, $now);
                }
                $session->timer_resume_on_session_resume = false;
                $session->status = 'finished';
                $session->wave_id = null;
                $session->wave_expires_at = null;
                $session->join_projection = false;
                $session->message = null;
                break;
            case 'stage':
                if (! in_array($payload['stageId'], array_column($document->stages, 'id'), true)) {
                    throw new ApiProblem('invalid_action', 422);
                }
                $session->current_stage_id = $payload['stageId'];
                $this->blocks->openTasks($session, $document);
                break;
            case 'timer.start':
                $this->requireStatus($session, 'running');
                $this->timer->start($session, $payload['seconds'], $now);
                break;
            case 'timer.pause':
                $this->requireStatus($session, 'running');
                $this->timer->pause($session, $now);
                $session->timer_resume_on_session_resume = false;
                break;
            case 'timer.resume':
                $this->requireStatus($session, 'running');
                $this->timer->resume($session, $now);
                break;
            case 'timer.clear':
                $this->timer->clear($session);
                break;
            case 'message.set':
                $session->message = $payload['text'];
                break;
            case 'message.clear':
                $session->message = null;
                break;
            case 'join.show':
            case 'join.hide':
                if ($session->mode !== 'lesson') {
                    throw new ApiProblem('invalid_state', 409);
                }
                $session->join_projection = $action === 'join.show';
                break;
            case 'wave':
                $session->wave_id = (string) Str::uuid();
                $session->wave_expires_at = $now->addSeconds(config('lessons.runtime.wave_seconds'));
                break;
        }
    }

    private function validate(string $action, array $payload): void
    {
        $required = match ($action) {
            'stage' => ['stageId'], 'timer.start' => ['seconds'], 'message.set' => ['text'],
            'begin', 'pause', 'resume', 'finish', 'timer.pause', 'timer.resume', 'timer.clear', 'message.clear', 'wave', 'join.show', 'join.hide' => [],
            default => throw new ApiProblem('invalid_action', 422),
        };
        $keys = array_keys($payload);
        sort($keys);
        if ($keys !== $required
            || ($action === 'stage' && (! is_string($payload['stageId']) || $payload['stageId'] === '' || strlen($payload['stageId']) > 128))
            || ($action === 'timer.start' && (! is_int($payload['seconds']) || $payload['seconds'] < 1 || $payload['seconds'] > config('lessons.runtime.timer_max_seconds')))
            || ($action === 'message.set' && (! is_string($payload['text']) || ! mb_check_encoding($payload['text'], 'UTF-8') || mb_strlen($payload['text']) > 1000))) {
            throw new ApiProblem('invalid_action', 422);
        }
    }

    private function requireStatus(TeachingSession $session, string $status): void
    {
        if ($session->status !== $status) {
            throw new ApiProblem('invalid_state', 409);
        }
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }

        return $value;
    }
}
