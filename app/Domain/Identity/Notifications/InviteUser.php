<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Settings\Support\EmailTemplates;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteUser extends Notification
{
    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invite.accept', ['token' => $this->token, 'email' => $notifiable->email]);
        $t = app(EmailTemplates::class)->render('invite', app()->getLocale(), ['staff_id' => $notifiable->staff_id, 'days' => 3]);

        $mail = (new MailMessage)->subject($t['subject']);
        foreach ($t['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action(__('cas.mail.invite_action'), $url);
    }
}
