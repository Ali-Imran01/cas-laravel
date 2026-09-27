<?php

namespace App\Domain\Audit\Enums;

enum LoginMethod: string
{
    case Password = 'password';
    case Sso = 'sso';
    case Mfa = 'mfa';
}
