<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ReminderNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\UnexpectedValueException as SerializerUnexpectedValueException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        [$statusCode, $message] = $this->getErrorDetails($exception);

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ],
        ], $statusCode));
    }

    /**
     * @return array{int, string}
     */
    private function getErrorDetails(\Throwable $exception): array
    {
        return match (true) {
            $exception instanceof ReminderNotFoundException => [404, $exception->getMessage()],
            $exception instanceof ValidationFailedException,
            $exception instanceof SerializerUnexpectedValueException,
            $exception instanceof UnprocessableEntityHttpException => [422, $exception->getMessage()],
            $exception instanceof BadRequestHttpException => [400, $exception->getMessage()],
            default => [500, 'Internal Server Error'],
        };
    }
}
