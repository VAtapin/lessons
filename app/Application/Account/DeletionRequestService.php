<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Shared\ApiProblem;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class DeletionRequestService
{
    public function current(User $user): ?array
    {
        $request = AccountDeletionRequest::query()->where('user_id', $user->id)->first();

        return $request === null ? null : $this->present($request);
    }

    /** A reversible request only; this operation never deletes or disables account data. */
    public function change(User $authenticated, array $input, bool $cancel): array
    {
        $keys = array_keys($input);
        sort($keys);
        if ($keys !== ['currentPassword', 'expectedRevision'] || ! is_string($input['currentPassword'])
            || ! is_int($input['expectedRevision']) || $input['expectedRevision'] < ($cancel ? 1 : 0)) {
            throw new ApiProblem('invalid_input', 422);
        }
        $password = $input['currentPassword'];
        if ($password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new ApiProblem('invalid_credentials', 422);
        }

        return DB::transaction(function () use ($authenticated, $password, $input, $cancel): array {
            // Lock the account first: two browser sessions cannot create two requests.
            $user = User::query()->whereKey($authenticated->id)->lockForUpdate()->first();
            if ($user === null || ! hash_equals($authenticated->password, $user->password)) {
                throw new ApiProblem('identity_changed', 409);
            }
            if (! Hash::check($password, $user->password)) {
                throw new ApiProblem('invalid_credentials', 422);
            }
            $request = AccountDeletionRequest::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ((! $cancel && $request?->status === 'pending') || ($cancel && $request?->status === 'cancelled')) {
                return ['request' => $this->present($request), 'created' => false];
            }
            if ($request === null && $cancel) {
                throw new ApiProblem('not_found', 404);
            }
            if (($request?->revision ?? 0) !== $input['expectedRevision']) {
                throw new ApiProblem('deletion_request_conflict', 409);
            }
            $created = $request === null;
            $request ??= new AccountDeletionRequest(['user_id' => $user->id, 'revision' => 0]);
            $request->revision++;
            $request->status = $cancel ? 'cancelled' : 'pending';
            if (! $cancel) {
                $request->requested_at = Carbon::now('UTC');
            }
            $request->cancelled_at = $cancel ? Carbon::now('UTC') : null;
            $request->save();

            return ['request' => $this->present($request), 'created' => $created];
        });
    }

    public function present(AccountDeletionRequest $request): array
    {
        return ['id' => $request->id, 'status' => $request->status, 'revision' => $request->revision,
            'requestedAt' => $request->requested_at->utc()->toISOString(), 'cancelledAt' => $request->cancelled_at?->utc()->toISOString()];
    }
}
