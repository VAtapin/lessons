<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Catalog\CatalogService;
use App\Application\Shared\GuestIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalog, private readonly GuestIdentity $identity) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['locale' => ['sometimes', Rule::in(['ru', 'de'])], 'q' => ['sometimes', 'string', 'max:200'],
            'age' => ['sometimes', 'string', 'max:80'], 'topic' => ['sometimes', 'string', 'max:80'],
            'audience' => ['sometimes', 'string', 'max:80'], 'format' => ['sometimes', 'string', 'max:80'],
            'duration' => ['sometimes', Rule::in(['short', 'standard', 'long'])], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);

        return response()->json($this->catalog->listing($filters['locale'] ?? 'ru', $filters));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        return response()->json($this->catalog->detail($slug, $this->locale($request)));
    }

    public function use(Request $request, string $slug): JsonResponse
    {
        return response()->json($this->catalog->use($slug, $this->locale($request), $this->identity->key($request)), 201);
    }

    public function start(Request $request, string $slug): JsonResponse
    {
        return response()->json($this->catalog->use($slug, $this->locale($request), $this->identity->key($request), start: true), 201);
    }

    private function locale(Request $request): string
    {
        return $request->validate(['locale' => ['sometimes', Rule::in(['ru', 'de'])]])['locale'] ?? 'ru';
    }
}
