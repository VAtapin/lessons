<?php

namespace App\Http\Controllers;

use App\Application\Catalog\AdminAccess;
use App\Application\History\HistoryService;
use App\Application\History\RehearsalService;
use App\Application\History\RetentionPolicy;
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
            'messages' => trans('interface'), 'studioMessages' => array_merge(trans('studio'), trans('deletion'), trans('collaboration'), trans('admin')),
        ]);
    }

    public function studio(Request $request, GuestIdentity $identity, string $locale): View
    {
        $identity->key($request);

        return $this->page($locale, 'studio');
    }

    public function administration(Request $request, AdminAccess $access, string $locale): View
    {
        $access->require($request->user());

        return $this->page($locale, 'admin');
    }

    public function library(Request $request, GuestIdentity $identity, string $locale): View
    {
        $identity->key($request);

        return $this->page($locale, 'library');
    }

    public function media(Request $request, GuestIdentity $identity, string $locale): View
    {
        $identity->key($request);

        return $this->page($locale, 'media');
    }

    public function editor(Request $request, GuestIdentity $identity, StudioService $studio, string $locale, string $lessonId): View
    {
        $studio->findOwned($identity->key($request), $lessonId);

        return $this->page($locale, 'editor', ['lessonId' => $lessonId]);
    }

    public function teacher(Request $request, GuestIdentity $identity, RuntimeService $runtime, RetentionPolicy $policy, string $locale, string $sessionId): View
    {
        $policy->assertOwnerReadable($runtime->findOwned($identity->key($request), $sessionId));

        return $this->page($locale, 'teacher', ['sessionId' => $sessionId]);
    }

    public function control(Request $request, GuestIdentity $identity, RuntimeService $runtime, RetentionPolicy $policy, string $locale, string $sessionId): View
    {
        $policy->assertOwnerReadable($runtime->findOwned($identity->key($request), $sessionId));

        return $this->page($locale, 'control', ['sessionId' => $sessionId]);
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

    public function authentication(Request $request, string $locale, string $kind): View
    {
        abort_unless(in_array($kind, ['login', 'register', 'forgot-password', 'verify-email', 'account'], true), 404);

        return $this->page($locale, $kind);
    }

    public function resetPassword(Request $request, string $locale, string $token): View
    {
        $email = $request->query('email');

        return $this->page($locale, 'reset-password', [
            'resetToken' => $token, 'email' => is_string($email) ? mb_substr($email, 0, 254) : '',
        ]);
    }

    public function history(Request $request, GuestIdentity $identity, HistoryService $history, string $locale, ?string $sessionId = null): View
    {
        $owner = $identity->key($request);
        if ($sessionId !== null) {
            $history->findOwned($owner, $sessionId);
        }

        return $this->page($locale, 'history', $sessionId === null ? [] : ['sessionId' => $sessionId]);
    }

    public function rehearsal(Request $request, GuestIdentity $identity, RehearsalService $rehearsals, string $locale, string $sessionId, string $audience): View
    {
        abort_unless(in_array($audience, ['student', 'projector'], true), 404);
        $rehearsals->preview($identity->key($request), $sessionId, $audience);

        return $this->page($locale, 'rehearsal', ['sessionId' => $sessionId, 'audience' => $audience]);
    }
}
