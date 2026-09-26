<?php

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Locked = 'locked';
    case Inactive = 'inactive';
}
