<?php

namespace App\Domain\Audit\Enums;

enum AuthResult: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Blocked = 'blocked';
}
