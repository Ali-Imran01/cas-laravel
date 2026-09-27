<?php

namespace App\Domain\Approvals\Notifications;

use App\Domain\Approvals\Models\ApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the requester when something happens to their request: approved, rejected, more information wanted. */
class ApprovalOutcome extends Notification
{
    /** @param 'approved'|'rejected'|'info_requested' $outcome */
    public function __construct(public readonly ApprovalRequest $request, public readonly string $outcome, public readonly string $summary, public readonly ?string $comment = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__("cas.approvals.mail.{$this->outcome}_subject", ['reference' => $this->request->reference]))
            ->line($this->summary);

        if ($this->comment !== null && $this->comment !== '') {
            $mail->line(__('cas.approvals.mail.comment', ['comment' => $this->comment]));
        }

        return $mail->action(__('cas.approvals.mail.open'), url('/approvals/'.$this->request->id));
    }
}
