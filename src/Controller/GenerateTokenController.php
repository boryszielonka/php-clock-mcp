<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\RateLimiterService;
use App\Service\TokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// TODO
#[Route('/api')]
final class GenerateTokenController extends AbstractController
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly RateLimiterService $rateLimiter,
    ) {
    }

    #[Route('/token', name: 'api_generate_token', methods: ['POST'])]
    public function generateToken(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !isset($data['user_id']) || empty($data['user_id'])) {
            return new JsonResponse([
                'error' => 'user_id is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $userId = (string) $data['user_id'];

        // Rate limiting for token generation to prevent abuse
        if (!$this->rateLimiter->isAllowed("token_gen_{$userId}")) {
            return new JsonResponse([
                'error' => 'Token generation rate limit exceeded',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $token = $this->tokenService->generateToken($userId);

            return new JsonResponse([
                'token' => $token,
                'user_id' => $userId,
                'expires_in' => 3600, // 1 hour
                'type' => 'bearer',
            ]);
        } catch (\Exception) {
            return new JsonResponse([
                'error' => 'Failed to generate token',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}