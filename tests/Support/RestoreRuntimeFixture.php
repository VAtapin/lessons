<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\History\HistoryService;
use App\Application\History\RetentionService;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Models\LessonMaterial;
use App\Models\LessonVersion;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Synthetic runtime fixture and actual access/expiry checks, reused after the real isolated import. */
trait RestoreRuntimeFixture
{
    private array $restoreSessionIds = [];

    private function restoreRuntimeDocument(): array
    {
        $document = $this->editorDocument();
        $document['stages'][0]['blocks'][] = ['id' => 'restore-free', 'type' => 'core.free-response', 'schemaVersion' => 1,
            'content' => ['ru' => ['question' => 'Synthetic private answer'], 'de' => ['question' => 'Synthetische private Antwort']],
            'config' => ['maxLength' => 100, 'allowRepeat' => true], 'teacherNotes' => ['ru' => 'Private restore note', 'de' => 'Private restore note']];

        return $document;
    }

    private function createRestoreRuntimeFixture(string $owner, LessonMaterial $material): array
    {
        $runtime = app(RuntimeService::class);
        $active = $runtime->start($owner, $material->id, $material->revision, 'ru');
        $this->restoreSessionIds[] = $active['id'];
        $participant = $runtime->join($active['joinCode'], 'Synthetic restore participant', [])['participant'];
        $runtime->answer($active['id'], $participant['id'], 'stage-a', 'choice-a', ['stageId' => 'stage-a', 'blockId' => 'choice-a', 'optionId' => 'a']);
        $active = $runtime->command($owner, $active['id'], (string) Str::uuid(), $active['revision'], 'block.open', ['blockId' => 'restore-free'])['session'];
        $runtime->answer($active['id'], $participant['id'], 'stage-a', 'restore-free', ['stageId' => 'stage-a', 'blockId' => 'restore-free', 'value' => ['text' => 'Synthetic original <private> Unicode ü']]);
        $activeAnswer = SessionAnswer::query()->where('teaching_session_id', $active['id'])->where('block_id', 'restore-free')->firstOrFail();
        $version = TeachingSession::findOrFail($active['id'])->version;
        $expired = $runtime->startSnapshot($owner, $version, 'ru', 'lesson');
        $this->restoreSessionIds[] = $expired['id'];
        $expiredParticipant = $runtime->join($expired['joinCode'], 'Synthetic expired participant', [])['participant'];
        $expired = $runtime->command($owner, $expired['id'], (string) Str::uuid(), $expired['revision'], 'block.open', ['blockId' => 'restore-free'])['session'];
        $runtime->answer($expired['id'], $expiredParticipant['id'], 'stage-a', 'restore-free', ['stageId' => 'stage-a', 'blockId' => 'restore-free', 'value' => ['text' => 'Synthetic expired private original']]);
        $expired = $runtime->command($owner, $expired['id'], (string) Str::uuid(), $expired['revision'], 'finish', [])['session'];
        DB::table('teaching_sessions')->where('id', $expired['id'])->update(['finished_at' => CarbonImmutable::now('UTC')->subDays(31)->format('Y-m-d H:i:s.u')]);

        return ['active' => $active, 'participantId' => $participant['id'], 'answerId' => $activeAnswer->id,
            'expired' => $expired, 'expiredParticipantId' => $expiredParticipant['id'], 'versionId' => $version->id];
    }

    private function assertRestoredRuntimeFixture(string $owner, array $fixture): void
    {
        $runtime = app(RuntimeService::class);
        $active = $fixture['active'];
        $activeToken = TeachingSession::findOrFail($active['id'])->projector_token;
        $expired = $fixture['expired'];
        $expiredToken = TeachingSession::findOrFail($expired['id'])->projector_token;
        $teacher = $runtime->teacher($owner, $active['id']);
        $this->assertSame('running', $teacher['status']);
        $this->assertSame($active['revision'], $teacher['revision']);
        $this->assertSame($fixture['versionId'], $teacher['document']['id']);
        $this->assertSame('released', LessonVersion::findOrFail($fixture['versionId'])->status);
        $this->assertStringContainsString('Synthetic original <private> Unicode ü', json_encode($teacher, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $student = $runtime->student($active['id'], $fixture['participantId']);
        $answers = array_column($student['ownAnswers'], null, 'blockId');
        $this->assertSame(['optionId' => 'a'], $answers['choice-a']['value']);
        $this->assertSame($fixture['answerId'], $answers['restore-free']['id']);
        $this->assertSame(['text' => 'Synthetic original <private> Unicode ü'], $answers['restore-free']['value']);
        $projection = $runtime->projector($activeToken);
        foreach ([$student, $projection] as $public) {
            $json = json_encode($public, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach (['Private restore note', 'Private block note', 'Private stage note', 'solution', 'projectorUrl', 'joinCode', 'owner_key', 'participants'] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
        $this->assertStringNotContainsString('Synthetic original <private>', json_encode($projection));
        $this->assertNotFoundAfterRestore(fn () => $runtime->teacher((string) Str::uuid(), $active['id']));
        $this->assertNotFoundAfterRestore(fn () => $runtime->student($active['id'], (string) Str::uuid()));
        $this->assertNotFoundAfterRestore(fn () => $runtime->projector(str_repeat('0', 64)));
        $this->assertNotFoundAfterRestore(fn () => $runtime->teacher($owner, $expired['id']));
        $this->assertNotFoundAfterRestore(fn () => app(HistoryService::class)->detail($owner, $expired['id']));
        $this->assertNotFoundAfterRestore(fn () => $runtime->student($expired['id'], $fixture['expiredParticipantId']));
        $this->assertNotFoundAfterRestore(fn () => $runtime->projector($expiredToken));
        $this->assertNotFoundAfterRestore(fn () => $runtime->join($expired['joinCode'], 'Synthetic retry', []));
        $this->assertSame(1, TeachingSession::whereKey($expired['id'])->count(), 'Expired raw data still exists before cleanup but must not become readable after restore.');
        $this->assertSame(1, SessionAnswer::where('teaching_session_id', $expired['id'])->count());
        $counts = app(RetentionService::class)->run(false, 100);
        $this->assertSame(1, $counts['sessionsDeleted']);
        $this->assertSame(0, TeachingSession::whereKey($expired['id'])->count());
        $this->assertSame(0, SessionAnswer::where('teaching_session_id', $expired['id'])->count());
        $this->assertSame(1, TeachingSession::whereKey($active['id'])->count());
        $this->assertSame(2, SessionAnswer::where('teaching_session_id', $active['id'])->count());
        $this->assertSame('released', LessonVersion::findOrFail($fixture['versionId'])->status);
    }

    private function assertNotFoundAfterRestore(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Restored access or expiry boundary was bypassed.');
        } catch (ApiProblem $problem) {
            $this->assertSame('not_found', $problem->problemCode);
            $this->assertSame(404, $problem->status);
        }
    }
}
