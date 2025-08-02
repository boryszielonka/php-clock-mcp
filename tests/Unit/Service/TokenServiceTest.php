<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

final class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;

    protected function setUp(): void
    {
        $this->tokenService = new TokenService(
            secretKey: 'test-secret-key-for-testing-only',
            tokenTtl: 3600
        );
    }

    public function testSuccessfulTokenGenerationAndValidation(): void
    {
        // Given
        $userId = 'test-user-123';

        // When
        $token = $this->tokenService->generateToken($userId);

        // Then
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Should contain 2 parts (payload.signature)
        $parts = explode('.', $token);
        $this->assertCount(2, $parts);

        // Test validation through getUserBadgeFrom
        $userBadge = $this->tokenService->getUserBadgeFrom($token);

        $this->assertSame($userId, $userBadge->getUserIdentifier());

        // Test that user loader returns MockUser
        $userLoader = $userBadge->getUserLoader();
        $this->assertIsCallable($userLoader);

        $user = $userLoader($userId);
        $this->assertInstanceOf(\App\Security\MockUser::class, $user);
        $this->assertSame($userId, $user->getUserIdentifier());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testInvalidTokenThrowsException(): void
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->tokenService->getUserBadgeFrom('invalid-token');
    }

    public function testExpiredTokenThrowsException(): void
    {
        // Create a token service with negative TTL (immediately expired)
        $expiredTokenService = new TokenService('test-secret', -1);
        $token = $expiredTokenService->generateToken('test-user');

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $expiredTokenService->getUserBadgeFrom($token);
    }
}
