<?php

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

final class ReminderRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 1, max: 140)]
        public string $title,
        public ?string $notes = null,
        #[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::RFC3339])]
        public ?\DateTimeImmutable $dueAt = null,
    ) {
    }
}
