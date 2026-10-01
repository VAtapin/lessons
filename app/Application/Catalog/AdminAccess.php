<?php

declare(strict_types=1);

namespace App\Application\Catalog;

use App\Application\Shared\ApiProblem;
use App\Models\User;

final class AdminAccess
{
    public function account(?User $user): User
    {
        if ($user === null) {
            throw new ApiProblem('authentication_required', 401);
        }
        $fresh = User::query()->find($user->id);
        if ($fresh === null || ! $fresh->hasVerifiedEmail()) {
            throw new ApiProblem('verification_required', 403);
        }

        return $fresh;
    }

    public function require(?User $user): User
    {
        $user = $this->account($user);
        if (! (bool) $user->getAttribute('is_admin')) {
            throw new ApiProblem('admin_required', 403);
        }

        return $user;
    }
}
