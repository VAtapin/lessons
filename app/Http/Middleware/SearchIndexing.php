<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SearchIndexing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $locales = implode('|', array_map(fn ($locale) => preg_quote($locale, '~'), config('lessons.ui_locales')));
        $public = $request->path() === '/' || $request->is('sitemap.xml', 'media/builtin/*', 'social-cover.jpg', 'social/builtin/*')
            || preg_match('~^('.$locales.')(?:/catalog(?:/[a-z0-9]+(?:-[a-z0-9]+)*)?)?$~D', $request->path());
        if (! $public || $response->getStatusCode() !== 200 || $request->query->count() > 0) {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
