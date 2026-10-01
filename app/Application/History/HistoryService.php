<?php

declare(strict_types=1);

namespace App\Application\History;

use App\Application\Runtime\RuntimeAnswers;
use App\Application\Runtime\RuntimeConflict;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\LessonDocument;
use App\Models\LessonMaterial;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Throwable;

final readonly class HistoryService
{
    public function __construct(private RetentionPolicy $policy, private SessionAggregates $aggregates, private RuntimeAnswers $answers, private BlockRegistry $registry, private RuntimeService $runtime) {}

    public function list(string $owner, array $filters): array
    {
        $status = $filters['status'] ?? null;
        $mode = $filters['mode'] ?? null;
        if (($status !== null && ! in_array($status, ['prepared', 'running', 'paused', 'finished'], true))
            || ($mode !== null && ! in_array($mode, ['lesson', 'rehearsal'], true))) {
            throw new ApiProblem('invalid_action', 422);
        }
        $scope = hash('sha256', json_encode([$owner, $status, $mode], JSON_THROW_ON_ERROR));
        $account = User::query()->where('owner_key', $owner)->exists();
        $now = CarbonImmutable::now('UTC');
        $query = TeachingSession::query()->where('owner_key', $owner)->with('version')->orderByDesc('created_at')->orderByDesc('id');
        // SQL removes expired rows; the one-day calendar-year margin is checked
        // precisely below, including Feb 29 -> Feb 28 cutoffs.
        $query->where(fn ($q) => $q->where('status', '!=', 'finished')
            ->orWhere(fn ($q) => $q->where('mode', 'lesson')->where(fn ($q) => $q->whereNull('finished_at')->orWhere('finished_at', '>',
                $account ? $now->subYearsNoOverflow(2)->subDay() : $now->subDays(30))))
            ->orWhere(fn ($q) => $q->where('mode', 'rehearsal')->where('created_at', '>', $now->subDays(7))));
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($mode !== null) {
            $query->where('mode', $mode);
        }
        if (isset($filters['cursor'])) {
            try {
                $cursor = json_decode(Crypt::decryptString($filters['cursor']), true, flags: JSON_THROW_ON_ERROR);
                if (($cursor['scope'] ?? null) !== $scope || ! is_string($cursor['id'] ?? null) || ! is_string($cursor['created'] ?? null)) {
                    throw new \RuntimeException;
                }
                $query->where(fn ($q) => $q->where('created_at', '<', $cursor['created'])
                    ->orWhere(fn ($q) => $q->where('created_at', $cursor['created'])->where('id', '<', $cursor['id'])));
            } catch (Throwable) {
                throw new ApiProblem('invalid_action', 422);
            }
        }
        $sessions = [];
        $last = null;
        $more = false;
        $scan = null;
        do {
            $batchQuery = clone $query;
            if ($scan !== null) {
                $batchQuery->where(fn ($q) => $q->where('created_at', '<', $scan->created_at)
                    ->orWhere(fn ($q) => $q->where('created_at', $scan->created_at)->where('id', '<', $scan->id)));
            }
            $batch = $batchQuery->limit(31)->get();
            foreach ($batch as $session) {
                $scan = $session;
                if ($this->policy->expired($this->policy->historyExpiresAt($session, $account))) {
                    continue;
                }
                if (count($sessions) === 30) {
                    $more = true;
                    break;
                }
                $sessions[] = $this->summary($session, $account);
                $last = $session;
            }
        } while (! $more && count($batch) === 31);

        return ['sessions' => $sessions, 'nextCursor' => $more ? Crypt::encryptString(json_encode([
            'scope' => $scope, 'created' => $last->created_at->format('Y-m-d H:i:s.u'), 'id' => $last->id,
        ], JSON_THROW_ON_ERROR)) : null];
    }

    public function findOwned(string $owner, string $id): TeachingSession
    {
        $session = TeachingSession::query()->where('owner_key', $owner)->with('version.material')->find($id)
            ?? throw new ApiProblem('not_found', 404);
        $this->policy->assertOwnerReadable($session);

        return $session;
    }

    public function detail(string $owner, string $id): array
    {
        return $this->present($this->findOwned($owner, $id));
    }

    public function present(TeachingSession $session): array
    {
        $this->policy->assertOwnerReadable($session);
        $available = $this->policy->detailsAvailable($session);
        $document = LessonDocument::fromArray($session->version->document, $this->registry);

        return $this->summary($session) + ['aggregates' => $session->final_aggregates ?? $this->aggregates->snapshot($session),
            'teacherNotes' => $session->teacher_notes ?? '',
            'participants' => $available ? SessionParticipant::query()->where('teaching_session_id', $session->id)->orderBy('created_at')->orderBy('id')->get()->map(fn ($participant) => [
                'id' => $participant->id, 'name' => $participant->name,
                'connected' => $participant->last_seen_at !== null && $participant->last_seen_at->greaterThanOrEqualTo(CarbonImmutable::now('UTC')->subSeconds(config('lessons.runtime.connected_seconds'))),
                'lastSeenAt' => $participant->last_seen_at?->utc()->toISOString(),
            ])->all() : [], 'answers' => $available ? $this->answers->teacher($session, $document) : []];
    }

    public function notes(string $owner, string $id, int $revision, string $notes): array
    {
        return OwnerMutation::transaction([$owner], function () use ($owner, $id, $revision, $notes): array {
            $session = TeachingSession::query()->where('owner_key', $owner)->lockForUpdate()->find($id)
                ?? throw new ApiProblem('not_found', 404);
            $this->policy->assertOwnerReadable($session);
            if ($session->revision !== $revision) {
                throw new RuntimeConflict('revision_conflict', $this->present($session));
            }
            $session->teacher_notes = $notes;
            $session->revision++;
            $session->save();

            return $this->present($session);
        });
    }

    public function favorite(string $owner, string $id, bool $favorite): array
    {
        return OwnerMutation::transaction([$owner], function () use ($owner, $id, $favorite): array {
            $material = LessonMaterial::query()->where('owner_key', $owner)->lockForUpdate()->find($id)
                ?? throw new ApiProblem('not_found', 404);
            if ((bool) $material->favorite !== $favorite) {
                $material->favorite = $favorite;
                $material->save();
            }

            return ['lessonId' => $id, 'favorite' => $favorite];
        });
    }

    public function versions(string $owner, string $id): array
    {
        $material = $this->material($owner, $id);

        return $material->versions()->where('purpose', 'authoring')->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn ($version) => [
            'id' => $version->id, 'status' => $version->status, 'purpose' => $version->purpose,
            'createdAt' => $version->created_at->utc()->toISOString(), 'current' => $material->current_version_id === $version->id,
        ])->all();
    }

    public function version(string $owner, string $id, string $versionId): array
    {
        $version = $this->material($owner, $id)->versions()->where('purpose', 'authoring')->find($versionId)
            ?? throw new ApiProblem('not_found', 404);

        return ['id' => $version->id, 'status' => $version->status, 'createdAt' => $version->created_at->utc()->toISOString(), 'document' => $version->document];
    }

    public function again(string $owner, string $id): array
    {
        $this->findOwned($owner, $id);

        return $this->runtime->again($owner, $id);
    }

    private function material(string $owner, string $id): LessonMaterial
    {
        return LessonMaterial::query()->where('owner_key', $owner)->find($id) ?? throw new ApiProblem('not_found', 404);
    }

    private function summary(TeachingSession $session, ?bool $account = null): array
    {
        return ['id' => $session->id, 'lessonId' => $session->version->lesson_material_id, 'lessonVersionId' => $session->lesson_version_id,
            'title' => $session->version->document['content'][$session->locale]['title'], 'locale' => $session->locale,
            'mode' => $session->mode, 'status' => $session->status, 'revision' => $session->revision,
            'createdAt' => $session->created_at->utc()->toISOString(), 'startedAt' => $session->started_at?->utc()->toISOString(),
            'finishedAt' => $session->finished_at?->utc()->toISOString(), 'visitedStageIds' => $session->visited_stage_ids,
            'detailsAvailable' => $this->policy->detailsAvailable($session),
            'detailsExpiresAt' => $this->policy->detailsExpiresAt($session)?->toISOString(), 'historyExpiresAt' => $this->policy->historyExpiresAt($session, $account)?->toISOString()];
    }
}
