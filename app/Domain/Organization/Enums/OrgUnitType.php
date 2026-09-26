<?php

namespace App\Domain\Organization\Enums;

enum OrgUnitType: string
{
    case Headquarters = 'headquarters';
    case Division = 'division';
    case Unit = 'unit';
    case StateOffice = 'state_office';
}
