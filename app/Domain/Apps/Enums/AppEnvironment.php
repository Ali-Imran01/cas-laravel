<?php

namespace App\Domain\Apps\Enums;

enum AppEnvironment: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Sandbox = 'sandbox';
}
