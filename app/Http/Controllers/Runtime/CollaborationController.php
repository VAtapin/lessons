<?php

declare(strict_types=1);

namespace App\Http\Controllers\Runtime;

use App\Application\Collaboration\CollaborationService;
use App\Application\Collaboration\TeacherAccess;
use App\Application\Collaboration\TeacherActor;
use App\Application\Collaboration\TeacherInvitations;
use App\Application\Runtime\RuntimeService;
use App\Application\Shared\ApiProblem;
use App\Application\Shared\GuestIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CollaborationController extends Controller
{
    public function __construct(private CollaborationService $collaboration, private TeacherInvitations $invitations,
        private TeacherAccess $access, private RuntimeService $runtime, private GuestIdentity $identity) {}

    public function overview(Request $request, string $id): JsonResponse
    {
        return response()->json($this->collaboration->overview(TeacherActor::owner($this->identity->key($request)), $id));
    }

    public function command(Request $request, string $id): JsonResponse
    {
        [$input, $extra] = $this->commandInput($request);

        return response()->json($this->collaboration->command(TeacherActor::owner($this->identity->key($request)), $id,
            $input['commandId'], $input['expectedRevision'], $input['controlEpoch'], $input['action'], $input['payload'], $extra));
    }

    public function accept(Request $request): JsonResponse
    {
        if (array_diff(array_keys($request->json()->all()), ['token', 'displayName']) !== []) {
            throw new ApiProblem('invalid_action', 422);
        }
        $input = $request->validate(['token' => ['required', 'uuid'], 'displayName' => ['required', 'string', 'max:80']]);
        $installed = $request->session()->get(TeacherAccess::COOKIE_KEY, []);
        $result = $this->invitations->accept($input['token'], $input['displayName'], is_array($installed) ? $installed : []);
        $installed = is_array($installed) ? $installed : [];
        $installed[$result['sessionId']] = $result['cookie'];
        $request->session()->put(TeacherAccess::COOKIE_KEY, $installed);
        unset($result['cookie']);

        return response()->json($result);
    }

    public function teacher(Request $request, string $id): JsonResponse
    {
        return response()->json($this->runtime->teacherActor($this->access->cookieActor($request, $id), $id));
    }

    public function teacherCommand(Request $request, string $id): JsonResponse
    {
        $actor = $this->access->cookieActor($request, $id);
        [$input, $extra] = $this->commandInput($request);

        return response()->json($this->runtime->actorCommand($actor, $id, $input['commandId'], $input['expectedRevision'],
            $input['action'], $input['payload'], $extra, $input['controlEpoch']));
    }

    public function projection(Request $request, string $id): JsonResponse
    {
        return response()->json(['session' => $this->runtime->projectorActor($this->access->cookieActor($request, $id), $id)]);
    }

    private function commandInput(Request $request): array
    {
        $input = $request->validate(['commandId' => ['required', 'uuid'], 'expectedRevision' => ['required', 'integer:strict', 'min:1'],
            'controlEpoch' => ['required', 'integer:strict', 'min:0'], 'action' => ['required', 'string', 'max:64'], 'payload' => ['present', 'array']]);
        $extra = array_diff_key($request->json()->all(), array_flip(['commandId', 'expectedRevision', 'controlEpoch', 'action', 'payload']));

        return [$input, $extra];
    }
}
