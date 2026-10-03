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
        $filters = request()->validate(['q' => ['sometimes', 'string', 'max:200'], 'age' => ['sometimes', 'string', 'max:80'],
            'topic' => ['sometimes', 'string', 'max:80'], 'audience' => ['sometimes', 'string', 'max:80'],
            'format' => ['sometimes', 'string', 'max:80'], 'duration' => ['sometimes', 'in:short,standard,long'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        $publicContent = $slug !== null ? $catalog->detail($slug, $locale) : $catalog->listing($locale, $filters);

        return view('home', ['locale' => $locale, 'page' => 'catalog', 'context' => ['slug' => $slug, 'initialCatalog' => $publicContent],
            'publicContent' => $publicContent,
            'messages' => trans('interface'), 'studioMessages' => array_merge(trans('studio'), trans('wave'))]);
    }

    public function __invoke(?string $locale = null): View
    {
        $locale ??= config('app.locale');
        abort_unless(in_array($locale, config('lessons.ui_locales'), true), 404);
        App::setLocale($locale);

        return view('home', ['locale' => $locale, 'messages' => trans('interface')]);
    }
}
