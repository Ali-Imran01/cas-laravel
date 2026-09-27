<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Settings\Support\EmailTemplates;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MfaCode extends Notification
{
    public function __construct(public readonly string $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = app(EmailTemplates::class)->render('mfa_code', app()->getLocale(), [
            'code' => $this->code, 'minutes' => config('cas.auth.mfa_email_otp_minutes'),
        ]);

        $mail = (new MailMessage)->subject($t['subject']);
        foreach ($t['lines'] as $line) {
            $mail->line($line);
        }

        return $mail;
    }
}
