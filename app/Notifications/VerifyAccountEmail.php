<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

final class VerifyAccountEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return self::message($notifiable, $this->verificationUrl($notifiable));
    }

    /** The framework supplies its original expiring signed URL to this presentation callback. */
    public static function message(User $user, string $url): MailMessage
    {
        $locale = $user->preferredLocale();

        return (new MailMessage)->subject(__('mail.verify.subject', [], $locale))->action(__('mail.verify.action', [], $locale), $url)
            ->view(['html' => 'emails.account-html', 'text' => 'emails.account-text'], [
                'kind' => 'verify', 'locale' => $locale, 'recipientName' => $user->name, 'actionUrl' => $url,
                'expiresMinutes' => (int) config('auth.verification.expire', 60),
            ]);
    }
}
