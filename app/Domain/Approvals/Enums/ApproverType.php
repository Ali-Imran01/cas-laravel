<?php

namespace App\Domain\Approvals\Enums;

enum ApproverType: string
{
    case Role = 'role';
    case User = 'user';
    case UnitHead = 'unit_head';
    case DivisionHead = 'division_head';
}
