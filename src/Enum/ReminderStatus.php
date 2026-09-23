<?php

namespace App\Enum;

enum ReminderStatus: string
{
    case ACTIVE = 'active';
    case DONE = 'done';
}
