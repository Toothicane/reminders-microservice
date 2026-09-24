<?php

namespace App\Tests\Repository;

use App\Entity\Reminder;
use App\Enum\ReminderStatus;
use App\Repository\ReminderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReminderRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ReminderRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(ReminderRepository::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->getConnection()->rollBack();
        }

        parent::tearDown();
    }

    public function testFindsReminderBelongingToUser(): void
    {
        $reminder = $this->createReminder('user-1');
        $this->repository->save($reminder, true);
        $this->entityManager->clear();

        $result = $this->repository->findOneByIdAndUserId($reminder->getId(), 'user-1');

        self::assertNotNull($result);
        self::assertSame($reminder->getId(), $result->getId());
        self::assertSame('user-1', $result->getUserId());
    }

    public function testReturnsNullForUnknownId(): void
    {
        $result = $this->repository->findOneByIdAndUserId(
            '00000000-0000-4000-8000-000000000000',
            'user-1',
        );

        self::assertNull($result);
    }

    public function testReturnsNullForAnotherUsersReminder(): void
    {
        $reminder = $this->createReminder('user-1');
        $this->repository->save($reminder, true);
        $this->entityManager->clear();

        $result = $this->repository->findOneByIdAndUserId($reminder->getId(), 'user-2');

        self::assertNull($result);
    }

    public function testMatchesExactIdAndUserCombination(): void
    {
        $firstReminder = $this->createReminder('user-1', 'First reminder');
        $secondReminder = $this->createReminder('user-1', 'Second reminder');
        $otherUsersReminder = $this->createReminder('user-2', 'Other user reminder');

        $this->repository->save($firstReminder);
        $this->repository->save($secondReminder);
        $this->repository->save($otherUsersReminder, true);
        $this->entityManager->clear();

        $result = $this->repository->findOneByIdAndUserId($secondReminder->getId(), 'user-1');

        self::assertNotNull($result);
        self::assertSame($secondReminder->getId(), $result->getId());
        self::assertNotSame($firstReminder->getId(), $result->getId());
        self::assertNotSame($otherUsersReminder->getId(), $result->getId());
    }

    public function testReturnsAllRecordsForUserWhenStatusIsNull(): void
    {
        $activeReminder = $this->createReminder('user-1', 'Active reminder');
        $activeReminder->setStatus(ReminderStatus::ACTIVE);
        $doneReminder = $this->createReminder('user-1', 'Done reminder');
        $doneReminder->setStatus(ReminderStatus::DONE);
        $otherUsersReminder = $this->createReminder('user-2', 'Other user reminder');

        $this->repository->save($activeReminder);
        $this->repository->save($doneReminder);
        $this->repository->save($otherUsersReminder, true);
        $this->entityManager->clear();

        $result = $this->repository->findAllByUserId('user-1');

        self::assertCount(2, $result);
        self::assertEqualsCanonicalizing(
            [$activeReminder->getId(), $doneReminder->getId()],
            array_map(static fn (Reminder $reminder): string => $reminder->getId(), $result),
        );
    }

    public function testReturnsEmptyArrayWhenUserHasNoReminders(): void
    {
        $result = $this->repository->findAllByUserId('user-without-reminders');

        self::assertSame([], $result);
    }

    public function testReturnsOnlyMatchingStatusForUser(): void
    {
        $activeReminder = $this->createReminder('user-1', 'Active reminder');
        $activeReminder->setStatus(ReminderStatus::ACTIVE);
        $doneReminder = $this->createReminder('user-1', 'Done reminder');
        $doneReminder->setStatus(ReminderStatus::DONE);
        $otherUsersDoneReminder = $this->createReminder('user-2', 'Other user done reminder');
        $otherUsersDoneReminder->setStatus(ReminderStatus::DONE);

        $this->repository->save($activeReminder);
        $this->repository->save($doneReminder);
        $this->repository->save($otherUsersDoneReminder, true);
        $this->entityManager->clear();

        $result = $this->repository->findAllByUserId('user-1', ReminderStatus::DONE);

        self::assertCount(1, $result);
        self::assertSame($doneReminder->getId(), $result[0]->getId());
    }

    public function testSortsDatedRemindersByEarliestDueDate(): void
    {
        $latest = $this->createReminder('user-1', 'Latest', new \DateTimeImmutable('2026-09-26 12:00:00'));
        $earliest = $this->createReminder('user-1', 'Earliest', new \DateTimeImmutable('2026-09-24 12:00:00'));
        $middle = $this->createReminder('user-1', 'Middle', new \DateTimeImmutable('2026-09-25 12:00:00'));

        $this->repository->save($latest);
        $this->repository->save($earliest);
        $this->repository->save($middle, true);
        $this->entityManager->clear();

        $result = $this->repository->findAllByUserId('user-1');

        self::assertSame($earliest->getId(), $result[0]->getId());
        self::assertSame($middle->getId(), $result[1]->getId());
        self::assertSame($latest->getId(), $result[2]->getId());
    }

    public function testPlacesUndatedRemindersAfterDatedReminders(): void
    {
        $undated1 = $this->createReminder('user-1', 'Undated 1');
        $pastDated = $this->createReminder('user-1', 'Past', new \DateTimeImmutable('2020-01-01 00:00:00'));
        $futureDated = $this->createReminder('user-1', 'Future', new \DateTimeImmutable('2030-01-01 00:00:00'));
        $undated2 = $this->createReminder('user-1', 'Undated 2');

        $this->repository->save($undated1);
        $this->repository->save($futureDated);
        $this->repository->save($pastDated);
        $this->repository->save($undated2, true);
        $this->entityManager->clear();
        
        $result = $this->repository->findAllByUserId('user-1');

        self::assertCount(4, $result);
        self::assertSame($pastDated->getId(), $result[0]->getId());
        self::assertSame($futureDated->getId(), $result[1]->getId());
        self::assertNull($result[2]->getDueAt());
        self::assertNull($result[3]->getDueAt());
    }

    public function testPersistsNewReminderWithFlushingEnabled(): void
    {
        $reminder = $this->createReminder('user-1');
        $reminder->setNotes(null)->setDueAt(null);

        $this->repository->save($reminder, true);
        $this->entityManager->clear();

        $result = $this->repository->findOneByIdAndUserId($reminder->getId(), 'user-1');

        self::assertNotNull($result);
        self::assertNull($result->getNotes());
        self::assertNull($result->getDueAt());
    }

    public function testDoesNotFlushWhenFlushingIsDisabled(): void
    {
        $reminder = $this->createReminder('user-1');
        $this->repository->save($reminder);
        $this->entityManager->clear();

        $count = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM reminder WHERE id = :id',
            ['id' => $reminder->getId()],
        );

        self::assertSame('0', (string) $count);
    }

    public function testUpdatesExistingReminderAndChangesUpdatedAt(): void
    {
        $reminder = $this->createReminder('user-1', 'Original title');
        $this->repository->save($reminder, true);
        $originalUpdatedAt = $reminder->getUpdatedAt();
        sleep(1);

        $reminder->setTitle('Updated title')
            ->setNotes('Updated notes')
            ->setDueAt(new \DateTimeImmutable('2026-09-30 12:00:00'));
        $this->repository->save($reminder, true);
        $this->entityManager->clear();

        $result = $this->repository->findOneByIdAndUserId($reminder->getId(), 'user-1');

        self::assertNotNull($result);
        self::assertSame('Updated title', $result->getTitle());
        self::assertSame('Updated notes', $result->getNotes());
        self::assertEquals(new \DateTimeImmutable('2026-09-30 12:00:00'), $result->getDueAt());
        self::assertGreaterThan($originalUpdatedAt, $result->getUpdatedAt());
    }

    public function testDeletesReminderWithFlushingEnabled(): void
    {
        $reminder = $this->createReminder('user-1');
        $this->repository->save($reminder, true);

        $this->repository->remove($reminder, true);
        $this->entityManager->clear();

        self::assertNull($this->repository->findOneByIdAndUserId($reminder->getId(), 'user-1'));
    }

    private function createReminder(
        string $userId,
        string $title = 'Reminder',
        ?\DateTimeImmutable $dueAt = null,
    ): Reminder {
        return (new Reminder())
            ->setUserId($userId)
            ->setTitle($title)
            ->setDueAt($dueAt);
    }
}
