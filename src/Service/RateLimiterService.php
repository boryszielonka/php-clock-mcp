<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

// TODO
readonly class RateLimiterService
{
    private RateLimiterFactory $factory;

    public function __construct(
        #[Autowire('%env(int:CLOCK_MCP_RATE_LIMIT_WINDOW)%')]
        private int $windowSeconds,
        #[Autowire('%env(int:CLOCK_MCP_RATE_LIMIT_PER_MINUTE)%')]
        private int $requestsPerWindow
    ) {
        // Create rate limiter with in-memory storage
        $this->factory = new RateLimiterFactory([
            'id' => 'clock_mcp_api',
            'policy' => 'sliding_window',
            'limit' => $this->requestsPerWindow,
            'interval' => "{$this->windowSeconds} seconds",
        ], new InMemoryStorage());
    }

    public function isAllowed(string $userId): bool
    {
        $limiter = $this->factory->create($userId);

        return $limiter->consume(1)->isAccepted();
    }

    public function getRemainingRequests(string $userId): int
    {
        $limiter = $this->factory->create($userId);
        $limit = $limiter->consume(0); // Peek , not consume

        return $limit->getRemainingTokens();
    }

    public function getRateLimitHeaders(string $userId): array
    {
        $limiter = $this->factory->create($userId);
        $limit = $limiter->consume(0); // Peek without consuming

        return [
            'X-RateLimit-Limit' => $this->requestsPerWindow,
            'X-RateLimit-Remaining' => $limit->getRemainingTokens(),
            'X-RateLimit-Reset' => $limit->getRetryAfter()->getTimestamp(),
        ];
    }
}
