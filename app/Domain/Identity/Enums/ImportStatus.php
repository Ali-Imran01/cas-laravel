<?php

namespace App\Domain\Identity\Enums;

enum ImportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
