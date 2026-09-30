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
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Illuminate\Support\Facades\DB;

final class RuntimeService
{
    public function __construct(private StudioService $studio, private BlockRegistry $registry) {}

    public function start(string $ownerKey, string $lessonId, int $expectedRevision, ?string $locale): array
    {
        return DB::transaction(function () use ($ownerKey, $lessonId, $expectedRevision, $locale): array {
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
                'join_code' => $this->newJoinCode(),
                'projector_token' => bin2hex(random_bytes(32)),
            ]);

            return $this->teacherState($session);
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

            $this->stage($this->document($session), $stageId);
            if ($session->current_stage_id !== $stageId) {
                $session->current_stage_id = $stageId;
                $session->revision++;
                $session->save();
            }

            return $this->teacherState($session);
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
            $participant ??= SessionParticipant::create(['teaching_session_id' => $session->id, 'name' => $name]);

            return ['participant' => ['id' => $participant->id, 'name' => $participant->name], 'sessionId' => $session->id];
        });
    }

    public function student(string $sessionId, ?string $participantId): array
    {
        $session = TeachingSession::query()->find($sessionId) ?? throw new ApiProblem('not_found', 404);
        $participant = $this->participant($sessionId, $participantId);

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
        $uiLocales = config('lessons.ui_locales');
        $uiLocale = config('app.locale');
        if (! in_array($uiLocale, $uiLocales, true)) {
            $uiLocale = $uiLocales[0];
        }

        return $this->baseState($session) + [
            'document' => $this->document($session)->project(Audience::Teacher, $session->locale),
            'joinCode' => $session->join_code,
            'projectorUrl' => url('/'.$uiLocale.'/project/'.$session->projector_token),
            'participants' => SessionParticipant::query()->where('teaching_session_id', $session->id)
                ->orderBy('created_at')->orderBy('id')->get()->map(fn (SessionParticipant $participant) => [
                    'id' => $participant->id, 'name' => $participant->name,
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
            'stage' => $this->stage($this->document($session), $session->current_stage_id)->project($audience, $session->locale),
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
        return ['id' => $session->id, 'revision' => $session->revision, 'locale' => $session->locale, 'currentStageId' => $session->current_stage_id];
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
