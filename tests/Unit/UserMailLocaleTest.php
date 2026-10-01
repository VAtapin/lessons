<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Contracts\Translation\HasLocalePreference;
use PHPUnit\Framework\TestCase;

final class UserMailLocaleTest extends TestCase
{
    public function test_only_selected_supported_account_locales_are_used_with_stable_legacy_fallback(): void
    {
        $user = new User;
        $this->assertInstanceOf(HasLocalePreference::class, $user);
        foreach (['ru' => 'ru', 'de' => 'de', 'en' => 'ru', 'DE' => 'ru', '' => 'ru'] as $stored => $expected) {
            $user->forceFill(['ui_locale' => $stored]);
            $this->assertSame($expected, $user->preferredLocale());
        }
        $user->forceFill(['ui_locale' => null]);
        $this->assertSame('ru', $user->preferredLocale());
    }
}
