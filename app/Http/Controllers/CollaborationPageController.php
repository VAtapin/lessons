<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Collaboration\TeacherAccess;
use App\Application\Runtime\RuntimeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

final class CollaborationPageController extends Controller
{
    public function invitation(string $locale): View
    {
        return $this->page($locale, 'teacher-invitation');
    }

    public function teacher(Request $request, TeacherAccess $access, RuntimeService $runtime, string $locale, string $sessionId): View
    {
        $runtime->teacherActor($access->cookieActor($request, $sessionId), $sessionId);

        return $this->page($locale, 'teacher', ['sessionId' => $sessionId, 'teacherScope' => 'grant']);
    }

    public function projector(Request $request, TeacherAccess $access, RuntimeService $runtime, string $locale, string $sessionId): View
    {
        $runtime->projectorActor($access->cookieActor($request, $sessionId), $sessionId);

        return $this->page($locale, 'projector', ['sessionId' => $sessionId, 'teacherScope' => 'grant']);
    }

    public function control(Request $request, TeacherAccess $access, RuntimeService $runtime, string $locale, string $sessionId): View
    {
        $runtime->teacherActor($access->cookieActor($request, $sessionId), $sessionId);

        return $this->page($locale, 'control', ['sessionId' => $sessionId, 'teacherScope' => 'grant']);
    }

    private function page(string $locale, string $page, array $context = []): View
    {
        abort_unless(in_array($locale, config('lessons.ui_locales'), true), 404);
        App::setLocale($locale);

        return view('home', ['locale' => $locale, 'page' => $page, 'context' => $context,
            'messages' => trans('interface'), 'studioMessages' => array_merge(trans('studio'), trans('deletion'), trans('collaboration'), trans('wave'))]);
    }
}
