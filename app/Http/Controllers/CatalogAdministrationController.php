<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Catalog\AdminAccess;
use App\Application\Catalog\CatalogReviewService;
use App\Application\Catalog\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class CatalogAdministrationController
{
    public function __construct(private AdminAccess $access, private CatalogReviewService $reviews, private TaxonomyService $taxonomy) {}

    public function account(Request $request): JsonResponse
    {
        $this->access->require($request->user());

        return response()->json(['admin' => true]);
    }

    public function authorList(Request $request): JsonResponse
    {
        return response()->json(['submissions' => $this->reviews->authorList($this->access->account($request->user()))]);
    }

    public function submit(Request $request): JsonResponse
    {
        $author = $this->access->account($request->user());
        $input = $request->validate(['lessonId' => ['required', 'uuid'], 'versionId' => ['required', 'uuid'], 'expectedLessonRevision' => ['required', 'integer:strict', 'min:1'], 'slug' => ['required', 'string', 'max:120'], 'metadata' => ['required', 'array']]);

        return response()->json(['submission' => $this->reviews->submit($author, $input['lessonId'], $input['versionId'], $input['expectedLessonRevision'], $input['slug'], $input['metadata'])], 201);
    }

    public function queue(Request $request): JsonResponse
    {
        $input = $request->validate(['status' => ['sometimes', Rule::in(['pending', 'returned', 'approved'])]]);

        return response()->json(['submissions' => $this->reviews->adminList($request->user(), $input['status'] ?? null)]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->reviews->detail($request->user(), $id));
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $input = $request->validate(['expectedRevision' => ['required', 'integer:strict', 'min:1'], 'decision' => ['required', Rule::in(['approve', 'return'])], 'reason' => ['sometimes', 'nullable', 'string', 'max:2000']]);

        return response()->json(['submission' => $this->reviews->review($request->user(), $id, $input['expectedRevision'], $input['decision'], $input['reason'] ?? null)]);
    }

    public function entries(Request $request): JsonResponse
    {
        return response()->json(['entries' => $this->reviews->entries($request->user())]);
    }

    public function visibility(Request $request, string $slug): JsonResponse
    {
        $input = $request->validate(['expectedRevision' => ['required', 'integer:strict', 'min:1'], 'visible' => ['required', 'boolean:strict']]);

        return response()->json(['entry' => $this->reviews->visibility($request->user(), $slug, $input['expectedRevision'], $input['visible'])]);
    }

    public function terms(Request $request): JsonResponse
    {
        return response()->json(['terms' => $this->taxonomy->listing()]);
    }

    public function publicTerms(Request $request): JsonResponse
    {
        $input = $request->validate(['locale' => ['sometimes', Rule::in(['ru', 'de'])]]);

        return response()->json(['terms' => $this->taxonomy->listing($input['locale'] ?? 'ru')]);
    }

    public function saveTerm(Request $request, ?string $id = null): JsonResponse
    {
        $input = $request->validate(['kind' => ['required', Rule::in(['age', 'topic', 'audience', 'format'])], 'key' => ['required', 'string', 'max:80'], 'labels' => ['required', 'array'], 'active' => ['required', 'boolean:strict'], 'expectedRevision' => [$id === null ? 'sometimes' : 'required', 'integer:strict', 'min:1']]);

        return response()->json(['term' => $this->taxonomy->save($request->user(), array_intersect_key($input, array_flip(['kind', 'key', 'labels', 'active'])), $id, $input['expectedRevision'] ?? null)], $id === null ? 201 : 200);
    }
}
