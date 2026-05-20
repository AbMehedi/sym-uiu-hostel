<?php

namespace App\Enum;

enum ReportType: string
{
    case RoomAllocation = 'room_allocation';
    case ComplaintLog = 'complaint_log';
    case CostReport = 'cost_report';
    case OccupancyDashboard = 'occupancy_dashboard';
}
