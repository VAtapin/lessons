<?php

namespace App\Http\Middleware;

use App\Application\Shared\ApiProblem;
use Closure;
use Illuminate\Http\Request;

final class RequireAccount
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->user() === null) {
            throw new ApiProblem('authentication_required', 401);
        }

        return $next($request);
    }
}
