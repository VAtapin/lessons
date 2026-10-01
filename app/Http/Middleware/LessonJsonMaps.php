<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Preserve the declared role-ID map at the JSON boundary, including numeric IDs. */
final class LessonJsonMaps
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->is('api/*') && $response instanceof JsonResponse && is_array($response->getOriginalContent())) {
            $changed = false;
            $data = $this->normalize($response->getOriginalContent(), $changed);
            if ($changed) {
                $response->setData($data);
            }
        }

        return $response;
    }

    private function normalize(array $data, bool &$changed): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalize($value, $changed);
            }
        }
        if (($data['type'] ?? null) === 'core.roles' && ($data['schemaVersion'] ?? null) === 1
            && is_array($data['config']['capacities'] ?? null)) {
            // PHP casts "0"/"1" map keys to integers; plain json_encode emits a list.
            // Storage/domain stay arrays; only this whitelisted wire field becomes an object.
            $data['config']['capacities'] = (object) $data['config']['capacities'];
            $changed = true;
        }

        return $data;
    }
}
