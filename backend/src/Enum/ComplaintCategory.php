<?php

namespace App\Enum;

enum ComplaintCategory: string
{
    case Plumbing = 'plumbing';
    case Electricity = 'electricity';
    case Cleaning = 'cleaning';
    case Noise = 'noise';
    case Other = 'other';
}
