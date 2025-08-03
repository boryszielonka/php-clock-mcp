<?php

declare(strict_types=1);

namespace App\Tools;

use App\Service\RateLimiterService;
use DateTimeZone;
use Exception;
use Symfony\AI\McpSdk\Capability\Tool\IdentifierInterface;
use Symfony\AI\McpSdk\Capability\Tool\MetadataInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolAnnotationsInterface;
use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;

// TODO split and refactor
readonly class Clock implements MetadataInterface, ToolExecutorInterface
{
    public function __construct(
        private ClockInterface     $clock,
        private RateLimiterService $rateLimiter,
        private Security           $security
    ) {
    }

    public function getName(): string
    {
        return 'get_current_time';
    }

    public function getDescription(): ?string
    {
        return 'Get the current date and time in a specified timezone and format';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'timezone' => [
                    'type' => 'string',
                    'description' => 'Timezone identifier (e.g., UTC, America/New_York)',
                    'default' => 'UTC'
                ],
                'format' => [
                    'type' => 'string',
                    'description' => 'PHP date format string (e.g., Y-m-d H:i:s)',
                    'default' => 'Y-m-d H:i:s'
                ]
            ]
        ];
    }

    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'current_time' => [
                    'type' => 'string',
                    'description' => 'Formatted current time'
                ],
                'timezone' => [
                    'type' => 'string',
                    'description' => 'Timezone name'
                ],
                'timestamp' => [
                    'type' => 'integer',
                    'description' => 'Unix timestamp'
                ],
                'iso8601' => [
                    'type' => 'string',
                    'description' => 'ISO 8601 formatted datetime'
                ]
            ]
        ];
    }

    public function getTitle(): ?string
    {
        return 'Current Time';
    }

    public function getAnnotations(): ?ToolAnnotationsInterface
    {
        return null;
    }

    public function call(ToolCall $input): ToolCallResult
    {
        $arguments = $input->arguments;
        $timezone = $arguments['timezone'] ?? 'UTC';
        $format = $arguments['format'] ?? 'Y-m-d H:i:s';

        $dateTimeZone = $this->createTimeZone($timezone);
        $now = $this->clock->now()->setTimezone($dateTimeZone);

        $result = [
            'current_time' => $now->format($format),
            'timezone' => $dateTimeZone->getName(),
            'timestamp' => $now->getTimestamp(),
            'iso8601' => $now->format('c'),
        ];

        return new ToolCallResult(
            result: json_encode([
                [
                    'type' => 'text',
                    'text' => json_encode($result, JSON_PRETTY_PRINT)
                ]
            ], JSON_THROW_ON_ERROR),
            isError: false
        );
    }

    private function createTimeZone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Exception) {
            return new DateTimeZone('UTC');
        }
    }

    // TODO
    private function createRateLimitErrorResponse(string $userId): array
    {
        $headers = $this->rateLimiter->getRateLimitHeaders($userId);

        return [
            'error' => 'Rate limit exceeded',
            'retry_after' => $headers['X-RateLimit-Reset'],
            'remaining' => $headers['X-RateLimit-Remaining'],
        ];
    }
}
