<?php

namespace App\Domain\Identity\Notifications;

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

        return (new MailMessage)
            ->subject(__('cas.mail.invite_subject'))
            ->line(__('cas.mail.invite_line', ['staff_id' => $notifiable->staff_id]))
            ->action(__('cas.mail.invite_action'), $url)
            ->line(__('cas.mail.invite_expiry', ['days' => 3]));
    }
}
