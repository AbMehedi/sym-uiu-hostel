<?php

namespace App\Enum;

enum AssignmentStatus: string
{
    case Active = 'active';
    case Vacated = 'vacated';
}
