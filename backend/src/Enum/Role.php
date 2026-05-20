<?php

namespace App\Enum;

enum Role: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Student = 'student';
}
