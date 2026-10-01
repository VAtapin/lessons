<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Catalog\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireCatalogAdmin
{
    public function __construct(private AdminAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->access->require($request->user());

        return $next($request);
    }
}
