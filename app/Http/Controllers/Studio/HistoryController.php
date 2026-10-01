<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Application\History\HistoryService;
use App\Application\History\RehearsalService;
use App\Application\Runtime\RuntimeConflict;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\GuestIdentity;
use App\Application\Studio\StudioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HistoryController extends Controller
{
    public function __construct(private readonly HistoryService $history, private readonly RehearsalService $rehearsals, private readonly GuestIdentity $identity, private readonly StudioService $studio) {}

    public function index(Request $request): JsonResponse
    {
        $this->keys($request->query(), ['status', 'mode', 'cursor']);

        return response()->json($this->history->list($this->identity->key($request), $request->query()));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['history' => $this->history->detail($this->identity->key($request), $id)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->history->findOwned($owner, $id);
        $data = $request->all();
        $this->keys($data, ['expectedRevision', 'teacherNotes'], true);
        if (! is_int($data['expectedRevision']) || $data['expectedRevision'] < 1 || ! is_string($data['teacherNotes'])
            || ! mb_check_encoding($data['teacherNotes'], 'UTF-8') || mb_strlen($data['teacherNotes']) > 5000) {
            throw new ApiProblem('invalid_action', 422);
        }
        try {
            return response()->json(['history' => $this->history->notes($owner, $id, $data['expectedRevision'], $data['teacherNotes'])]);
        } catch (RuntimeConflict $conflict) {
            return response()->json(['error' => ['code' => $conflict->problemCode], 'history' => $conflict->state], 409);
        }
    }

    public function again(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->history->findOwned($owner, $id);
        $this->keys($request->all(), [], true);

        return response()->json(['session' => $this->history->again($owner, $id)], 201);
    }

    public function versions(Request $request, string $id): JsonResponse
    {
        return response()->json(['versions' => $this->history->versions($this->identity->key($request), $id)]);
    }

    public function version(Request $request, string $id, string $versionId): JsonResponse
    {
        return response()->json(['version' => $this->history->version($this->identity->key($request), $id, $versionId)]);
    }

    public function favorite(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->studio->findOwned($owner, $id);
        $data = $request->all();
        $this->keys($data, ['favorite'], true);
        if (! is_bool($data['favorite'])) {
            throw new ApiProblem('invalid_action', 422);
        }

        return response()->json($this->history->favorite($owner, $id, $data['favorite']));
    }

    public function rehearsal(Request $request, string $id): JsonResponse
    {
        $owner = $this->identity->key($request);
        $this->studio->findOwned($owner, $id);
        $data = $request->all();
        $this->keys($data, ['expectedRevision', 'locale']);
        if (! is_int($data['expectedRevision'] ?? null) || $data['expectedRevision'] < 1
            || (array_key_exists('locale', $data) && ! is_string($data['locale']))) {
            throw new ApiProblem('invalid_action', 422);
        }

        return response()->json(['session' => $this->rehearsals->start($owner, $id, $data['expectedRevision'], $data['locale'] ?? null)], 201);
    }

    public function preview(Request $request, string $id, string $audience): JsonResponse
    {
        return response()->json(['session' => $this->rehearsals->preview($this->identity->key($request), $id, $audience)]);
    }

    public function answer(Request $request, string $id): JsonResponse
    {
        return response()->json(['session' => $this->rehearsals->answer($this->identity->key($request), $id, $request->all())]);
    }

    private function keys(array $data, array $allowed, bool $required = false): void
    {
        if (array_diff(array_keys($data), $allowed) !== [] || ($required && array_diff($allowed, array_keys($data)) !== [])) {
            throw new ApiProblem('invalid_action', 422);
        }
    }
}
