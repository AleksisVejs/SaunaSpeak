<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * SaunaSpeak-flavored email verification. The signed URL comes from the base
 * class (route "verification.verify", 60-minute expiry per auth.verification);
 * the branded HTML shell lives in resources/views/emails/branded.blade.php.
 */
class VerifyEmail extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email - SaunaSpeak')
            ->view('emails.branded', [
                'vaino' => 'vaino-wave.png',
                'title' => 'Moi! Väinö saved you a seat',
                'preheader' => 'One tap to confirm your email and the bench is all yours.',
                'intro' => [
                    'One quick tap to confirm this is really your email address, and the bench is all yours.',
                ],
                'actionUrl' => $url,
                'actionText' => 'Confirm email',
                'outro' => [
                    'The link works for 60 minutes. If it expires, you can request a new one from your dashboard.',
                ],
                'footerNote' => 'Didn\'t create a SaunaSpeak account? Ignore this email - nothing else will be sent.',
            ]);
    }
}
