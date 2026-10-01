<?php

declare(strict_types=1);

namespace App\Application\History;

use App\Application\Shared\ApiProblem;
use App\Application\Shared\OwnerMutation;
use App\Application\Studio\SaveReceiptRetention;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\SessionAnswer;
use App\Models\SessionCommandReceipt;
use App\Models\SessionParticipant;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Throwable;

final readonly class RetentionService
{
    public function __construct(private RetentionPolicy $policy, private SessionAggregates $aggregates, private SaveReceiptRetention $saveReceipts) {}

    public function run(bool $dryRun, int $batch = 100): array
    {
        $counts = ['sessionsDeleted' => 0, 'detailsPurged' => 0, 'rehearsalVersionsDeleted' => 0, 'receiptsDeleted' => 0];
        try {
            foreach ($this->candidates()->select('teaching_sessions.id')->orderBy('teaching_sessions.id')->limit($batch)->get() as $candidate) {
                try {
                    $result = $dryRun ? $this->inspect($candidate->id, $batch) : OwnerMutation::forSession($candidate->id,
                        fn (TeachingSession $session): array => $this->apply($session, $batch));
                } catch (ApiProblem $problem) {
                    if ($problem->problemCode === 'not_found') {
                        continue;
                    }
                    throw $problem;
                }
                if (array_sum($result) > 0) {
                    foreach ($counts as $key => $count) {
                        $counts[$key] += $result[$key];
                    }
                }
            }

            $counts['saveReceiptsDeleted'] = $this->saveReceipts->run($dryRun, $batch);

            return $counts;
        } catch (Throwable) {
            throw new ApiProblem('retention_failed', 503);
        }
    }

    private function candidates()
    {
        $now = CarbonImmutable::now('UTC');
        $calendar = $now->subYearsNoOverflow(2);
        $account = fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('users.owner_key', 'teaching_sessions.owner_key');

        return TeachingSession::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('status', 'finished')->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('mode', 'rehearsal')->where('created_at', '<=', $now->subDays(7)->format('Y-m-d H:i:s.u')))
                ->orWhere(fn ($q) => $q->where('mode', 'lesson')->whereNotNull('finished_at')->where(fn ($q) => $q
                    ->where(fn ($q) => $q->whereNotExists($account)->where('finished_at', '<=', $now->subDays(30)->format('Y-m-d H:i:s.u')))
                    ->orWhere(fn ($q) => $q->whereExists($account)->where(function ($q) use ($now, $calendar): void {
                        $q->where('finished_at', '<=', $calendar->format('Y-m-d H:i:s.u'));
                        // Inverse of addYearsNoOverflow: leap-day anchors also expire
                        // on Feb 28 at their original UTC time in a non-leap year.
                        if ($now->month === 2 && $now->day === 28 && ! $now->isLeapYear() && $calendar->isLeapYear()) {
                            $q->orWhereBetween('finished_at', [$calendar->addDay()->startOfDay()->format('Y-m-d H:i:s.u'), $calendar->addDay()->format('Y-m-d H:i:s.u')]);
                        }
                    }))
                    ->orWhere(fn ($q) => $q->whereNull('details_purged_at')->where('finished_at', '<=', $now->subDays(30)->format('Y-m-d H:i:s.u')))))))
            ->orWhereHas('commandReceipts', fn ($q) => $q->where('created_at', '<=', $now->subDays(30)->format('Y-m-d H:i:s.u'))));
    }

    private function inspect(string $id, int $batch): array
    {
        $session = TeachingSession::query()->find($id) ?? throw new ApiProblem('not_found', 404);

        return $this->plan($session, $batch);
    }

    private function plan(TeachingSession $session, int $batch): array
    {
        $delete = $this->policy->expired($this->policy->historyExpiresAt($session));
        $purge = ! $delete && $session->details_purged_at === null && $this->policy->expired($this->policy->detailsExpiresAt($session));
        $temporary = $delete && $session->version->purpose === 'rehearsal'
            && ! TeachingSession::query()->where('lesson_version_id', $session->lesson_version_id)->where('id', '!=', $session->id)->exists()
            && ! LessonMaterial::query()->where('current_version_id', $session->lesson_version_id)->exists();
        $receipts = $delete ? SessionCommandReceipt::query()->where('teaching_session_id', $session->id)->count()
            : count($this->oldReceipts($session, $batch));

        return ['sessionsDeleted' => (int) $delete, 'detailsPurged' => (int) $purge,
            'rehearsalVersionsDeleted' => (int) $temporary, 'receiptsDeleted' => $receipts];
    }

    private function apply(TeachingSession $session, int $batch): array
    {
        $plan = $this->plan($session, $batch);
        if ($plan['sessionsDeleted']) {
            $versionId = $session->lesson_version_id;
            $session->delete();
            if ($plan['rehearsalVersionsDeleted'] && ! TeachingSession::query()->where('lesson_version_id', $versionId)->exists()
                && ! LessonMaterial::query()->where('current_version_id', $versionId)->exists()) {
                LessonVersion::query()->whereKey($versionId)->where('purpose', 'rehearsal')->delete();
            }
        } elseif ($plan['detailsPurged']) {
            $session->final_aggregates ??= $this->aggregates->snapshot($session);
            SessionAnswer::query()->where('teaching_session_id', $session->id)->delete();
            SessionParticipant::query()->where('teaching_session_id', $session->id)->delete();
            $session->details_purged_at = CarbonImmutable::now('UTC');
            $session->public_access_closed_at = CarbonImmutable::now('UTC');
            $session->save();
        }
        if (! $plan['sessionsDeleted']) {
            SessionCommandReceipt::query()->whereIn('id', $this->oldReceipts($session, $batch))->delete();
        }

        return $plan;
    }

    private function oldReceipts(TeachingSession $session, int $batch): array
    {
        return SessionCommandReceipt::query()->where('teaching_session_id', $session->id)
            ->where('created_at', '<=', CarbonImmutable::now('UTC')->subDays(30)->format('Y-m-d H:i:s.u'))->orderBy('id')->limit($batch)->pluck('id')->all();
    }
}
