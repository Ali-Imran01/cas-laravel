<?php

namespace App\Domain\Approvals\Notifications;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Settings\Support\EmailTemplates;
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
        $t = app(EmailTemplates::class)->render($this->overdue ? 'approval_overdue' : 'approval_needed', app()->getLocale(), [
            'reference' => $this->request->reference, 'summary' => $this->summary, 'requester' => $this->request->requester->name,
        ]);

        $mail = (new MailMessage)->subject($t['subject']);
        foreach ($t['lines'] as $line) {
            $mail->line($line);
        }

        return $mail->action(__('cas.approvals.mail.open'), url('/approvals/'.$this->request->id));
    }
}
