<?php

namespace App\Http\Controllers;

use App\Application\Catalog\CatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;

final class HomeController extends Controller
{
    public function catalog(CatalogService $catalog, string $locale, ?string $slug = null): View
    {
        abort_unless(in_array($locale, config('lessons.ui_locales'), true), 404);
        App::setLocale($locale);
        if ($slug !== null) {
            $catalog->detail($slug, $locale);
        }

        return view('home', ['locale' => $locale, 'page' => 'catalog', 'context' => ['slug' => $slug],
            'messages' => trans('interface'), 'studioMessages' => trans('studio')]);
    }

    public function __invoke(?string $locale = null): View
    {
        $locale ??= config('app.locale');
        abort_unless(in_array($locale, config('lessons.ui_locales'), true), 404);
        App::setLocale($locale);

        return view('home', ['locale' => $locale, 'messages' => trans('interface')]);
    }
}
