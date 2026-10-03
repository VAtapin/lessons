<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Catalog\CommonTemplateService;
use App\Application\Shared\GuestIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class CommonTemplateController
{
    public function __construct(private CommonTemplateService $templates, private GuestIdentity $identity) {}

    public function index(Request $request): JsonResponse
    {
        $input = $request->validate(['locale' => ['sometimes', Rule::in(['ru', 'de'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'], 'tag' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'nullable', 'string', 'max:128'], 'scope' => ['sometimes', Rule::in(['all', 'universal', 'lesson'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);

        return response()->json($this->templates->page($input['locale'] ?? 'ru', $input));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $input = $request->validate(['locale' => ['sometimes', Rule::in(['ru', 'de'])]]);

        return response()->json($this->templates->detail($id, $input['locale'] ?? 'ru'));
    }

    public function adminIndex(Request $request): JsonResponse
    {
        return response()->json(['templates' => $this->templates->listing(null, $request->user())]);
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $input = $request->validate(['locales' => ['required', 'array'], 'defaultLocale' => ['required', 'string'], 'block' => ['required', 'array'], 'labels' => ['required', 'array'], 'attribution' => ['required', 'array'], 'expectedRevision' => [$id === null ? 'sometimes' : 'required', 'integer:strict', 'min:1']]);

        return response()->json(['template' => $this->templates->save($request->user(), $input, $id, $input['expectedRevision'] ?? null)], $id === null ? 201 : 200);
    }

    public function visibility(Request $request, string $id): JsonResponse
    {
        $input = $request->validate(['expectedRevision' => ['required', 'integer:strict', 'min:1'], 'visible' => ['required', 'boolean:strict']]);

        return response()->json(['template' => $this->templates->visibility($request->user(), $id, $input['expectedRevision'], $input['visible'])]);
    }

    public function instantiate(Request $request, string $id): JsonResponse
    {
        $input = $request->validate(['versionId' => ['required', 'uuid'], 'locales' => ['required', 'array']]);

        return response()->json(['block' => $this->templates->instantiate($id, $input['versionId'], $input['locales'], $this->identity->key($request))]);
    }
}
