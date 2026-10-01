<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class ResetAccountPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return self::message($notifiable, $this->resetUrl($notifiable));
    }

    /** The password broker's real token and existing reset URL remain untouched. */
    public static function message(User $user, string $url): MailMessage
    {
        $locale = $user->preferredLocale();

        return (new MailMessage)->subject(__('mail.reset.subject', [], $locale))->action(__('mail.reset.action', [], $locale), $url)
            ->view(['html' => 'emails.account-html', 'text' => 'emails.account-text'], [
                'kind' => 'reset', 'locale' => $locale, 'recipientName' => $user->name, 'actionUrl' => $url,
                'expiresMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ]);
    }
}
