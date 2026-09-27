<?php

namespace App\Domain\Approvals\Enums;

enum ApprovalDecision: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case InfoRequested = 'info_requested';
    case Commented = 'commented';
    case Resubmitted = 'resubmitted';
    case Cancelled = 'cancelled';
}
