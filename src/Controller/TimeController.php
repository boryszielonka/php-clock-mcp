<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TimeController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface     $clock,
        private readonly RateLimiterService $rateLimiter,
        private readonly Security           $security
    ) {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function health(): Response
    {
        return new Response('healthy', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    #[Route('/api/current-time', name: 'api_current_time', methods: ['GET'])]
    public function getCurrentTime(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getUserIdentifier();

        if (!$this->rateLimiter->isAllowed($userId)) {
            $headers = $this->rateLimiter->getRateLimitHeaders($userId);

            return $this->createRateLimitResponse($headers);
        }

        $timezone = (string)$request->query->get('timezone', 'UTC');
        $format = (string)$request->query->get('format', 'Y-m-d H:i:s');

        $dateTimeZone = $this->createTimeZone($timezone);
        $now = $this->clock->now()->setTimezone($dateTimeZone);
        $headers = $this->rateLimiter->getRateLimitHeaders($userId);

        $response = new JsonResponse([
            'current_time' => $now->format($format),
            'timezone' => $dateTimeZone->getName(),
            'timestamp' => $now->getTimestamp(),
            'iso8601' => $now->format('c'),
            'user_id' => $userId,
        ]);

        $response->headers->add($headers);

        return $response;
    }

    #[Route('/api/timestamp', name: 'api_timestamp', methods: ['GET'])]
    public function getTimestamp(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $userId = $user->getUserIdentifier();

        if (!$this->rateLimiter->isAllowed($userId)) {
            $headers = $this->rateLimiter->getRateLimitHeaders($userId);

            return $this->createRateLimitResponse($headers);
        }

        $timezone = (string)$request->query->get('timezone', 'UTC');
        $dateTimeZone = $this->createTimeZone($timezone);
        $now = $this->clock->now()->setTimezone($dateTimeZone);
        $headers = $this->rateLimiter->getRateLimitHeaders($userId);

        $response = new JsonResponse([
            'timestamp' => $now->getTimestamp(),
            'timezone' => $dateTimeZone->getName(),
            'unix_timestamp' => $now->getTimestamp(),
            'milliseconds' => $now->getTimestamp() * 1000,
            'user_id' => $userId,
        ]);

        $response->headers->add($headers);

        return $response;
    }

    private function createTimeZone(string $timezone): \DateTimeZone
    {
        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }

    private function createRateLimitResponse(array $headers): JsonResponse
    {
        $response = new JsonResponse([
            'error' => 'Rate limit exceeded',
            'retry_after' => $headers['X-RateLimit-Reset'] - time(),
            'remaining' => $headers['X-RateLimit-Remaining'],
        ], Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->add($headers);

        return $response;
    }
}
