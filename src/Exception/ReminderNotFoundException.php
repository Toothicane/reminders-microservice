<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReminderNotFoundException extends NotFoundHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf("Reminder with ID '%s' was not found.", $id));
    }
}
