<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Catalog\CatalogService;
use App\Application\Catalog\NeighborInstaller;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Models\SessionAnswer;
use App\Models\TeachingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class NeighborLessonRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return [['ru'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_complete_real_topic_uses_common_runtime_with_private_notes_reveal_moderation_capacity_and_clean_restart(string $locale): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        $released = $entry->version->document;
        $owner = (string) Str::uuid();
        $runtime = app(RuntimeService::class);
        $result = app(CatalogService::class)->use('kto-moi-blizhnii', $locale, $owner, true);
        $session = $result['session'];
        $token = TeachingSession::findOrFail($session['id'])->projector_token;
        $participant = $runtime->join($session['joinCode'], 'Synthetic learner', [])['participant']['id'];
        $other = $runtime->join($session['joinCode'], 'Synthetic observer', [])['participant']['id'];
        $execute = function (string $action, array $payload = []) use ($runtime, $owner, &$session): void {
            $session = $runtime->command($owner, $session['id'], (string) Str::uuid(), $session['revision'], $action, $payload)['session'];
        };
        $execute('begin');
        $visited = [];
        foreach ($released['stages'] as $stageIndex => $stage) {
            if ($session['currentStageId'] !== $stage['id']) {
                $execute('stage', ['stageId' => $stage['id']]);
            }
            $visited[] = $stage['id'];
            $projector = $runtime->projector($token);
            $this->assertSame($stage['content'][$locale]['title'], $projector['stage']['content']['title']);
            $this->assertArrayNotHasKey('notes', $projector['stage']['content']);
            foreach ($stage['blocks'] as $blockIndex => $block) {
                $public = $projector['stage']['blocks'][$blockIndex];
                $this->assertArrayNotHasKey('teacherNotes', $public);
                $this->assertArrayNotHasKey('solution', $public);
                if ($block['type'] === 'core.image') {
                    $this->assertSame('/media/builtin/'.$block['media']['image']['versionId'], $public['resources']['image']);
                }
                if (! isset($public['runtime'])) {
                    continue;
                }
                if ($public['runtime']['status'] === 'prepared') {
                    $execute('block.open', ['blockId' => $block['id']]);
                }
                if ($block['type'] === 'core.roles') {
                    foreach ($block['content'][$locale]['roles'] as $role) {
                        $execute('role.reveal.next', ['blockId' => $block['id']]);
                    }
                }
                $value = match ($block['type']) {
                    'core.roles' => ['roleId' => 'samaritan'],
                    'core.signals' => ['ready' => true, 'question' => true],
                    'core.free-response' => ['text' => 'Private original '.$locale],
                    'core.poll' => ['optionId' => 'fear'],
                    default => $block['solution'],
                };
                $body = ['stageId' => $stage['id'], 'blockId' => $block['id'], 'value' => $value];
                $student = $runtime->answer($session['id'], $participant, $stage['id'], $block['id'], $body);
                $own = collect($student['ownAnswers'])->firstWhere('blockId', $block['id']);
                $this->assertNull($own['grade']);
                if ($block['type'] === 'core.roles') {
                    try {
                        $runtime->answer($session['id'], $other, $stage['id'], $block['id'], $body);
                        $this->fail('A one-place role must reject the second learner.');
                    } catch (ApiProblem $problem) {
                        $this->assertSame('role_full', $problem->problemCode);
                    }
                } elseif ($block['type'] === 'core.signals') {
                    $execute('signal.ack', ['blockId' => $block['id'], 'participantId' => $participant]);
                    $this->assertTrue($runtime->student($session['id'], $participant)['ownAnswers'][0]['acknowledged']);
                } elseif ($block['type'] === 'core.free-response') {
                    $answer = SessionAnswer::query()->where('teaching_session_id', $session['id'])->where('block_id', $block['id'])->firstOrFail();
                    $execute('answer.moderate', ['answerId' => $answer->id, 'expectedAnswerRevision' => $answer->revision, 'status' => 'approved', 'displayText' => 'Public '.$locale]);
                    $this->assertArrayNotHasKey('results', $runtime->projector($token)['stage']['blocks'][$blockIndex]['runtime']);
                    $execute('answer.publish', ['answerId' => $answer->id, 'expectedAnswerRevision' => $answer->fresh()->revision]);
                    $published = $runtime->projector($token)['stage']['blocks'][$blockIndex]['runtime']['results']['published'];
                    $this->assertSame([['text' => 'Public '.$locale]], $published);
                    $this->assertSame(['text' => 'Private original '.$locale], $answer->fresh()->value);
                    $this->assertStringNotContainsString('Private original', json_encode($runtime->projector($token), JSON_THROW_ON_ERROR));
                    $this->assertStringNotContainsString('Synthetic learner', json_encode($published, JSON_THROW_ON_ERROR));
                } else {
                    $this->assertArrayNotHasKey('results', $runtime->projector($token)['stage']['blocks'][$blockIndex]['runtime']);
                    $execute('block.close', ['blockId' => $block['id']]);
                    $execute('block.reveal', ['blockId' => $block['id']]);
                    $results = $runtime->projector($token)['stage']['blocks'][$blockIndex]['runtime']['results'];
                    if ($block['type'] === 'core.poll') {
                        $this->assertSame(1, $results['totalAnswers']);
                    } else {
                        $this->assertSame($block['solution'], $results);
                        $this->assertTrue(collect($runtime->student($session['id'], $participant)['ownAnswers'])->firstWhere('blockId', $block['id'])['grade']);
                    }
                }
            }
            if (in_array($stageIndex, [8, 9, 10], true)) {
                $execute('timer.start', ['seconds' => 60]);
                $this->assertSame('running', $session['timer']['status']);
                $execute('timer.clear');
            }
        }
        $execute('finish');
        $this->assertSame('finished', $session['status']);
        $this->assertSame($visited, TeachingSession::findOrFail($session['id'])->visited_stage_ids);
        $this->assertSame($released, $entry->version->fresh()->document);
        $new = app(CatalogService::class)->use('kto-moi-blizhnii', $locale, $owner, true)['session'];
        $this->assertNotSame($session['id'], $new['id']);
        $this->assertSame([], $new['participants']);
        $this->assertSame([], $new['answers']);
        $this->assertSame([], TeachingSession::findOrFail($new['id'])->visited_stage_ids);
    }
}
