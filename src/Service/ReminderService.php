<?php

namespace App\Service;

use App\DTO\ReminderRequest;
use App\Entity\Reminder;
use App\Enum\ReminderStatus;
use App\Exception\ReminderNotFoundException;
use App\Repository\ReminderRepository;

final class ReminderService
{
    public function __construct(
        private readonly ReminderRepository $reminderRepository,
    ) {
    }

    public function createReminder(ReminderRequest $request, string $userId): Reminder
    {
        $reminder = (new Reminder())
            ->setUserId($userId)
            ->setTitle($request->title)
            ->setNotes($request->notes)
            ->setDueAt($request->dueAt);

        $this->reminderRepository->save($reminder, true);

        return $reminder;
    }

    public function getReminder(string $id, string $userId): Reminder
    {
        $reminder = $this->reminderRepository->findOneByIdAndUserId($id, $userId);

        if ($reminder === null) {
            throw new ReminderNotFoundException($id);
        }

        return $reminder;
    }

    /**
     * @return list<Reminder>
     */
    public function listReminders(string $userId, ?ReminderStatus $status = null): array
    {
        return $this->reminderRepository->findAllByUserId($userId, $status);
    }

    public function updateReminder(
        string $id,
        string $userId,
        ReminderRequest $request,
    ): Reminder {
        $reminder = $this->getReminder($id, $userId);
        $reminder->setTitle($request->title);

        if ($request->notes !== null) {
            $reminder->setNotes($request->notes);
        }

        if ($request->dueAt !== null) {
            $reminder->setDueAt($request->dueAt);
        }

        $this->reminderRepository->save($reminder, true);

        return $reminder;
    }

    public function markAsDone(string $id, string $userId): Reminder
    {
        $reminder = $this->getReminder($id, $userId);
        $reminder->setStatus(ReminderStatus::DONE);
        $this->reminderRepository->save($reminder, true);

        return $reminder;
    }

    public function deleteReminder(string $id, string $userId): void
    {
        $reminder = $this->getReminder($id, $userId);
        $this->reminderRepository->remove($reminder, true);
    }
}
