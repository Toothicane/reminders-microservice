<?php

declare(strict_types=1);

namespace App\Enum;

enum ReminderStatus: string
{
    case ACTIVE = 'active';
    case DONE = 'done';
}
