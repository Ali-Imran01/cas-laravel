<?php

namespace App\Domain\Approvals\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case InfoRequested = 'info_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Still waiting on someone: the requester (info requested) or an approver (pending). */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::InfoRequested;
    }
}
