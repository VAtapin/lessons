<?php

namespace Tests\Feature;

use App\Domain\Lessons\BlockRegistry;
use App\Domain\Lessons\Types\TextBlock;
use Tests\TestCase;

final class FoundationTest extends TestCase
{
    public function test_home_bootstraps_russian_messages(): void
    {
        $this->withoutVite()->get('/')->assertOk()
            ->assertSee('lang="ru"', false)->assertSee('data-locale="ru"', false)
            ->assertSee('Готовим платформу для ваших занятий');
    }

    public function test_german_home_receives_its_own_dictionary(): void
    {
        $this->withoutVite()->get('/de')->assertOk()
            ->assertSee('lang="de"', false)->assertSee('Wir bereiten die Plattform');
    }

    public function test_unknown_locale_is_not_accepted(): void
    {
        $this->get('/xx')->assertNotFound();
    }

    public function test_health_endpoint_runs_without_a_database(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_application_resolves_the_trusted_block_registry(): void
    {
        $registry = $this->app->make(BlockRegistry::class);
        $this->assertSame($registry, $this->app->make(BlockRegistry::class));
        $this->assertInstanceOf(TextBlock::class, $registry->resolve('core.text', 1));
    }
}
