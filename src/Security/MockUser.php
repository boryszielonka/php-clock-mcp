<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

// Simple user class for stateless authentication
readonly class MockUser implements UserInterface
{
    public function __construct(
        private string $userIdentifier
    ) {
    }

    public function getUserIdentifier(): string
    {
        return '' !== $this->userIdentifier ? $this->userIdentifier : 'anonymous';
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase in stateless authentication
    }
}
