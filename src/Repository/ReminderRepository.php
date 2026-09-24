<?php

namespace App\Repository;

use App\Entity\Reminder;
use App\Enum\ReminderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reminder::class);
    }

    public function findOneByIdAndUserId(string $id, string $userId): ?Reminder
    {
        return $this->createQueryBuilder('reminder')
            ->andWhere('reminder.id = :id')
            ->andWhere('reminder.userId = :userId')
            ->setParameter('id', $id)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Reminder>
     */
    public function findAllByUserId(string $userId, ?ReminderStatus $status = null): array
    {
        $queryBuilder = $this->createQueryBuilder('reminder')
            ->andWhere('reminder.userId = :userId')
            ->setParameter('userId', $userId)
            ->addOrderBy('CASE WHEN reminder.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('reminder.dueAt', 'ASC');

        if ($status !== null) {
            $queryBuilder
                ->andWhere('reminder.status = :status')
                ->setParameter('status', $status);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function save(Reminder $reminder, bool $flush = false): void
    {
        $this->getEntityManager()->persist($reminder);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reminder $reminder, bool $flush = false): void
    {
        $this->getEntityManager()->remove($reminder);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
