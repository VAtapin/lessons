<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Account\AuthService;
use App\Application\Shared\ApiProblem;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AccountSession
{
    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $fresh = User::query()->find($user->id);
            $baseline = $request->session()->get('auth_password_hash');
            if ($fresh === null || ! is_string($baseline) || ! hash_equals($fresh->password, $baseline)) {
                $guest = $this->auth->hasPendingGuest($request)
                    ? strtolower($request->session()->get(AuthService::GUEST_PROOF)) : null;
                Auth::guard('web')->logout();
                $participants = $request->session()->get('lesson_participants', []);
                $request->session()->invalidate();
                $request->session()->put('lesson_participants', $participants);
                if ($guest !== null) {
                    $request->session()->put(AuthService::GUEST_PROOF, $guest);
                }
                $request->session()->regenerateToken();
                throw new ApiProblem('authentication_required', 401);
            }
            Auth::guard('web')->setUser($fresh);
        }

        return $next($request);
    }
}
