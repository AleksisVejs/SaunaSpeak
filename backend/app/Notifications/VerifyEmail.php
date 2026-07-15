<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * SaunaSpeak-flavored email verification. The branded HTML shell lives in
 * resources/views/emails/branded.blade.php.
 *
 * The link is signed over the RELATIVE URL (path + query) and validated with
 * the signed:relative middleware. cPanel hosting sits behind redirects/proxies
 * that can change the scheme or host between mail-out and click (http→https,
 * www→apex), which breaks absolute signatures with a 403 "invalid signature".
 */
class VerifyEmail extends BaseVerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        $relative = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
            absolute: false
        );

        return rtrim(config('app.url'), '/').$relative;
    }

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
                    'The link works for 24 hours. If it expires, you can request a new one from your dashboard.',
                ],
                'footerNote' => 'Didn\'t create a SaunaSpeak account? Ignore this email - nothing else will be sent.',
            ]);
    }
}
