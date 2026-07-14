<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded password-reset mail. The token comes from Laravel's password broker;
 * the link lands on the SPA's /reset-password page, which posts the token +
 * new password back to /api/password/reset.
 */
class ResetPassword extends BaseResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your password - SaunaSpeak')
            ->view('emails.branded', [
                'vaino' => 'vaino-think.png',
                'title' => 'Forgot your password? No hätä!',
                'preheader' => 'Tap the button to pick a new password - the link works for 60 minutes.',
                'intro' => [
                    'Someone (hopefully you) asked to reset the password for this account. Tap the button and pick a new one - your streak and progress are exactly where you left them.',
                ],
                'actionUrl' => $url,
                'actionText' => 'Choose a new password',
                'outro' => [
                    'The link works for 60 minutes.',
                ],
                'footerNote' => 'Didn\'t ask for this? Ignore the email - your password stays unchanged.',
            ]);
    }

    protected function resetUrl($notifiable): string
    {
        $base = rtrim(config('services.stripe.frontend_url') ?: config('app.url'), '/');

        return $base.'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());
    }
}
