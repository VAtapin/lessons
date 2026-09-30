<?php

declare(strict_types=1);

namespace App\Http\Controllers\Runtime;

use App\Application\Runtime\RuntimeService;
use App\Application\Shared\GuestIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RuntimeController extends Controller
{
    public const SESSION_PARTICIPANTS_KEY = 'lesson_participants';

    public function __construct(private RuntimeService $runtime, private GuestIdentity $identity) {}

    public function start(Request $request, string $id): JsonResponse
    {
        $input = $request->validate(['expectedRevision' => ['required', 'integer', 'min:1'], 'locale' => ['sometimes', 'string', 'max:35']]);

        return response()->json(['session' => $this->runtime->start($this->identity->key($request), $id, (int) $input['expectedRevision'], $input['locale'] ?? null)], 201);
    }

    public function teacher(Request $request, string $id): JsonResponse
    {
        return response()->json(['session' => $this->runtime->teacher($this->identity->key($request), $id)]);
    }

    public function navigate(Request $request, string $id): JsonResponse
    {
        $input = $request->validate(['expectedRevision' => ['required', 'integer', 'min:1'], 'stageId' => ['required', 'string', 'max:128']]);

        return response()->json(['session' => $this->runtime->navigate($this->identity->key($request), $id, (int) $input['expectedRevision'], $input['stageId'])]);
    }

    public function join(Request $request): JsonResponse
    {
        $input = $request->validate(['code' => ['required', 'string', 'size:8'], 'name' => ['required', 'string', 'max:80']]);
        $participants = $request->session()->get(self::SESSION_PARTICIPANTS_KEY, []);
        $result = $this->runtime->join($input['code'], $input['name'], $participants);
        $participants[$result['sessionId']] = $result['participant']['id'];
        $request->session()->put(self::SESSION_PARTICIPANTS_KEY, $participants);

        return response()->json($result);
    }

    public function student(Request $request, string $id): JsonResponse
    {
        return response()->json(['session' => $this->runtime->student($id, $this->participantId($request, $id))]);
    }

    public function answer(Request $request, string $id): JsonResponse
    {
        $input = $request->validate([
            'stageId' => ['required', 'string', 'max:128'], 'blockId' => ['required', 'string', 'max:128'], 'optionId' => ['required', 'string', 'max:128'],
        ]);

        return response()->json(['session' => $this->runtime->answer($id, $this->participantId($request, $id), $input['stageId'], $input['blockId'], $input['optionId'])]);
    }

    public function projector(string $token): JsonResponse
    {
        return response()->json(['session' => $this->runtime->projector($token)]);
    }

    private function participantId(Request $request, string $sessionId): ?string
    {
        $id = $request->session()->get(self::SESSION_PARTICIPANTS_KEY, [])[$sessionId] ?? null;

        return is_string($id) ? $id : null;
    }
}
