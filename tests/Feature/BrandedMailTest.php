<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class BrandedMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        // Real Laravel rendering/channel and MIME assembly; transport is memory-only.
        config(['mail.default' => 'array', 'mail.from.address' => 'lessons@atapin.de', 'mail.from.name' => 'lessons.atapin.de']);
    }

    public static function locales(): array
    {
        return [['ru', 'de'], ['de', 'ru']];
    }

    #[DataProvider('locales')]
    public function test_verification_mail_uses_selected_account_locale_html_and_plain_text_and_original_signed_link(string $locale, string $opposite): void
    {
        app()->setLocale($opposite);
        $user = User::factory()->unverified()->create(['ui_locale' => $locale, 'name' => '<script>alert(1)</script>&"Ü']);
        $notification = new VerifyEmail;
        $mail = $notification->toMail($user);
        $this->assertSame(__('mail.verify.subject', [], $locale), $mail->subject);
        $this->assertTrue(URL::hasValidSignature(Request::create($mail->actionUrl)));
        $this->assertStringContainsString('/email/verify/'.$user->id.'/'.sha1($user->getEmailForVerification()), $mail->actionUrl);
        $user->sendEmailVerificationNotification();
        $this->assertSame($opposite, app()->getLocale(), 'Notification locale must not leak into the caller.');
        $message = $this->lastMail();
        $this->assertSame($mail->subject, $message->getSubject());
        $this->assertSame('lessons@atapin.de', $message->getFrom()[0]->getAddress());
        $html = $message->getHtmlBody();
        $text = $message->getTextBody();
        $this->assertStringContainsString('<html lang="'.$locale.'">', $html);
        $this->assertStringContainsString('background-color:#fbf3e4', $html);
        $this->assertStringContainsString('max-width:600px', $html);
        $this->assertStringContainsString(__('mail.verify.action', [], $locale), $html);
        $this->assertStringContainsString(e($mail->actionUrl), $html);
        $this->assertStringContainsString('word-break:break-all', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;&amp;&quot;Ü', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringNotContainsString('/build/', $html);
        $this->assertStringNotContainsString('Verify Email Address', $html);
        $this->assertStringNotContainsString('If you did not create an account', $html);
        $this->assertStringContainsString($user->name, $text);
        $this->assertStringContainsString(__('mail.verify.title', [], $locale), $text);
        $this->assertStringContainsString($mail->actionUrl, $text);
        $this->assertStringNotContainsString('<table', $text);
        $this->assertStringNotContainsString('&amp;', $text);
        $this->assertStringContainsString('multipart/alternative', $message->toString());
        $this->assertFalse($user->fresh()->hasVerifiedEmail(), 'Rendering and sending do not verify an account.');
    }

    public function test_actual_verification_button_link_is_bound_signed_and_expires(): void
    {
        $user = User::factory()->unverified()->create(['ui_locale' => 'de']);
        $url = (new VerifyEmail)->toMail($user)->actionUrl;
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $this->get($url.'&changed=1')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect('/de/account');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
    }

    #[DataProvider('locales')]
    public function test_reset_mail_preserves_password_broker_token_and_selected_locale_url(string $locale, string $opposite): void
    {
        app()->setLocale($opposite);
        $user = User::factory()->create(['ui_locale' => $locale, 'email' => 'fixture+'.$locale.'@example.test']);
        $token = Password::broker()->createToken($user);
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->assertNotSame($token, DB::table('password_reset_tokens')->where('email', $user->email)->value('token'));
        $mail = (new ResetPassword($token))->toMail($user);
        $this->assertSame(__('mail.reset.subject', [], $locale), $mail->subject);
        $this->assertSame('/'.$locale.'/reset-password/'.$token, parse_url($mail->actionUrl, PHP_URL_PATH));
        parse_str(parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $this->assertSame($user->email, $query['email']);
        $user->sendPasswordResetNotification($token);
        $message = $this->lastMail();
        $this->assertSame($mail->subject, $message->getSubject());
        $this->assertStringContainsString('<html lang="'.$locale.'">', $message->getHtmlBody());
        $this->assertStringContainsString(__('mail.reset.action', [], $locale), $message->getHtmlBody());
        $this->assertStringContainsString(__('mail.reset.ignore', [], $locale), $message->getTextBody());
        $this->assertStringContainsString($mail->actionUrl, $message->getTextBody());
        $this->assertStringNotContainsString('Reset Password', $message->getHtmlBody());
        $this->assertStringNotContainsString('If you did not request a password reset', $message->getTextBody());
        $this->assertSame($opposite, app()->getLocale());
        $this->postJson('/api/auth/reset-password', ['email' => $query['email'], 'token' => $token,
            'password' => 'replacement password 456', 'passwordConfirmation' => 'replacement password 456'])->assertOk();
        $this->assertTrue(Hash::check('replacement password 456', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    public function test_reset_expiry_copy_matches_configured_broker_and_expired_token_is_rejected(): void
    {
        config(['auth.passwords.users.expire' => 15]);
        $user = User::factory()->create(['ui_locale' => 'ru']);
        $token = Password::broker()->createToken($user);
        $mail = (new ResetPassword($token))->toMail($user);
        $this->assertSame(15, $mail->viewData['expiresMinutes']);
        $this->assertStringContainsString('15 мин.', view($mail->view['text'], $mail->viewData)->render());
        $password = $user->password;
        $this->travel(16)->minutes();
        $this->postJson('/api/auth/reset-password', ['email' => $user->email, 'token' => $token,
            'password' => 'replacement password 456', 'passwordConfirmation' => 'replacement password 456'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'invalid_reset');
        $this->assertSame($password, $user->fresh()->password);
        $this->travelBack();
    }

    #[DataProvider('locales')]
    public function test_registration_notification_renders_the_language_selected_in_registration(string $locale, string $opposite): void
    {
        app()->setLocale($opposite);
        $this->postJson('/api/auth/register', ['name' => 'Mail fixture', 'email' => 'registration-'.$locale.'@example.test',
            'password' => 'secure password 123', 'passwordConfirmation' => 'secure password 123', 'uiLocale' => $locale])
            ->assertCreated()->assertJsonPath('user.uiLocale', $locale);
        $this->assertSame(__('mail.verify.subject', [], $locale), $this->lastMail()->getSubject());
        $this->assertStringContainsString('<html lang="'.$locale.'">', $this->lastMail()->getHtmlBody());
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('locales')]
    public function test_verification_resend_keeps_the_account_language(string $locale, string $opposite): void
    {
        app()->setLocale($opposite);
        $user = User::factory()->unverified()->create(['ui_locale' => $locale]);
        $this->actingAs($user)->withSession(['auth_password_hash' => $user->password]);
        $this->postJson('/api/auth/verification-notification')->assertAccepted();
        $this->assertSame(__('mail.verify.subject', [], $locale), $this->lastMail()->getSubject());
        $this->assertStringContainsString('<html lang="'.$locale.'">', $this->lastMail()->getHtmlBody());
        $this->assertSame($locale, $user->fresh()->ui_locale);
    }

    #[DataProvider('locales')]
    public function test_forgot_password_sends_the_stored_account_language(string $locale, string $opposite): void
    {
        app()->setLocale($opposite);
        $user = User::factory()->create(['ui_locale' => $locale]);
        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertAccepted();
        $message = $this->lastMail();
        $this->assertSame(__('mail.reset.subject', [], $locale), $message->getSubject());
        $this->assertStringContainsString('<html lang="'.$locale.'">', $message->getHtmlBody());
        $this->assertStringContainsString('/'.$locale.'/reset-password/', $message->getTextBody());
        $this->assertSame($locale, $user->fresh()->ui_locale);
    }

    public function test_unsupported_legacy_locale_renders_russian_instead_of_english(): void
    {
        app()->setLocale('de');
        $user = User::factory()->unverified()->create(['ui_locale' => 'en']);
        $user->sendEmailVerificationNotification();
        $this->assertSame(__('mail.verify.subject', [], 'ru'), $this->lastMail()->getSubject());
        $this->assertStringContainsString('<html lang="ru">', $this->lastMail()->getHtmlBody());
        $reset = (new ResetPassword('dummy-token'))->toMail($user);
        $this->assertSame(__('mail.reset.subject', [], 'ru'), $reset->subject);
        $this->assertStringContainsString('/ru/reset-password/dummy-token', $reset->actionUrl);
        $this->assertSame('de', app()->getLocale());
    }

    private function lastMail(): Email
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());

        return $transport->messages()->last()->getOriginalMessage();
    }
}
