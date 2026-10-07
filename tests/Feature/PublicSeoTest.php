<?php

namespace Tests\Feature;

use App\Application\Catalog\NeighborInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        URL::forceRootUrl('http://localhost');
    }

    public function test_public_html_has_localized_metadata_and_real_lesson_content_without_javascript(): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        foreach (['ru', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/catalog/'.$entry->slug)->assertOk();
            $response->assertSee('<h1>'.$entry->metadata['translations'][$locale]['title'].'</h1>', false)
                ->assertSee('rel="canonical" href="http://localhost/'.$locale.'/catalog/'.$entry->slug.'"', false)
                ->assertSee('hreflang="ru"', false)->assertSee('hreflang="de"', false)
                ->assertSee('name="twitter:card" content="summary_large_image"', false)
                ->assertSee('LearningResource')->assertSee('BreadcrumbList')
                ->assertSee($entry->metadata['details'][$locale]['goals'][0]);
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, '<h1>'));
            preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $match);
            $this->assertSame('https://schema.org', json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)['@context']);
            $this->get('/'.$locale.'/catalog')->assertOk()->assertSee('href="/'.$locale.'/catalog/'.$entry->slug.'"', false);
            $this->get('/'.$locale)->assertOk()->assertSee('<h1>', false)->assertSee('og:image', false);
        }
    }

    public function test_sitemap_only_lists_published_content_and_all_pages_of_catalog(): void
    {
        $entry = app(NeighborInstaller::class)->install()['entry'];
        for ($number = 1; $number <= 13; $number++) {
            $copy = $entry->replicate();
            $copy->slug = 'public-'.$number;
            $copy->save();
        }
        $hidden = $entry->replicate();
        $hidden->slug = 'hidden-lesson';
        $hidden->status = 'hidden';
        $hidden->save();
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/ru/catalog/public-13')->assertSee('/de/catalog/public-13')
            ->assertDontSee('hidden-lesson')->assertDontSee('/studio')->assertDontSee('/teach');
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
        $this->get('/ru/catalog/hidden-lesson')->assertNotFound();
    }

    public function test_private_pages_and_filtered_urls_are_noindex_and_missing_pages_have_localized_404(): void
    {
        $this->get('/ru/join')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('name="robots" content="noindex, follow"', false)->assertDontSee('rel="canonical"', false);
        $this->get('/ru/catalog?q=help')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->assertSee('rel="canonical" href="http://localhost/ru/catalog"', false);
        $this->get('/de/catalog/missing-lesson')->assertNotFound()->assertSee('<html lang="de">', false)
            ->assertSee('href="/de/catalog"', false)->assertSee('noindex, follow');
        $this->get('/de/not-a-route')->assertNotFound()->assertSee('<html lang="de">', false);
        $this->getJson('/api/catalog/missing-lesson?locale=de')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_social_images_are_real_jpeg_with_share_dimensions_and_cache_headers(): void
    {
        foreach (['/social-cover.jpg', '/social/builtin/builtin-neighbor-road-v1.jpg'] as $url) {
            $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeaderMissing('X-Robots-Tag');
            $size = getimagesizefromstring($response->getContent());
            $this->assertSame([1200, 630, IMAGETYPE_JPEG], array_slice($size, 0, 3));
            $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        }
        $this->get('/social/builtin/missing.jpg')->assertNotFound();
    }
}
