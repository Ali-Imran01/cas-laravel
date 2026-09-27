<?php

namespace App\Domain\Approvals\Notifications;

use App\Domain\Approvals\Models\ApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the people who can decide the request at its current level. */
class ApprovalNeeded extends Notification
{
    public function __construct(public readonly ApprovalRequest $request, public readonly string $summary, public readonly bool $overdue = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__($this->overdue ? 'cas.approvals.mail.overdue_subject' : 'cas.approvals.mail.needed_subject', ['reference' => $this->request->reference]))
            ->line($this->summary)
            ->line(__('cas.approvals.mail.needed_line', ['name' => $this->request->requester->name]))
            ->action(__('cas.approvals.mail.open'), url('/approvals/'.$this->request->id));
    }
}
