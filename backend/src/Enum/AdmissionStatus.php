<?php

namespace App\Enum;

enum AdmissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Vacated = 'vacated';
}
