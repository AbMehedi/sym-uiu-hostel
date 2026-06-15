<?php

namespace App\Enum;

enum TaskPriority: string
{
    case Low    = 'low';
    case Normal = 'normal';
    case High   = 'high';
}
