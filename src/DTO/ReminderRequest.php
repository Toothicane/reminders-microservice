<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ReminderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 140)]
        public string $title,
        public ?string $notes = null,
        public ?\DateTimeImmutable $dueAt = null,
    ) {
    }
}
