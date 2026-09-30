<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\Shared\ApiProblem;
use App\Application\Studio\StudioService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\Stage;
use App\Models\SessionAnswer;
use App\Models\SessionCommandReceipt;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RuntimeService
{
    public function __construct(private StudioService $studio, private BlockRegistry $registry, private RuntimeCommands $commands, private SessionTimer $timer, private RuntimeMediaProjection $mediaProjection) {}

    public function start(string $ownerKey, string $lessonId, int $expectedRevision, ?string $locale, bool $prepare = false): array
    {
        return DB::transaction(function () use ($ownerKey, $lessonId, $expectedRevision, $locale, $prepare): array {
            $version = $this->studio->release($ownerKey, $lessonId, $expectedRevision);
            $document = LessonDocument::fromArray($version->document, $this->registry);
            $locale ??= $document->defaultLocale;
            if (! in_array($locale, $document->locales, true)) {
                throw new ApiProblem('invalid_action', 422);
            }

            $session = TeachingSession::create([
                'lesson_version_id' => $version->id,
                'owner_key' => $ownerKey,
                'locale' => $locale,
                'current_stage_id' => $document->stages[0]->id,
                'revision' => 1,
                'status' => $prepare ? 'prepared' : 'running',
                'join_code' => $this->newJoinCode(),
                'projector_token' => bin2hex(random_bytes(32)),
            ]);

            return $this->teacherState($session->refresh());
        });
    }

    public function findOwned(string $ownerKey, string $sessionId): TeachingSession
    {
        return TeachingSession::query()->whereKey($sessionId)->where('owner_key', $ownerKey)->first()
            ?? throw new ApiProblem('not_found', 404);
    }

    public function teacher(string $ownerKey, string $sessionId): array
    {
        return $this->teacherState($this->findOwned($ownerKey, $sessionId));
    }

    public function navigate(string $ownerKey, string $sessionId, int $expectedRevision, string $stageId): array
    {
        return DB::transaction(function () use ($ownerKey, $sessionId, $expectedRevision, $stageId): array {
            $session = TeachingSession::query()->whereKey($sessionId)->where('owner_key', $ownerKey)->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            if ($session->revision !== $expectedRevision) {
                throw new ApiProblem('revision_conflict', 409);
            }

            $previousStage = $session->current_stage_id;
            $this->commands->apply($session, $this->document($session), 'stage', ['stageId' => $stageId], CarbonImmutable::now('UTC'));
            if ($previousStage !== $stageId) {
                $session->revision++;
                $session->save();
            }

            return $this->teacherState($session);
        });
    }

    public function command(string $ownerKey, string $sessionId, string $commandId, int $expectedRevision, string $action, array $payload, array $extraFields = []): array
    {
        return DB::transaction(function () use ($ownerKey, $sessionId, $commandId, $expectedRevision, $action, $payload, $extraFields): array {
            $session = TeachingSession::query()->whereKey($sessionId)->where('owner_key', $ownerKey)->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            $commandId = strtolower($commandId);
            $fingerprint = $this->commands->fingerprint($expectedRevision, $action, $payload, $extraFields);
            $receipt = SessionCommandReceipt::query()->where('teaching_session_id', $sessionId)->where('command_id', $commandId)->first();
            if ($receipt !== null) {
                if (! hash_equals($receipt->fingerprint, $fingerprint)) {
                    throw new RuntimeConflict('command_conflict', $this->teacherState($session));
                }

                return ['session' => $this->teacherState($session), 'acknowledgedCommandId' => $commandId];
            }
            if ($session->revision !== $expectedRevision) {
                throw new RuntimeConflict('revision_conflict', $this->teacherState($session));
            }
            if ($extraFields !== []) {
                throw new ApiProblem('invalid_action', 422);
            }
            try {
                $this->commands->apply($session, $this->document($session), $action, $payload, CarbonImmutable::now('UTC'));
            } catch (ApiProblem $problem) {
                if ($problem->status === 409) {
                    throw new RuntimeConflict($problem->problemCode, $this->teacherState($session));
                }
                throw $problem;
            }
            $session->revision++;
            $session->save();
            SessionCommandReceipt::create(['teaching_session_id' => $sessionId, 'command_id' => $commandId, 'fingerprint' => $fingerprint]);

            return ['session' => $this->teacherState($session), 'acknowledgedCommandId' => $commandId];
        });
    }

    /** The caller stores the returned participant ID only in the server session. */
    public function join(string $code, string $name, array $participantMap): array
    {
        return DB::transaction(function () use ($code, $name, $participantMap): array {
            $session = TeachingSession::query()->where('join_code', strtoupper(trim($code)))->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            $name = trim($name);
            if ($name === '') {
                throw new ApiProblem('invalid_action', 422);
            }

            $participantId = $participantMap[$session->id] ?? null;
            $participant = is_string($participantId)
                ? SessionParticipant::query()->whereKey($participantId)->where('teaching_session_id', $session->id)->first()
                : null;
            if ($participant === null && $session->status === 'finished') {
                throw new ApiProblem('invalid_state', 409);
            }
            $participant ??= SessionParticipant::create(['teaching_session_id' => $session->id, 'name' => $name, 'last_seen_at' => CarbonImmutable::now('UTC')]);

            return ['participant' => ['id' => $participant->id, 'name' => $participant->name], 'sessionId' => $session->id];
        });
    }

    public function student(string $sessionId, ?string $participantId): array
    {
        $session = TeachingSession::query()->find($sessionId) ?? throw new ApiProblem('not_found', 404);
        $participant = $this->participant($sessionId, $participantId);
        $now = CarbonImmutable::now('UTC');
        // Conditional SQL prevents concurrent polls from bypassing the throttle.
        SessionParticipant::query()->whereKey($participant->id)->where(function ($query) use ($now): void {
            $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', $now->subSeconds(config('lessons.runtime.activity_write_seconds'))->format('Y-m-d H:i:s.u'));
        })->update(['last_seen_at' => $now->format('Y-m-d H:i:s.u')]);

        return $this->publicState($session, Audience::Student, $participant);
    }

    public function projector(string $token): array
    {
        $session = TeachingSession::query()->where('projector_token', $token)->first()
            ?? throw new ApiProblem('not_found', 404);

        return $this->publicState($session, Audience::Projector);
    }

    public function answer(string $sessionId, ?string $participantId, string $stageId, string $blockId, string $optionId): array
    {
        return DB::transaction(function () use ($sessionId, $participantId, $stageId, $blockId, $optionId): array {
            // One lock serializes navigation and all answers for this session.
            $session = TeachingSession::query()->whereKey($sessionId)->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            $participant = $this->participant($sessionId, $participantId);
            if ($session->status !== 'running') {
                throw new ApiProblem('invalid_state', 409);
            }
            if ($session->current_stage_id !== $stageId) {
                throw new ApiProblem('invalid_action', 422);
            }

            $stage = $this->stage($this->document($session), $stageId);
            $block = null;
            foreach ($stage->blocks as $candidate) {
                if ($candidate->id === $blockId) {
                    $block = $candidate;
                    break;
                }
            }
            if ($block === null || $block->type !== 'core.single-choice'
                || ! in_array($optionId, array_column($block->content[$session->locale]['options'], 'optionId'), true)) {
                throw new ApiProblem('invalid_action', 422);
            }

            $answer = SessionAnswer::query()->where('teaching_session_id', $sessionId)
                ->where('session_participant_id', $participant->id)->where('block_id', $blockId)->first();
            if ($answer !== null && $answer->option_id !== $optionId && ! $block->config['allowRepeat']) {
                throw new ApiProblem('answer_locked', 409);
            }
            if ($answer === null) {
                SessionAnswer::create([
                    'teaching_session_id' => $sessionId, 'session_participant_id' => $participant->id,
                    'block_id' => $blockId, 'option_id' => $optionId,
                ]);
            } elseif ($answer->option_id !== $optionId) {
                $answer->update(['option_id' => $optionId]);
            }

            return $this->publicState($session, Audience::Student, $participant);
        });
    }

    private function teacherState(TeachingSession $session): array
    {
        $document = $this->document($session);
        $teacherDocument = $document->project(Audience::Teacher, $session->locale);
        $teacherDocument['stages'] = array_map(
            fn (array $stage): array => $this->mediaProjection->stage($stage, $session, Audience::Teacher),
            $teacherDocument['stages'],
        );
        $uiLocales = config('lessons.ui_locales');
        $uiLocale = config('app.locale');
        if (! in_array($uiLocale, $uiLocales, true)) {
            $uiLocale = $uiLocales[0];
        }

        return $this->baseState($session) + [
            'document' => $teacherDocument,
            'joinCode' => $session->join_code,
            'projectorUrl' => url('/'.$uiLocale.'/project/'.$session->projector_token),
            'joinUrl' => url('/'.$uiLocale.'/join').'?code='.rawurlencode($session->join_code),
            'publicStage' => $this->mediaProjection->stage(
                $this->stage($document, $session->current_stage_id)->project(Audience::Projector, $session->locale),
                $session, Audience::Projector,
            ),
            'participants' => SessionParticipant::query()->where('teaching_session_id', $session->id)
                ->orderBy('created_at')->orderBy('id')->get()->map(fn (SessionParticipant $participant) => [
                    'id' => $participant->id, 'name' => $participant->name,
                    'connected' => $participant->last_seen_at !== null && $participant->last_seen_at->greaterThanOrEqualTo(CarbonImmutable::now('UTC')->subSeconds(config('lessons.runtime.connected_seconds'))),
                    'lastSeenAt' => $participant->last_seen_at?->utc()->toISOString(),
                ])->all(),
            'answers' => SessionAnswer::query()->where('teaching_session_id', $session->id)
                ->orderBy('id')->get()->map(fn (SessionAnswer $answer) => [
                    'participantId' => $answer->session_participant_id, 'blockId' => $answer->block_id, 'optionId' => $answer->option_id,
                ])->all(),
        ];
    }

    private function publicState(TeachingSession $session, Audience $audience, ?SessionParticipant $participant = null): array
    {
        $state = $this->baseState($session) + [
            'stage' => $this->mediaProjection->stage(
                $this->stage($this->document($session), $session->current_stage_id)->project($audience, $session->locale),
                $session, $audience,
            ),
        ];
        if ($participant !== null) {
            $state['ownAnswers'] = SessionAnswer::query()->where('teaching_session_id', $session->id)
                ->where('session_participant_id', $participant->id)->orderBy('id')->get()
                ->map(fn (SessionAnswer $answer) => ['blockId' => $answer->block_id, 'optionId' => $answer->option_id])->all();
        }

        return $state;
    }

    private function baseState(TeachingSession $session): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            'id' => $session->id, 'revision' => $session->revision, 'locale' => $session->locale, 'currentStageId' => $session->current_stage_id,
            'status' => $session->status, 'serverNow' => $now->toISOString(), 'timer' => $this->timer->project($session, $now),
            'message' => $session->message,
            'wave' => $session->wave_expires_at !== null && $session->wave_expires_at->greaterThan($now)
                ? ['id' => $session->wave_id, 'expiresAt' => $session->wave_expires_at->utc()->toISOString()] : null,
        ];
    }

    private function document(TeachingSession $session): LessonDocument
    {
        return LessonDocument::fromArray($session->version->document, $this->registry);
    }

    private function stage(LessonDocument $document, string $stageId): Stage
    {
        foreach ($document->stages as $stage) {
            if ($stage->id === $stageId) {
                return $stage;
            }
        }

        throw new ApiProblem('invalid_action', 422);
    }

    private function participant(string $sessionId, ?string $participantId): SessionParticipant
    {
        if ($participantId === null) {
            throw new ApiProblem('not_found', 404);
        }

        return SessionParticipant::query()->whereKey($participantId)->where('teaching_session_id', $sessionId)->first()
            ?? throw new ApiProblem('not_found', 404);
    }

    private function newJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (TeachingSession::query()->where('join_code', $code)->exists());

        return $code;
    }
}
