<?php

namespace App\Application\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class GuestIdentity
{
    public function key(Request $request): string
    {
        $key = $request->session()->get('studio_owner_key');
        if (! is_string($key) || ! Str::isUuid($key)) {
            $key = (string) Str::uuid();
            $request->session()->put('studio_owner_key', $key);
        }

        return $key;
    }
}
