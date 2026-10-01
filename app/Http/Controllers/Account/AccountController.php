<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Application\Account\AccountIdentity;
use App\Application\Account\AuthService;
use App\Application\Account\GuestClaimService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\GuestIdentity;
use App\Application\Shared\OwnerQuota;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

final readonly class AccountController
{
    public function __construct(private AuthService $auth, private GuestClaimService $claims, private GuestIdentity $identity) {}

    public function show(Request $request): mixed
    {
        $key = $this->identity->key($request);

        return response()->json(['user' => $request->user() ? AccountIdentity::present($request->user()) : null,
            'guestClaimAvailable' => $request->user() ? $this->claims->preview($request)['claim']['available'] : $this->auth->hasPendingGuest($request),
            'quota' => OwnerQuota::present($key)]);
    }

    public function register(Request $request): mixed
    {
        return response()->json($this->auth->register($request), 201);
    }

    public function login(Request $request): mixed
    {
        return response()->json($this->auth->login($request));
    }

    public function logout(Request $request): mixed
    {
        $this->auth->logout($request);

        return response()->noContent();
    }

    public function continueGuest(Request $request): mixed
    {
        $this->auth->continueGuest($request);

        return response()->noContent();
    }

    public function verificationNotification(Request $request): mixed
    {
        return response()->json($this->auth->verification($request), 202);
    }

    public function verify(Request $request, string $id, string $hash): mixed
    {
        $user = $request->user();
        if ((string) $user->id !== $id || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new ApiProblem('verification_invalid', 403);
        }
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect('/'.AccountIdentity::present($user)['uiLocale'].'/account');
    }

    public function forgot(Request $request): mixed
    {
        return response()->json($this->auth->forgot($request), 202);
    }

    public function reset(Request $request): mixed
    {
        return response()->json($this->auth->reset($request));
    }

    public function update(Request $request): mixed
    {
        return response()->json($this->auth->update($request));
    }

    public function claimPreview(Request $request): mixed
    {
        return response()->json($this->claims->preview($request));
    }

    public function claim(Request $request): mixed
    {
        return response()->json($this->claims->claim($request));
    }
}
