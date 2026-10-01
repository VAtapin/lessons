<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Application\Shared\GuestIdentity;
use App\Application\Studio\EditorProblem;
use App\Application\Studio\EditorSaveRequest;
use App\Application\Studio\StudioService;
use App\Domain\Lessons\Audience;
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
        if ($request->exists('saveId')) {
            return response()->json($this->studio->saveEditor($owner, $id, EditorSaveRequest::fromJson($request->getContent())));
        }
        $data = $request->validate(['expectedRevision' => ['required', 'integer', 'min:1'], 'document' => ['required', 'array']]);
        $material = $this->studio->save($owner, $id, (int) $data['expectedRevision'], $data['document']);

        return response()->json(['lesson' => $this->studio->present($material)]);
    }

    public function release(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->studio->findOwned($owner, $id);
        $data = $request->validate(['expectedRevision' => ['required', 'integer', 'min:1'], 'locales' => ['sometimes', 'array', 'min:1'], 'locales.*' => ['string']]);
        if (array_diff(array_keys($request->all()), ['expectedRevision', 'locales']) !== []) {
            throw new EditorProblem('invalid_editor_document', 422, [['code' => 'invalid_envelope', 'path' => '']]);
        }
        $version = $this->studio->release($owner, $id, (int) $data['expectedRevision'], $data['locales'] ?? null);

        return response()->json(['lesson' => $this->studio->present($version->material)]);
    }

    public function preview(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->studio->findOwned($owner, $id);
        $data = $request->all();
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['audience', 'document', 'expectedRevision', 'locale', 'stageId'] || ! is_int($data['expectedRevision']) || $data['expectedRevision'] < 1
            || ! is_array($data['document']) || ! is_string($data['locale']) || ! is_string($data['stageId']) || ! is_string($data['audience'])
            || ($audience = Audience::tryFrom($data['audience'])) === null) {
            throw new EditorProblem('invalid_editor_document', 422, [['code' => 'invalid_envelope', 'path' => '']]);
        }

        return response()->json(['preview' => $this->studio->preview($owner, $id, $data['expectedRevision'], $data['document'], $audience, $data['locale'], $data['stageId'])]);
    }
}
