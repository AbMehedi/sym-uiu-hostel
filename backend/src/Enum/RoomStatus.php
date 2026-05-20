<?php

namespace App\Enum;

enum RoomStatus: string
{
    case Available = 'available';
    case Full = 'full';
    case UnderMaintenance = 'under_maintenance';
}
