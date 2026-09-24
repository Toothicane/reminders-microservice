<?php

namespace App\Entity;

use App\Enum\ReminderStatus;
use App\Repository\ReminderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReminderRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Reminder
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $userId;

    #[ORM\Column(type: 'string', length: 140)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: 'string', enumType: ReminderStatus::class)]
    private ReminderStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->status = ReminderStatus::ACTIVE;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = $this->currentTimestamp();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = $this->currentTimestamp();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): self
    {
        $this->dueAt = $dueAt === null ? null : $this->normalizeTimestamp($dueAt);

        return $this;
    }

    public function getStatus(): ReminderStatus
    {
        return $this->status;
    }

    public function setStatus(ReminderStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function currentTimestamp(): \DateTimeImmutable
    {
        return $this->normalizeTimestamp(new \DateTimeImmutable());
    }

    private function normalizeTimestamp(\DateTimeImmutable $dateTime): \DateTimeImmutable
    {
        return $dateTime->setTime(
            (int) $dateTime->format('H'),
            (int) $dateTime->format('i'),
            (int) $dateTime->format('s'),
        );
    }
}
