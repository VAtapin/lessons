<?php

namespace App\Http\Controllers;

use App\Application\Catalog\CatalogService;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(CatalogService $catalog): Response
    {
        $pages = [];
        foreach (config('lessons.ui_locales') as $locale) {
            $pages[] = url('/'.$locale);
            $pages[] = url('/'.$locale.'/catalog');
            $page = 1;
            do {
                $listing = $catalog->listing($locale, ['page' => $page]);
                foreach ($listing['entries'] as $entry) {
                    $pages[] = url('/'.$locale.'/catalog/'.$entry['slug']);
                }
            } while (++$page <= $listing['pagination']['lastPage']);
        }

        return response()->view('sitemap', ['pages' => $pages], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
