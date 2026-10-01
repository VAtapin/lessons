<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AccountIdentity
{
    public static function owner(User $user): string
    {
        if ($user->owner_key !== null) {
            return $user->owner_key;
        }

        $key = DB::transaction(function () use ($user): string {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->owner_key === null) {
                $locked->forceFill(['owner_key' => (string) Str::uuid()])->save();
            }

            return $locked->owner_key;
        });
        $user->owner_key = $key;

        return $key;
    }

    public static function present(User $user): array
    {
        $locales = config('lessons.ui_locales');
        $locale = $user->ui_locale ?? config('app.locale');

        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'verified' => $user->hasVerifiedEmail(), 'uiLocale' => in_array($locale, $locales, true) ? $locale : $locales[0]];
    }
}
