<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;

final class HomeController extends Controller
{
    public function __invoke(?string $locale = null): View
    {
        $locale ??= config('app.locale');
        abort_unless(in_array($locale, config('lessons.ui_locales'), true), 404);
        App::setLocale($locale);

        return view('home', ['locale' => $locale, 'messages' => trans('interface')]);
    }
}
