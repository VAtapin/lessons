<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Account\AccountIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminPageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_requires_current_verified_admin_permission(): void
    {
        $this->withoutVite();
        $this->get('/ru/admin')->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $this->get('/ru/admin')->assertForbidden();
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        $this->get('/ru/admin')->assertOk()->assertSee('data-page="admin"', false)->assertSee('Управление публикациями');
        $this->get('/de/admin')->assertOk()->assertSee('Veröffentlichungen verwalten');
        User::query()->whereKey($user->id)->update(['is_admin' => false]);
        $this->get('/ru/admin')->assertForbidden();
        User::query()->whereKey($user->id)->update(['is_admin' => true, 'email_verified_at' => null]);
        $this->get('/de/admin')->assertForbidden();
    }

    public function test_account_admin_navigation_flag_uses_current_database_permission(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->forceFill(['is_admin' => true])->save();
        $this->assertTrue(AccountIdentity::present($user)['isAdmin']);
        User::query()->whereKey($user->id)->update(['is_admin' => false]);
        $this->assertFalse(AccountIdentity::present($user)['isAdmin']);
        User::query()->whereKey($user->id)->update(['is_admin' => true, 'email_verified_at' => null]);
        $this->assertFalse(AccountIdentity::present($user)['isAdmin']);
        User::query()->whereKey($user->id)->update(['is_admin' => false, 'email_verified_at' => now()]);
        $user->is_admin = true;
        $this->assertFalse(AccountIdentity::present($user)['isAdmin']);
    }
}
