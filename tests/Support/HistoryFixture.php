<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Str;

trait HistoryFixture
{
    private string $historyOwner;

    private function historyIdentity(bool $account = false): void
    {
        $this->historyOwner = (string) Str::uuid();
        if ($account) {
            $user = User::factory()->create(['owner_key' => $this->historyOwner]);
            $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        } else {
            $this->withSession(['studio_owner_key' => $this->historyOwner]);
        }
    }

    private function historyLesson(): array
    {
        return $this->postJson('/api/studio/lessons', ['document' => $this->historyDocument()])->assertCreated()->json('lesson');
    }

    private function historySession(bool $prepare = false): array
    {
        $lesson = $this->historyLesson();

        return $this->postJson('/api/studio/lessons/'.$lesson['id'].'/sessions', ['expectedRevision' => $lesson['revision'], 'prepare' => $prepare])->assertCreated()->json('session');
    }

    private function historyCommand(array &$session, string $action, array $payload = [], ?string $uuid = null): array
    {
        $body = ['commandId' => $uuid ?? (string) Str::uuid(), 'expectedRevision' => $session['revision'], 'action' => $action, 'payload' => $payload];
        $session = $this->postJson('/api/studio/sessions/'.$session['id'].'/commands', $body)->assertOk()->json('session');

        return $body;
    }

    private function historyParticipant(array $session): array
    {
        return $this->postJson('/api/join', ['code' => $session['joinCode'], 'name' => 'Private pupil name'])->assertOk()->json('participant');
    }

    private function historyDocument(): array
    {
        $block = fn (string $id, string $type, array $content, array $config = [], ?array $solution = null): array => [
            'id' => $id, 'type' => 'core.'.$type, 'schemaVersion' => 1, 'content' => ['ru' => $content], 'config' => $config,
            'solution' => $solution, 'teacherNotes' => ['ru' => 'Authored private note'],
        ];

        return ['id' => 'history-fixture', 'schemaVersion' => 1, 'defaultLocale' => 'ru', 'locales' => ['ru'],
            'content' => ['ru' => ['title' => 'History fixture']], 'stages' => [
                ['id' => 'first', 'content' => ['ru' => ['title' => 'First']], 'blocks' => [
                    $block('choice', 'single-choice', ['question' => 'Choose', 'options' => [['optionId' => 'a', 'text' => 'A'], ['optionId' => 'b', 'text' => 'B']]], [], ['optionId' => 'a']),
                    $block('free', 'free-response', ['question' => 'Write'], ['maxLength' => 100, 'allowRepeat' => true]),
                    $block('roles', 'roles', ['text' => 'Role', 'roles' => [['roleId' => 'a', 'text' => 'A']]], ['capacities' => ['a' => 2]]),
                    $block('signals', 'signals', ['text' => 'Ready?']),
                ]],
                ['id' => 'second', 'content' => ['ru' => ['title' => 'Second']], 'blocks' => [$block('text', 'text', ['text' => 'Future'])]],
            ]];
    }
}
