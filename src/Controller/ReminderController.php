<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ReminderRequest;
use App\Entity\Reminder;
use App\Enum\ReminderStatus;
use App\Service\ReminderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ReminderController
{
    public function __construct(
        private readonly ReminderService $reminderService,
    ) {
    }

    #[Route('/reminders', name: 'reminder_create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] ReminderRequest $dto,
        Request $request,
    ): JsonResponse {
        $reminder = $this->reminderService->createReminder($dto, $this->getUserId($request));

        return new JsonResponse(['reminder' => $this->serializeReminder($reminder)], Response::HTTP_CREATED);
    }

    #[Route('/reminders/{id}', name: 'reminder_get', methods: ['GET'])]
    public function get(string $id, Request $request): JsonResponse
    {
        $reminder = $this->reminderService->getReminder($id, $this->getUserId($request));

        return new JsonResponse(['reminder' => $this->serializeReminder($reminder)]);
    }

    #[Route('/reminders', name: 'reminder_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $statusFilter = null === $status ? null : $this->parseStatus($status);
        $reminders = $this->reminderService->listReminders($this->getUserId($request), $statusFilter);

        return new JsonResponse([
            'items' => array_map(
                fn (Reminder $reminder): array => $this->serializeReminder($reminder),
                $reminders,
            ),
        ]);
    }

    #[Route('/reminders/{id}', name: 'reminder_update', methods: ['PATCH'])]
    public function update(
        string $id,
        #[MapRequestPayload] ReminderRequest $dto,
        Request $request,
    ): JsonResponse {
        $reminder = $this->reminderService->updateReminder($id, $this->getUserId($request), $dto);

        return new JsonResponse(['reminder' => $this->serializeReminder($reminder)]);
    }

    #[Route('/reminders/{id}/done', name: 'reminder_done', methods: ['POST'])]
    public function done(string $id, Request $request): JsonResponse
    {
        $reminder = $this->reminderService->markAsDone($id, $this->getUserId($request));

        return new JsonResponse(['reminder' => $this->serializeReminder($reminder)]);
    }

    #[Route('/reminders/{id}', name: 'reminder_delete', methods: ['DELETE'])]
    public function delete(string $id, Request $request): JsonResponse
    {
        $this->reminderService->deleteReminder($id, $this->getUserId($request));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function getUserId(Request $request): string
    {
        $userId = trim((string) $request->headers->get('X-User-Id', ''));

        if ('' === $userId) {
            throw new BadRequestHttpException('Missing X-User-Id header');
        }

        return $userId;
    }

    private function parseStatus(string $status): ReminderStatus
    {
        try {
            return ReminderStatus::from($status);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Invalid status filter');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReminder(Reminder $reminder): array
    {
        return [
            'id' => $reminder->getId(),
            'userId' => $reminder->getUserId(),
            'title' => $reminder->getTitle(),
            'notes' => $reminder->getNotes(),
            'dueAt' => $reminder->getDueAt()?->format(\DateTimeInterface::ATOM),
            'status' => $reminder->getStatus()->value,
            'createdAt' => $reminder->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $reminder->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
