<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Account\DeletionRequestService;
use App\Application\Shared\ApiProblem;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccountDeletionRequestTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        $user = User::factory()->unverified()->create(['password' => 'existing password 123']);
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);

        return $user;
    }

    private function body(int $revision): array
    {
        return ['currentPassword' => 'existing password 123', 'expectedRevision' => $revision];
    }

    public function test_request_is_durable_private_and_does_not_delete_or_disable_account_data(): void
    {
        $user = $this->account();
        $this->getJson('/api/account/deletion-request')->assertOk()->assertExactJson(['request' => null]);
        $lesson = $this->postJson('/api/studio/lessons', ['document' => [
            'id' => 'fixture', 'schemaVersion' => 1, 'locales' => ['ru'], 'defaultLocale' => 'ru',
            'content' => ['ru' => ['title' => 'Private material']], 'stages' => [['id' => 'stage',
                'content' => ['ru' => ['title' => 'Stage']], 'blocks' => [['id' => 'text', 'type' => 'core.text',
                    'schemaVersion' => 1, 'content' => ['ru' => ['text' => 'Account content']]]]]],
        ]])->assertCreated()->json('lesson');
        $response = $this->postJson('/api/account/deletion-request', $this->body(0))->assertCreated()
            ->assertJsonPath('request.status', 'pending')->assertJsonPath('request.revision', 1);
        $this->assertDatabaseCount('account_deletion_requests', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('lesson_materials', 1);
        $this->assertDatabaseCount('lesson_versions', 1);
        $this->assertSame($user->password, $user->fresh()->password);
        $this->getJson('/api/studio/lessons/'.$lesson['id'])->assertOk();
        $this->getJson('/api/account/deletion-request')->assertExactJson($response->json());
        $this->assertStringNotContainsString($user->password, $response->getContent());
        $this->assertStringNotContainsString($user->fresh()->owner_key, $response->getContent());
        $this->assertStringNotContainsString('user_id', $response->getContent());
    }

    public function test_retries_cancellation_reopening_and_stale_revision_do_not_duplicate_requests(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->account();
        $first = $this->postJson('/api/account/deletion-request', $this->body(0))->assertCreated()->json();
        $this->travel(2)->minutes();
        $this->postJson('/api/account/deletion-request', $this->body(0))->assertOk()->assertExactJson($first);
        $this->postJson('/api/account/deletion-request/cancel', $this->body(999))->assertConflict();
        $cancelled = $this->postJson('/api/account/deletion-request/cancel', $this->body(1))->assertOk()
            ->assertJsonPath('request.status', 'cancelled')->assertJsonPath('request.revision', 2)->json();
        $this->postJson('/api/account/deletion-request/cancel', $this->body(1))->assertOk()->assertExactJson($cancelled);
        $this->postJson('/api/account/deletion-request', $this->body(1))->assertConflict();
        $this->postJson('/api/account/deletion-request', $this->body(2))->assertOk()->assertJsonPath('request.revision', 3)
            ->assertJsonPath('request.id', $first['request']['id'])->assertJsonPath('request.cancelledAt', null);
        $this->postJson('/api/account/deletion-request/cancel', $this->body(1))->assertConflict();
        $this->assertDatabaseCount('account_deletion_requests', 1);
        $this->assertNotSame($first['request']['requestedAt'], AccountDeletionRequest::first()->requested_at->utc()->toISOString());
        $this->travelBack();
    }

    public function test_guest_and_other_account_cannot_access_a_request(): void
    {
        $owner = $this->account();
        $this->postJson('/api/account/deletion-request', $this->body(0))->assertCreated();
        $other = User::factory()->create(['password' => 'other password 456']);
        $this->actingAs($other)->withSession(['auth_password_hash' => $other->password]);
        $this->getJson('/api/account/deletion-request')->assertExactJson(['request' => null]);
        $this->postJson('/api/account/deletion-request/cancel', ['currentPassword' => 'other password 456', 'expectedRevision' => 1])->assertNotFound();
        $this->assertSame('pending', AccountDeletionRequest::where('user_id', $owner->id)->first()->status);
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->getJson('/api/account/deletion-request')->assertUnauthorized();
        $this->postJson('/api/account/deletion-request', $this->body(0))->assertUnauthorized();
    }

    public function test_wrong_password_and_invalid_bodies_cannot_write_and_do_not_revoke_valid_login(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->account();
        foreach (['wrong', '', str_repeat('ü', 37), "existing password 123\0"] as $password) {
            $this->postJson('/api/account/deletion-request', ['currentPassword' => $password, 'expectedRevision' => 0])
                ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_credentials');
        }
        foreach ([$this->body(0) + ['userId' => 2], ['currentPassword' => [], 'expectedRevision' => 0],
            ['currentPassword' => 'existing password 123', 'expectedRevision' => '0'], $this->body(-1)] as $body) {
            $this->postJson('/api/account/deletion-request', $body)->assertUnprocessable();
        }
        $this->getJson('/api/account')->assertOk();
        $this->assertDatabaseCount('account_deletion_requests', 0);
    }

    public function test_password_confirmation_is_required_even_for_idempotent_retries(): void
    {
        $this->account();
        $this->postJson('/api/account/deletion-request', $this->body(0))->assertCreated();
        $this->postJson('/api/account/deletion-request', ['currentPassword' => 'wrong', 'expectedRevision' => 0])->assertUnprocessable();
        $this->postJson('/api/account/deletion-request/cancel', ['currentPassword' => 'wrong', 'expectedRevision' => 1])->assertUnprocessable();
        $this->assertSame('pending', AccountDeletionRequest::first()->status);
    }

    public function test_stale_authenticated_user_cannot_write_after_password_change(): void
    {
        $user = $this->account();
        User::whereKey($user->id)->update(['password' => Hash::make('replacement password 456')]);
        try {
            app(DeletionRequestService::class)->change($user, $this->body(0), false);
            $this->fail('Stale authentication was accepted.');
        } catch (ApiProblem $problem) {
            $this->assertSame('identity_changed', $problem->problemCode);
        }
        $this->assertDatabaseCount('account_deletion_requests', 0);
    }
}
