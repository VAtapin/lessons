<?php

namespace App\Http\Controllers;

use App\Application\Runtime\RuntimeService;
use App\Application\Shared\GuestIdentity;
use App\Application\Studio\StudioService;
use App\Http\Controllers\Runtime\RuntimeController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

final class WorkspacePageController extends Controller
{
    private function page(string $locale, string $page, array $context = []): View
    {
        abort_unless(in_array($locale, config('lessons.ui_locales'), true), 404);
        App::setLocale($locale);

        return view('home', [
            'locale' => $locale, 'page' => $page, 'context' => $context,
            'messages' => trans('interface'), 'studioMessages' => trans('studio'),
        ]);
    }

    public function studio(Request $request, GuestIdentity $identity, string $locale): View
    {
        $identity->key($request);

        return $this->page($locale, 'studio');
    }

    public function editor(Request $request, GuestIdentity $identity, StudioService $studio, string $locale, string $lessonId): View
    {
        $studio->findOwned($identity->key($request), $lessonId);

        return $this->page($locale, 'editor', ['lessonId' => $lessonId]);
    }

    public function teacher(Request $request, GuestIdentity $identity, RuntimeService $runtime, string $locale, string $sessionId): View
    {
        $runtime->findOwned($identity->key($request), $sessionId);

        return $this->page($locale, 'teacher', ['sessionId' => $sessionId]);
    }

    public function join(string $locale): View
    {
        return $this->page($locale, 'join');
    }

    public function student(Request $request, RuntimeService $runtime, string $locale, string $sessionId): View
    {
        $participantId = $request->session()->get(RuntimeController::SESSION_PARTICIPANTS_KEY.'.'.$sessionId);
        $runtime->student($sessionId, is_string($participantId) ? $participantId : null);

        return $this->page($locale, 'student', ['sessionId' => $sessionId]);
    }

    public function projector(RuntimeService $runtime, string $locale, string $projectorToken): View
    {
        $runtime->projector($projectorToken);

        return $this->page($locale, 'projector', ['projectorToken' => $projectorToken]);
    }
}
