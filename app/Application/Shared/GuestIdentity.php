<?php

namespace App\Application\Shared;

use App\Application\Account\AccountIdentity;
use App\Application\Account\AuthService;
use App\Models\GuestWorkspaceClaim;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class GuestIdentity
{
    public function key(Request $request): string
    {
        if ($request->user() !== null) {
            return AccountIdentity::owner($request->user());
        }
        $key = $request->session()->get('studio_owner_key');
        if (! is_string($key) || ! Str::isUuid($key)) {
            // Logout preserves a server-proven unclaimed guest workspace. Reuse
            // that guest credential rather than creating an inaccessible second workspace.
            $pending = $request->session()->get(AuthService::GUEST_PROOF);
            if (is_string($pending) && Str::isUuid($pending)
                && ! GuestWorkspaceClaim::query()->whereKey(strtolower($pending))->exists()
                && ! User::query()->where('owner_key', strtolower($pending))->exists()) {
                $key = strtolower($pending);
                $request->session()->put('studio_owner_key', $key);
            }
        }
        if (! is_string($key) || ! Str::isUuid($key)
            || GuestWorkspaceClaim::query()->whereKey(strtolower($key))->exists()
            || User::query()->where('owner_key', strtolower($key))->exists()) {
            $key = (string) Str::uuid();
            $request->session()->put('studio_owner_key', $key);
        }

        return strtolower($key);
    }
}
