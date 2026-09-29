<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Reminder;
use App\Enum\ReminderStatus;
use App\Repository\ReminderRepository;
use App\Service\ReminderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ReminderControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $client = static::createClient();
        $client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCreatesReminderWithValidPayload(): void
    {
        $client = static::getClient();
        $client->request(
            'POST',
            '/reminders',
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => 'Book an appointment', 'notes' => 'Call in the morning']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->getJsonResponse();

        self::assertSame(['reminder'], array_keys($body));
        self::assertSame('user-a', $body['reminder']['userId']);
        self::assertSame('Book an appointment', $body['reminder']['title']);
        self::assertSame('active', $body['reminder']['status']);
        self::assertNotEmpty($body['reminder']['id']);
    }

    public function testCreatesReminderWhenOptionalFieldsAreOmitted(): void
    {
        $client = static::getClient();
        $client->request(
            'POST',
            '/reminders',
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => 'A title']),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $reminder = $this->getJsonResponse()['reminder'];
        self::assertNull($reminder['notes']);
        self::assertNull($reminder['dueAt']);
    }

    public function testRejectsBlankTitle(): void
    {
        $client = static::getClient();
        $client->request(
            'POST',
            '/reminders',
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => '']),
        );

        $this->assertErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRejectsTitleLongerThan140Characters(): void
    {
        $client = static::getClient();
        $client->request(
            'POST',
            '/reminders',
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => str_repeat('x', 141)]),
        );

        $this->assertErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRejectsInvalidDueDate(): void
    {
        $client = static::getClient();
        $client->request(
            'POST',
            '/reminders',
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => 'A title', 'dueAt' => 'not-a-date']),
        );

        $this->assertErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRejectsMalformedJson(): void
    {
        $client = static::getClient();
        $client->request('POST', '/reminders', [], [], $this->jsonServer('user-a'), '{"title":');

        $this->assertErrorResponse(Response::HTTP_BAD_REQUEST);
    }

    public function testGetsReminderOwnedByRequestingUser(): void
    {
        $reminder = $this->persistReminder('user-a', 'Owned reminder');
        $client = static::getClient();
        $client->request('GET', '/reminders/'.$reminder->getId(), [], [], $this->jsonServer('user-a'));

        self::assertResponseIsSuccessful();
        $body = $this->getJsonResponse();
        self::assertSame(['reminder'], array_keys($body));
        self::assertSame($reminder->getId(), $body['reminder']['id']);
        self::assertSame('user-a', $body['reminder']['userId']);
        self::assertSame('Owned reminder', $body['reminder']['title']);
        self::assertSame('active', $body['reminder']['status']);
    }

    public function testReturns404ForMissingReminder(): void
    {
        $client = static::getClient();
        $client->request(
            'GET',
            '/reminders/00000000-0000-4000-8000-000000000000',
            [],
            [],
            $this->jsonServer('user-a'),
        );

        $this->assertErrorResponse(
            Response::HTTP_NOT_FOUND,
            "Reminder with ID '00000000-0000-4000-8000-000000000000' was not found.",
        );
    }

    public function testReturns404ForReminderOwnedByAnotherUser(): void
    {
        $reminder = $this->persistReminder('user-b', 'Private reminder');
        $client = static::getClient();
        $client->request('GET', '/reminders/'.$reminder->getId(), [], [], $this->jsonServer('user-a'));

        $this->assertErrorResponse(
            Response::HTTP_NOT_FOUND,
            "Reminder with ID '{$reminder->getId()}' was not found.",
        );
    }

    public function testListsOnlyRemindersOwnedByRequestingUser(): void
    {
        $ownedOne = $this->persistReminder('user-a', 'First');
        $ownedTwo = $this->persistReminder('user-a', 'Second');
        $this->persistReminder('user-b', 'Not visible');

        $client = static::getClient();
        $client->request('GET', '/reminders', [], [], $this->jsonServer('user-a'));

        self::assertResponseIsSuccessful();
        $body = $this->getJsonResponse();
        self::assertSame(['items'], array_keys($body));
        self::assertEqualsCanonicalizing(
            [$ownedOne->getId(), $ownedTwo->getId()],
            array_column($body['items'], 'id'),
        );
        self::assertSame(['user-a'], array_values(array_unique(array_column($body['items'], 'userId'))));
    }

    public function testFiltersListByActiveAndDoneStatus(): void
    {
        $activeOwned = $this->persistReminder('user-a', 'Active', ReminderStatus::ACTIVE);
        $doneOwned = $this->persistReminder('user-a', 'Done', ReminderStatus::DONE);
        $this->persistReminder('user-b', 'Other user done', ReminderStatus::DONE);

        $client = static::getClient();
        $client->request('GET', '/reminders?status=active', [], [], $this->jsonServer('user-a'));
        self::assertResponseIsSuccessful();
        self::assertSame([$activeOwned->getId()], array_column($this->getJsonResponse()['items'], 'id'));

        $client->request('GET', '/reminders?status=done', [], [], $this->jsonServer('user-a'));
        self::assertResponseIsSuccessful();
        self::assertSame([$doneOwned->getId()], array_column($this->getJsonResponse()['items'], 'id'));
    }

    public function testReturnsEmptyListWhenNoRemindersExist(): void
    {
        $client = static::getClient();
        $client->request('GET', '/reminders', [], [], $this->jsonServer('user-without-reminders'));

        self::assertResponseIsSuccessful();
        self::assertSame(['items' => []], $this->getJsonResponse());
    }

    public function testRejectsUnknownStatusFilter(): void
    {
        $client = static::getClient();
        $client->request('GET', '/reminders?status=invalid_status', [], [], $this->jsonServer('user-a'));

        $this->assertErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid status filter');
    }

    public function testListPreservesDueDateOrderingWithUndatedRemindersLast(): void
    {
        $undated = $this->persistReminder('user-a', 'Undated');
        $future = $this->persistReminder('user-a', 'Future', dueAt: new \DateTimeImmutable('2030-01-01'));
        $past = $this->persistReminder('user-a', 'Past', dueAt: new \DateTimeImmutable('2020-01-01'));

        $client = static::getClient();
        $client->request('GET', '/reminders', [], [], $this->jsonServer('user-a'));

        self::assertResponseIsSuccessful();
        self::assertSame(
            [$past->getId(), $future->getId(), $undated->getId()],
            array_column($this->getJsonResponse()['items'], 'id'),
        );
    }

    public function testUpdatesOwnedReminder(): void
    {
        $reminder = $this->persistReminder('user-a', 'Original', ReminderStatus::ACTIVE, null, 'Original note');
        $client = static::getClient();
        $client->request(
            'PATCH',
            '/reminders/'.$reminder->getId(),
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode([
                'title' => 'Updated title',
                'notes' => 'Updated note',
                'dueAt' => '2030-04-05T12:30:00+00:00',
            ]),
        );

        self::assertResponseIsSuccessful();
        $updated = $this->getJsonResponse()['reminder'];
        self::assertSame('Updated title', $updated['title']);
        self::assertSame('Updated note', $updated['notes']);
        self::assertSame('2030-04-05T12:30:00+00:00', $updated['dueAt']);
    }

    public function testRejectsInvalidUpdatePayload(): void
    {
        $reminder = $this->persistReminder('user-a', 'Original');
        $client = static::getClient();
        $client->request(
            'PATCH',
            '/reminders/'.$reminder->getId(),
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => str_repeat('x', 141)]),
        );

        $this->assertErrorResponse(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testReturns404ForUpdateOfMissingOrUnownedReminder(): void
    {
        $client = static::getClient();
        $missingId = '00000000-0000-4000-8000-000000000001';
        $client->request(
            'PATCH',
            '/reminders/'.$missingId,
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => 'Updated']),
        );
        $this->assertErrorResponse(Response::HTTP_NOT_FOUND, "Reminder with ID '{$missingId}' was not found.");

        $ownedByOther = $this->persistReminder('user-b', 'Other user reminder');
        $client->request(
            'PATCH',
            '/reminders/'.$ownedByOther->getId(),
            [],
            [],
            $this->jsonServer('user-a'),
            json_encode(['title' => 'Updated']),
        );
        $this->assertErrorResponse(
            Response::HTTP_NOT_FOUND,
            "Reminder with ID '{$ownedByOther->getId()}' was not found.",
        );
    }

    public function testMarksActiveReminderAsDone(): void
    {
        $reminder = $this->persistReminder('user-a', 'Active reminder');
        $client = static::getClient();
        $client->request('POST', '/reminders/'.$reminder->getId().'/done', [], [], $this->jsonServer('user-a'));

        self::assertResponseIsSuccessful();
        self::assertSame('done', $this->getJsonResponse()['reminder']['status']);
    }

    public function testMarkingAlreadyDoneReminderIsIdempotent(): void
    {
        $reminder = $this->persistReminder('user-a', 'Done reminder', ReminderStatus::DONE);
        $client = static::getClient();
        $client->request('POST', '/reminders/'.$reminder->getId().'/done', [], [], $this->jsonServer('user-a'));

        self::assertResponseIsSuccessful();
        self::assertSame('done', $this->getJsonResponse()['reminder']['status']);
    }

    public function testReturns404WhenMarkingMissingOrUnownedReminderDone(): void
    {
        $client = static::getClient();
        $missingId = '00000000-0000-4000-8000-000000000002';
        $client->request('POST', '/reminders/'.$missingId.'/done', [], [], $this->jsonServer('user-a'));
        $this->assertErrorResponse(Response::HTTP_NOT_FOUND, "Reminder with ID '{$missingId}' was not found.");

        $ownedByOther = $this->persistReminder('user-b', 'Other user reminder');
        $client->request(
            'POST',
            '/reminders/'.$ownedByOther->getId().'/done',
            [],
            [],
            $this->jsonServer('user-a'),
        );
        $this->assertErrorResponse(
            Response::HTTP_NOT_FOUND,
            "Reminder with ID '{$ownedByOther->getId()}' was not found.",
        );
    }

    public function testDeletesOwnedReminder(): void
    {
        $reminder = $this->persistReminder('user-a', 'Delete me');
        $client = static::getClient();
        $client->request('DELETE', '/reminders/'.$reminder->getId(), [], [], $this->jsonServer('user-a'));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('', $client->getResponse()->getContent());
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Reminder::class)->find($reminder->getId()));
    }

    public function testReturns404WhenDeletingMissingOrUnownedReminder(): void
    {
        $client = static::getClient();
        $missingId = '00000000-0000-4000-8000-000000000003';
        $client->request('DELETE', '/reminders/'.$missingId, [], [], $this->jsonServer('user-a'));
        $this->assertErrorResponse(Response::HTTP_NOT_FOUND, "Reminder with ID '{$missingId}' was not found.");

        $ownedByOther = $this->persistReminder('user-b', 'Other user reminder');
        $client->request(
            'DELETE',
            '/reminders/'.$ownedByOther->getId(),
            [],
            [],
            $this->jsonServer('user-a'),
        );
        $this->assertErrorResponse(
            Response::HTTP_NOT_FOUND,
            "Reminder with ID '{$ownedByOther->getId()}' was not found.",
        );
    }

    public function testRejectsMissingUserHeader(): void
    {
        $client = static::getClient();
        $client->request('GET', '/reminders');

        $this->assertErrorResponse(Response::HTTP_BAD_REQUEST, 'Missing X-User-Id header');
    }

    public function testNormalizesUnhandledServiceException(): void
    {
        $repository = $this->createMock(ReminderRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneByIdAndUserId')
            ->with('reminder-error', 'user-a')
            ->willThrowException(new \RuntimeException('Sensitive internal detail'));
        static::getContainer()->set(ReminderService::class, new ReminderService($repository));

        $client = static::getClient();
        $client->request('GET', '/reminders/reminder-error', [], [], $this->jsonServer('user-a'));

        $this->assertErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR, 'Internal Server Error');
    }

    /**
     * @return array<string, string>
     */
    private function jsonServer(string $userId): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-User-Id' => $userId,
        ];
    }

    private function persistReminder(
        string $userId,
        string $title,
        ReminderStatus $status = ReminderStatus::ACTIVE,
        ?\DateTimeImmutable $dueAt = null,
        ?string $notes = null,
    ): Reminder {
        $reminder = (new Reminder())
            ->setUserId($userId)
            ->setTitle($title)
            ->setStatus($status)
            ->setDueAt($dueAt)
            ->setNotes($notes);

        $this->entityManager->persist($reminder);
        $this->entityManager->flush();

        return $reminder;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonResponse(): array
    {
        $client = static::getClient();
        self::assertNotNull($client);
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function assertErrorResponse(int $statusCode, ?string $message = null): void
    {
        self::assertResponseStatusCodeSame($statusCode);
        $body = $this->getJsonResponse();

        self::assertSame(['error'], array_keys($body));
        self::assertIsArray($body['error']);
        self::assertSame(['code', 'message'], array_keys($body['error']));
        self::assertSame($statusCode, $body['error']['code']);
        self::assertIsString($body['error']['message']);
        self::assertNotSame('', $body['error']['message']);

        if (null !== $message) {
            self::assertSame($message, $body['error']['message']);
        }
    }
}
