<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_and_legal_links_are_available_without_javascript(): void
    {
        $this->withoutVite();
        foreach (['ru', 'de'] as $locale) {
            $contact = config('public-site.contact');
            $links = config('public-site.links.'.$locale);
            $home = $this->get('/'.$locale)->assertOk();
            $home->assertSee('id="contact"', false)->assertSee($contact['name'])->assertSee($contact['address'])
                ->assertSee('mailto:'.$contact['email'], false)->assertSee($contact['phoneHref'], false);
            foreach (['/'.$locale, '/'.$locale.'/catalog', '/'.$locale.'/register', '/'.$locale.'/join'] as $url) {
                $page = $this->get($url)->assertOk();
                foreach ($links as $link) {
                    $page->assertSee('href="'.$link.'"', false);
                }
            }
            $home->assertDontSee('info@atapin.de');
        }
    }
}
