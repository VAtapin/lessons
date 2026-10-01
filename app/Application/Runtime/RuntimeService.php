<?php

declare(strict_types=1);

namespace App\Application\Runtime;

use App\Application\History\RetentionPolicy;
use App\Application\History\SessionAggregates;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Application\Studio\StudioService;
use App\Domain\Lessons\Audience;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Domain\Lessons\Stage;
use App\Models\LessonVersion;
use App\Models\SessionCommandReceipt;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;

final class RuntimeService
{
    public function __construct(private StudioService $studio, private BlockRegistry $registry, private RuntimeCommands $commands, private SessionTimer $timer, private RuntimeMediaProjection $mediaProjection, private RuntimeBlocks $blocks, private RuntimeAnswers $answers, private RetentionPolicy $retention, private SessionAggregates $aggregates) {}

    public function start(string $ownerKey, string $lessonId, int $expectedRevision, ?string $locale, bool $prepare = false): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $lessonId, $expectedRevision, $locale, $prepare): array {
            $version = $this->studio->release($ownerKey, $lessonId, $expectedRevision, selectedLocale: $locale);

            return $this->startSnapshot($ownerKey, $version, $locale, 'lesson', $prepare);
        });
    }

    public function startSnapshot(string $ownerKey, LessonVersion $version, ?string $locale, string $mode, bool $prepare = false): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $version, $locale, $mode, $prepare): array {
            if ($version->material->owner_key !== $ownerKey || $version->status !== 'released'
                || ! in_array($mode, ['lesson', 'rehearsal'], true)
                || $version->purpose !== ($mode === 'rehearsal' ? 'rehearsal' : 'authoring')) {
                throw new ApiProblem('not_found', 404);
            }
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
                'mode' => $mode,
                'started_at' => $prepare ? null : CarbonImmutable::now('UTC'),
                'visited_stage_ids' => $prepare ? [] : [$document->stages[0]->id],
                'join_code' => $this->newJoinCode(),
                'projector_token' => bin2hex(random_bytes(32)),
            ]);
            if ($mode === 'rehearsal') {
                SessionParticipant::create(['teaching_session_id' => $session->id, 'name' => '—']);
            }

            return $this->teacherState($session->refresh());
        });
    }

    public function again(string $ownerKey, string $sessionId): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $sessionId): array {
            $session = TeachingSession::query()->where('owner_key', $ownerKey)->lockForUpdate()->find($sessionId)
                ?? throw new ApiProblem('not_found', 404);
            $this->retention->assertOwnerReadable($session);
            if ($session->mode !== 'lesson' || $session->version->purpose !== 'authoring') {
                throw new ApiProblem('invalid_state', 409);
            }

            return $this->startSnapshot($ownerKey, $session->version, $session->locale, 'lesson');
        });
    }

    public function rehearsalPreview(string $ownerKey, string $id, string $audience): array
    {
        $session = $this->findOwned($ownerKey, $id);
        $this->retention->assertOwnerReadable($session);
        if ($session->mode !== 'rehearsal') {
            throw new ApiProblem('not_found', 404);
        }
        $target = match ($audience) {
            'student' => Audience::Student, 'projector' => Audience::Projector,
            default => throw new ApiProblem('invalid_action', 422),
        };
        $participant = $target === Audience::Student ? SessionParticipant::query()->where('teaching_session_id', $id)->firstOrFail() : null;

        return $this->publicState($session, $target, $participant);
    }

    public function rehearsalAnswer(string $ownerKey, string $id, array $body): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $id, $body): array {
            $session = TeachingSession::query()->where('owner_key', $ownerKey)->lockForUpdate()->find($id)
                ?? throw new ApiProblem('not_found', 404);
            $this->retention->assertOwnerReadable($session);
            if ($session->mode !== 'rehearsal') {
                throw new ApiProblem('not_found', 404);
            }
            $keys = array_keys($body);
            sort($keys);
            if ($keys !== ['blockId', 'stageId', 'value'] || ! is_string($body['stageId'] ?? null) || ! is_string($body['blockId'] ?? null)) {
                throw new ApiProblem('invalid_action', 422);
            }
            $participant = SessionParticipant::query()->where('teaching_session_id', $id)->firstOrFail();

            return $this->submitAnswer($session, $participant->id, $body['stageId'], $body['blockId'], $body);
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
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $sessionId, $expectedRevision, $stageId): array {
            $session = TeachingSession::query()->whereKey($sessionId)->where('owner_key', $ownerKey)->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            $this->retention->assertOwnerReadable($session);
            if ($session->revision !== $expectedRevision) {
                throw new ApiProblem('revision_conflict', 409);
            }

            $previousStage = $session->current_stage_id;
            $this->commands->apply($session, $this->document($session), 'stage', ['stageId' => $stageId], CarbonImmutable::now('UTC'));
            if ($previousStage !== $stageId) {
                $this->recordLifecycle($session, 'stage');
                $session->revision++;
                $session->save();
            }

            return $this->teacherState($session);
        });
    }

    public function command(string $ownerKey, string $sessionId, string $commandId, int $expectedRevision, string $action, array $payload, array $extraFields = []): array
    {
        return OwnerMutation::transaction([$ownerKey], function () use ($ownerKey, $sessionId, $commandId, $expectedRevision, $action, $payload, $extraFields): array {
            $session = TeachingSession::query()->whereKey($sessionId)->where('owner_key', $ownerKey)->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            $this->retention->assertOwnerReadable($session);
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
                $this->recordLifecycle($session, $action);
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
        $sessionId = TeachingSession::query()->where('join_code', strtoupper(trim($code)))->value('id')
            ?? throw new ApiProblem('not_found', 404);

        return OwnerMutation::forSession($sessionId, function () use ($code, $name, $participantMap): array {
            $session = TeachingSession::query()->where('join_code', strtoupper(trim($code)))->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            $this->retention->assertPublicReadable($session);
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
        return OwnerMutation::forSession($sessionId, function (TeachingSession $session) use ($sessionId, $participantId): array {
            $this->retention->assertPublicReadable($session);
            $participant = $this->participant($sessionId, $participantId);
            $now = CarbonImmutable::now('UTC');
            // Conditional SQL prevents concurrent polls from bypassing the throttle.
            SessionParticipant::query()->whereKey($participant->id)->where(function ($query) use ($now): void {
                $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', $now->subSeconds(config('lessons.runtime.activity_write_seconds'))->format('Y-m-d H:i:s.u'));
            })->update(['last_seen_at' => $now->format('Y-m-d H:i:s.u')]);

            return $this->publicState($session, Audience::Student, $participant);
        });
    }

    public function projector(string $token): array
    {
        $session = TeachingSession::query()->where('projector_token', $token)->first()
            ?? throw new ApiProblem('not_found', 404);
        $this->retention->assertPublicReadable($session);

        return $this->publicState($session, Audience::Projector);
    }

    public function answer(string $sessionId, ?string $participantId, string $stageId, string $blockId, array $body): array
    {
        return OwnerMutation::forSession($sessionId, function () use ($sessionId, $participantId, $stageId, $blockId, $body): array {
            // One lock serializes navigation, role capacity, moderation and answers.
            $session = TeachingSession::query()->whereKey($sessionId)->lockForUpdate()->first()
                ?? throw new ApiProblem('not_found', 404);
            $this->retention->assertPublicReadable($session);

            return $this->submitAnswer($session, $participantId, $stageId, $blockId, $body);
        });
    }

    private function submitAnswer(TeachingSession $session, ?string $participantId, string $stageId, string $blockId, array $body): array
    {
        $sessionId = $session->id;
        $participant = $this->participant($sessionId, $participantId);
        $keys = array_keys($body);
        sort($keys);
        $legacy = $keys === ['blockId', 'optionId', 'stageId'];
        if (! $legacy && $keys !== ['blockId', 'stageId', 'value']) {
            throw new ApiProblem('invalid_action', 422);
        }
        if ($session->status !== 'running') {
            throw new ApiProblem('invalid_state', 409);
        }
        if ($session->current_stage_id !== $stageId) {
            throw new ApiProblem('invalid_action', 422);
        }
        $block = $this->blocks->find($this->document($session), $blockId, $stageId);
        if ($legacy && $block->type !== 'core.single-choice') {
            throw new ApiProblem('invalid_action', 422);
        }
        $value = $legacy ? ['optionId' => $body['optionId']] : $body['value'];
        if (! is_array($value)) {
            throw new ApiProblem('invalid_action', 422);
        }
        $this->answers->submit($session, $participant, $block, $value);

        return $this->publicState($session, Audience::Student, $participant);
    }

    private function teacherState(TeachingSession $session): array
    {
        $this->retention->assertOwnerReadable($session);
        $document = $this->document($session);
        $blockSnapshot = $this->blocks->snapshot($session);
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
            'publicStage' => $this->mediaProjection->stage(
                $this->blocks->project($this->stage($document, $session->current_stage_id), $session, Audience::Projector, $blockSnapshot, $this->retention->detailsAvailable($session)),
                $session, Audience::Projector,
            ),
            'participants' => $this->retention->detailsAvailable($session) ? SessionParticipant::query()->where('teaching_session_id', $session->id)
                ->orderBy('created_at')->orderBy('id')->get()->map(fn (SessionParticipant $participant) => [
                    'id' => $participant->id, 'name' => $participant->name,
                    'connected' => $participant->last_seen_at !== null && $participant->last_seen_at->greaterThanOrEqualTo(CarbonImmutable::now('UTC')->subSeconds(config('lessons.runtime.connected_seconds'))),
                    'lastSeenAt' => $participant->last_seen_at?->utc()->toISOString(),
                ])->all() : [],
            'blockStates' => $this->blocks->allStates($document, $blockSnapshot),
            'answers' => $this->retention->detailsAvailable($session) ? $this->answers->teacher($session, $document) : [],
        ] + ($session->mode === 'lesson' ? ['joinCode' => $session->join_code,
            'projectorUrl' => url('/'.$uiLocale.'/project/'.$session->projector_token),
            'joinUrl' => url('/'.$uiLocale.'/join').'?code='.rawurlencode($session->join_code)] : []);
    }

    private function publicState(TeachingSession $session, Audience $audience, ?SessionParticipant $participant = null): array
    {
        $document = $this->document($session);
        $blockSnapshot = $this->blocks->snapshot($session);
        $state = $this->baseState($session) + [
            'stage' => $this->mediaProjection->stage(
                $this->blocks->project($this->stage($document, $session->current_stage_id), $session, $audience, $blockSnapshot),
                $session, $audience,
            ),
        ];
        if ($participant !== null) {
            $state['ownAnswers'] = $this->answers->own($session, $document, $participant, $blockSnapshot);
        }

        return $state;
    }

    private function baseState(TeachingSession $session): array
    {
        $now = CarbonImmutable::now('UTC');

        return [
            'id' => $session->id, 'revision' => $session->revision, 'locale' => $session->locale, 'currentStageId' => $session->current_stage_id,
            'mode' => $session->mode,
            'status' => $session->status, 'serverNow' => $now->toISOString(), 'timer' => $this->timer->project($session, $now),
            'message' => $session->message,
            'wave' => $session->wave_expires_at !== null && $session->wave_expires_at->greaterThan($now)
                ? ['id' => $session->wave_id, 'expiresAt' => $session->wave_expires_at->utc()->toISOString()] : null,
        ];
    }

    private function recordLifecycle(TeachingSession $session, string $action): void
    {
        if ($action === 'begin' && $session->started_at === null) {
            $session->started_at = CarbonImmutable::now('UTC');
        }
        if (in_array($action, ['begin', 'stage'], true) && $session->status === 'running' && $session->visited_stage_ids !== null) {
            $session->visited_stage_ids = array_values(array_unique([...$session->visited_stage_ids, $session->current_stage_id]));
        }
        if ($action === 'finish' && $session->finished_at === null) {
            $session->finished_at = CarbonImmutable::now('UTC');
            $session->final_aggregates = $this->aggregates->snapshot($session);
        }
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
