<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Shared\ApiProblem;
use App\Models\GuestWorkspaceClaim;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

final class AuthService
{
    public const GUEST_PROOF = 'pending_guest_owner_key';

    public function hasPendingGuest(Request $request): bool
    {
        $key = $request->session()->get(self::GUEST_PROOF);

        return is_string($key) && Str::isUuid($key)
            && ! GuestWorkspaceClaim::query()->whereKey(strtolower($key))->exists()
            && ! User::query()->where('owner_key', strtolower($key))->exists();
    }

    public function register(Request $request): array
    {
        $input = AuthInput::parse($request->all(), ['name', 'email', 'password', 'passwordConfirmation', 'uiLocale']);
        $guest = $this->guestProof($request);
        try {
            $user = DB::transaction(function () use ($input): User {
                $user = new User(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
                $user->forceFill(['owner_key' => (string) Str::uuid(), 'ui_locale' => $input['uiLocale']])->save();

                return $user;
            });
        } catch (QueryException $failure) {
            throw new ApiProblem(in_array($failure->getCode(), ['23000', '23505', '19'], true)
                ? 'registration_unavailable' : 'account_unavailable', in_array($failure->getCode(), ['23000', '23505', '19'], true) ? 422 : 503);
        }
        $this->loginSession($request, $user, $guest);
        $this->notify(fn () => $user->sendEmailVerificationNotification(), 'verification', true);

        return ['user' => AccountIdentity::present($user), 'verificationRequired' => true];
    }

    public function login(Request $request): array
    {
        $input = AuthInput::parse($request->all(), ['email', 'password']);
        $guest = $this->guestProof($request);
        if (strlen($input['password']) > 72 || str_contains($input['password'], "\0")
            || ! Auth::guard('web')->attempt($input)) {
            throw new ApiProblem('invalid_credentials', 401);
        }
        $user = Auth::guard('web')->user();
        AccountIdentity::owner($user);
        $this->loginSession($request, $user, $guest);

        return ['user' => AccountIdentity::present($user)];
    }

    public function logout(Request $request): void
    {
        AuthInput::parse($request->all(), []);
        $participants = $request->session()->get('lesson_participants', []);
        $guest = $this->guestProof($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('lesson_participants', $participants);
        if ($guest !== null) {
            $request->session()->put(self::GUEST_PROOF, $guest);
        }
    }

    public function continueGuest(Request $request): void
    {
        if (! $this->hasPendingGuest($request)) {
            throw new ApiProblem('claim_unavailable', 409);
        }
        $guest = $this->guestProof($request);
        if ($guest === null) {
            throw new ApiProblem('claim_unavailable', 409);
        }
        $this->logout($request);
        $request->session()->put('studio_owner_key', $guest);
        $request->session()->forget(self::GUEST_PROOF);
    }

    public function verification(Request $request): array
    {
        AuthInput::parse($request->all(), []);
        if (! $request->user()->hasVerifiedEmail()) {
            $this->notify(fn () => $request->user()->sendEmailVerificationNotification(), 'verification', true);
        }

        return ['accepted' => true];
    }

    public function forgot(Request $request): array
    {
        $input = AuthInput::parse($request->all(), ['email']);
        $this->notify(fn () => Password::broker()->sendResetLink($input), 'reset', false);

        return ['accepted' => true];
    }

    public function reset(Request $request): array
    {
        $input = AuthInput::parse($request->all(), ['email', 'token', 'password', 'passwordConfirmation']);
        $status = DB::transaction(function () use ($input): string {
            User::query()->where('email', $input['email'])->lockForUpdate()->first();

            return Password::broker()->reset([
                'email' => $input['email'], 'token' => $input['token'], 'password' => $input['password'],
                'password_confirmation' => $input['passwordConfirmation'],
            ], function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $locked->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                });
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiProblem('invalid_reset', 422);
        }

        return ['reset' => true];
    }

    public function update(Request $request): array
    {
        $input = AuthInput::parse($request->all(), ['name', 'uiLocale']);
        $request->user()->forceFill(['name' => $input['name'], 'ui_locale' => $input['uiLocale']])->save();

        return ['user' => AccountIdentity::present($request->user())];
    }

    private function loginSession(Request $request, User $user, ?string $guest): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate(true);
        $request->session()->forget('studio_owner_key');
        $request->session()->put('auth_password_hash', $user->password);
        if ($guest !== null) {
            $request->session()->put(self::GUEST_PROOF, $guest);
        } else {
            $request->session()->forget(self::GUEST_PROOF);
        }
    }

    private function guestProof(Request $request): ?string
    {
        $fields = $request->user() === null ? ['studio_owner_key', self::GUEST_PROOF] : [self::GUEST_PROOF];
        $keys = [];
        foreach ($fields as $field) {
            $key = $request->session()->get($field);
            if (is_string($key) && Str::isUuid($key)
                && ! GuestWorkspaceClaim::query()->whereKey(strtolower($key))->exists()
                && ! User::query()->where('owner_key', strtolower($key))->exists()) {
                $keys[] = strtolower($key);
            }
        }
        $keys = array_values(array_unique($keys));
        if (count($keys) > 1) {
            throw new ApiProblem('guest_context_conflict', 409);
        }

        return $keys[0] ?? null;
    }

    private function notify(callable $notification, string $kind, bool $reportFailure): void
    {
        try {
            if (app()->environment('production') && config('mail.mailers.'.config('mail.default').'.transport') !== 'sendmail') {
                throw new \RuntimeException('Production account mail requires the confirmed native transport.');
            }
            $notification();
        } catch (Throwable) {
            Log::warning('Account notification unavailable.', ['kind' => $kind]);
            if ($reportFailure) {
                throw new ApiProblem('mail_unavailable', 503);
            }
        }
    }
}
