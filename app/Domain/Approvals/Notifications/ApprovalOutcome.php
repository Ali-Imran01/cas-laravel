<?php

namespace App\Domain\Approvals\Notifications;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Settings\Support\EmailTemplates;
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
        $t = app(EmailTemplates::class)->render("approval_{$this->outcome}", app()->getLocale(), [
            'reference' => $this->request->reference, 'summary' => $this->summary,
        ]);

        $mail = (new MailMessage)->subject($t['subject']);
        foreach ($t['lines'] as $line) {
            $mail->line($line);
        }

        if ($this->comment !== null && $this->comment !== '') {
            $mail->line(__('cas.approvals.mail.comment', ['comment' => $this->comment]));
        }

        return $mail->action(__('cas.approvals.mail.open'), url('/approvals/'.$this->request->id));
    }
}
