<?php

namespace App\Domain\Identity\Notifications;

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
        return (new MailMessage)
            ->subject(__('cas.mail.otp_subject'))
            ->line(__('cas.mail.otp_line', ['code' => $this->code, 'minutes' => config('cas.auth.mfa_email_otp_minutes')]))
            ->line(__('cas.mail.otp_ignore'));
    }
}
