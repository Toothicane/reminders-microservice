<?php

namespace App\Tests\Service;

use App\DTO\ReminderRequest;
use App\Entity\Reminder;
use App\Enum\ReminderStatus;
use App\Exception\ReminderNotFoundException;
use App\Repository\ReminderRepository;
use App\Service\ReminderService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReminderServiceTest extends TestCase
{
    private ReminderRepository&MockObject $repository;
    private ReminderService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReminderRepository::class);
        $this->service = new ReminderService($this->repository);
    }

    public function testCreatesAndSavesReminder(): void
    {
        $dueAt = new \DateTimeImmutable('2030-01-01 12:00:00');
        $request = new ReminderRequest('Buy milk', 'From the local shop', $dueAt);
        $userId = 'user-1';
        $savedReminder = null;

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(
                self::callback(function (Reminder $reminder) use (&$savedReminder, $userId, $request): bool {
                    $savedReminder = $reminder;

                    return $reminder->getUserId() === $userId
                        && $reminder->getTitle() === $request->title
                        && $reminder->getNotes() === $request->notes
                        && $reminder->getDueAt() == $request->dueAt
                        && $reminder->getStatus() === ReminderStatus::ACTIVE;
                }),
                true,
            );

        $result = $this->service->createReminder($request, $userId);

        self::assertSame($savedReminder, $result);
        self::assertNotEmpty($result->getId());
    }

    public function testCreatesReminderWithNullableFields(): void
    {
        $request = new ReminderRequest('Buy milk');
        $savedReminder = null;

        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with(
                self::callback(function (Reminder $reminder) use (&$savedReminder): bool {
                    $savedReminder = $reminder;

                    return $reminder->getNotes() === null && $reminder->getDueAt() === null;
                }),
                true,
            );

        $result = $this->service->createReminder($request, 'user-1');

        self::assertSame($savedReminder, $result);
    }

    public function testReturnsUserOwnedReminder(): void
    {
        $reminder = $this->newReminder('user-1');

        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('reminder-1', 'user-1')
            ->willReturn($reminder);

        self::assertSame($reminder, $this->service->getReminder('reminder-1', 'user-1'));
    }

    public function testThrowsWhenGettingUnknownReminder(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('missing-id', 'user-1')
            ->willReturn(null);
        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::never())->method('remove');

        $exception = $this->captureNotFoundException(
            fn (): Reminder => $this->service->getReminder('missing-id', 'user-1'),
        );

        $this->assertNotFoundExceptionDetails($exception, 'missing-id');
    }

    public function testListsAllRemindersWithoutStatusFilter(): void
    {
        $reminders = [$this->newReminder('user-1'), $this->newReminder('user-1')];

        $this->repository
            ->expects(self::once())
            ->method('findAllByUserId')
            ->with('user-1', null)
            ->willReturn($reminders);

        self::assertSame($reminders, $this->service->listReminders('user-1'));
    }

    public function testListsRemindersWithStatusFilter(): void
    {
        $reminders = [$this->newReminder('user-1')];

        $this->repository
            ->expects(self::once())
            ->method('findAllByUserId')
            ->with('user-1', ReminderStatus::ACTIVE)
            ->willReturn($reminders);

        self::assertSame(
            $reminders,
            $this->service->listReminders('user-1', ReminderStatus::ACTIVE),
        );
    }

    public function testUpdatesAllProvidedFields(): void
    {
        $reminder = $this->newReminder('user-1', 'Original title', 'Original notes');
        $dueAt = new \DateTimeImmutable('2030-01-01 12:00:00');
        $request = new ReminderRequest('Updated title', 'Updated notes', $dueAt);

        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('reminder-1', 'user-1')
            ->willReturn($reminder);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($reminder, true);

        $result = $this->service->updateReminder('reminder-1', 'user-1', $request);

        self::assertSame($reminder, $result);
        self::assertSame('Updated title', $result->getTitle());
        self::assertSame('Updated notes', $result->getNotes());
        self::assertEquals($dueAt, $result->getDueAt());
    }

    public function testPreservesExistingNullableFieldsWhenRequestFieldsAreNull(): void
    {
        $oldDueAt = new \DateTimeImmutable('2030-01-01 12:00:00');
        $reminder = $this->newReminder('user-1', 'Original title', 'Original notes', $oldDueAt);
        $request = new ReminderRequest('Updated title', null, null);

        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('reminder-1', 'user-1')
            ->willReturn($reminder);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($reminder, true);

        $result = $this->service->updateReminder('reminder-1', 'user-1', $request);

        self::assertSame('Updated title', $result->getTitle());
        self::assertSame('Original notes', $result->getNotes());
        self::assertEquals($oldDueAt, $result->getDueAt());
    }

    public function testThrowsWhenUpdatingUnknownReminder(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('missing-id', 'user-1')
            ->willReturn(null);
        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::never())->method('remove');

        $exception = $this->captureNotFoundException(
            fn (): Reminder => $this->service->updateReminder(
                'missing-id',
                'user-1',
                new ReminderRequest('Updated title'),
            ),
        );

        $this->assertNotFoundExceptionDetails($exception, 'missing-id');
    }

    public function testMarksReminderAsDone(): void
    {
        $reminder = $this->newReminder('user-1');

        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('reminder-1', 'user-1')
            ->willReturn($reminder);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($reminder, true);

        $result = $this->service->markAsDone('reminder-1', 'user-1');

        self::assertSame($reminder, $result);
        self::assertSame(ReminderStatus::DONE, $result->getStatus());
    }

    public function testThrowsWhenMarkingUnknownReminderAsDone(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('missing-id', 'user-1')
            ->willReturn(null);
        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::never())->method('remove');

        $exception = $this->captureNotFoundException(
            fn (): Reminder => $this->service->markAsDone('missing-id', 'user-1'),
        );

        $this->assertNotFoundExceptionDetails($exception, 'missing-id');
    }

    public function testMarksAlreadyDoneReminderAsDone(): void
    {
        $reminder = $this->newReminder('user-1');
        $reminder->setStatus(ReminderStatus::DONE);

        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('reminder-1', 'user-1')
            ->willReturn($reminder);
        $this->repository
            ->expects(self::once())
            ->method('save')
            ->with($reminder, true);

        $result = $this->service->markAsDone('reminder-1', 'user-1');

        self::assertSame(ReminderStatus::DONE, $result->getStatus());
    }

    public function testDeletesUserOwnedReminder(): void
    {
        $reminder = $this->newReminder('user-1');

        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('reminder-1', 'user-1')
            ->willReturn($reminder);
        $this->repository
            ->expects(self::once())
            ->method('remove')
            ->with($reminder, true);

        $this->service->deleteReminder('reminder-1', 'user-1');
    }

    public function testThrowsWhenDeletingUnknownReminder(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('missing-id', 'user-1')
            ->willReturn(null);
        $this->repository->expects(self::never())->method('save');
        $this->repository->expects(self::never())->method('remove');

        $exception = $this->captureNotFoundException(
            function (): void {
                $this->service->deleteReminder('missing-id', 'user-1');
            },
        );

        $this->assertNotFoundExceptionDetails($exception, 'missing-id');
    }

    private function newReminder(
        string $userId,
        string $title = 'Reminder',
        ?string $notes = null,
        ?\DateTimeImmutable $dueAt = null,
    ): Reminder {
        return (new Reminder())
            ->setUserId($userId)
            ->setTitle($title)
            ->setNotes($notes)
            ->setDueAt($dueAt);
    }

    /**
     * @param callable(): mixed $operation
     */
    private function captureNotFoundException(callable $operation): ReminderNotFoundException
    {
        try {
            $operation();
        } catch (ReminderNotFoundException $exception) {
            return $exception;
        }

        self::fail('Expected ReminderNotFoundException was not thrown.');
    }

    private function assertNotFoundExceptionDetails(
        ReminderNotFoundException $exception,
        string $id,
    ): void {
        self::assertSame("Reminder with ID '{$id}' was not found.", $exception->getMessage());
        self::assertSame(404, $exception->getStatusCode());
    }
}
