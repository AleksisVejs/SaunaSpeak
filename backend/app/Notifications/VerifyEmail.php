<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * SaunaSpeak-flavored email verification. The signed URL comes from the base
 * class (route "verification.verify", 60-minute expiry per auth.verification);
 * only the wording is ours.
 */
class VerifyEmail extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email - SaunaSpeak')
            ->greeting('Moi!')
            ->line('One quick tap to confirm this is really your email address, and the bench is all yours.')
            ->action('Confirm email', $url)
            ->line('The link works for 60 minutes. If you didn\'t create a SaunaSpeak account, you can ignore this - nothing else will be sent.')
            ->salutation('Kiitos, and see you in the sauna! 🧖');
    }
}
