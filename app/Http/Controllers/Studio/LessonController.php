<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Application\Shared\GuestIdentity;
use App\Application\Studio\StudioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LessonController extends Controller
{
    public function __construct(private readonly StudioService $studio, private readonly GuestIdentity $identity) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['lessons' => $this->studio->listOwned($this->identity->key($request))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['document' => ['required', 'array']]);
        $material = $this->studio->create($this->identity->key($request), $data['document']);

        return response()->json(['lesson' => $this->studio->present($material)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $material = $this->studio->findOwned($this->identity->key($request), $id);

        return response()->json(['lesson' => $this->studio->present($material)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->studio->findOwned($owner, $id);
        $data = $request->validate(['expectedRevision' => ['required', 'integer', 'min:1'], 'document' => ['required', 'array']]);
        $material = $this->studio->save($owner, $id, (int) $data['expectedRevision'], $data['document']);

        return response()->json(['lesson' => $this->studio->present($material)]);
    }

    public function release(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->studio->findOwned($owner, $id);
        $data = $request->validate(['expectedRevision' => ['required', 'integer', 'min:1']]);
        $version = $this->studio->release($owner, $id, (int) $data['expectedRevision']);

        return response()->json(['lesson' => $this->studio->present($version->material)]);
    }
}
