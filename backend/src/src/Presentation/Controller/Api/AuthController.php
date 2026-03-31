<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\AuthService;
use App\Application\Service\MailerService;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly MailerService $mailerService,
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $fullName = $data['fullName'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';

        if (!$fullName || !$email || !$password) {
            return $this->json(['error' => 'Champs requis manquants.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userDTO = $this->authService->register($fullName, $email, $phone, $password);

            $this->mailerService->sendRegistration($email, $fullName);

            return $this->json($userDTO, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    #[Route('/profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->authService->getProfile($user));
    }

    #[Route('/profile', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }
        if (isset($data['fullName']) && $data['fullName']) {
            $user->setFullName($data['fullName']);
        }

        $this->authService->saveUser($user);

        return $this->json($this->authService->getProfile($user));
    }
}
