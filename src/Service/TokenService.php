<?php

declare(strict_types=1);

namespace App\Service;

use App\Security\MockUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

readonly class TokenService implements AccessTokenHandlerInterface
{
    public function __construct(
        #[Autowire('%env(CLOCK_MCP_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(int:CLOCK_MCP_TOKEN_TTL)%')]
        private int $tokenTtl
    ) {
    }

    /**
     * Generates a cryptographic token without persistent storage
     * Token contains: user_id, expiry, signature.
     */
    public function generateToken(string $userId): string
    {
        $expiresAt = (new \DateTimeImmutable())->modify("+{$this->tokenTtl} seconds");

        $payload = [
            'user_id' => $userId,
            'expires_at' => $expiresAt->getTimestamp(),
            'issued_at' => time(),
        ];

        $payloadBase64 = base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->createSignature($payloadBase64);

        return $payloadBase64.'.'.$signature;
    }

    /**
     * Implementation of AccessTokenHandlerInterface
     * Returns UserBadge with user identifier or throws exception if invalid.
     */
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $userId = $this->validateTokenInternal($accessToken);

        if (null === $userId) {
            throw new BadCredentialsException('Invalid or expired token');
        }

        return new UserBadge($userId, function (string $userIdentifier): MockUser {
            // Return a simple user object for our stateless authentication
            return new MockUser($userIdentifier);
        });
    }

    /**
     * Validates token without requiring storage lookup
     * Returns user_id if valid, null if invalid/expired.
     */
    private function validateTokenInternal(string $token): ?string
    {
        try {
            $parts = explode('.', $token);
            if (2 !== count($parts)) {
                return null;
            }

            [$payloadBase64, $signature] = $parts;

            // Verify signature
            if (!hash_equals($this->createSignature($payloadBase64), $signature)) {
                return null;
            }

            // Decode and validate payload
            $payload = json_decode(base64_decode($payloadBase64), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($payload) || !isset($payload['user_id'], $payload['expires_at'])) {
                return null;
            }

            // Check expiry
            if (!is_int($payload['expires_at']) || $payload['expires_at'] < time()) {
                return null;
            }

            return is_string($payload['user_id']) ? $payload['user_id'] : null;
        } catch (\JsonException) {
            return null;
        }
    }

    private function createSignature(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secretKey);
    }
}
