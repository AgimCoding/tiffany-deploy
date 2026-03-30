<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\FamilyMemberService;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FamilyMemberController extends AbstractController
{
    public function __construct(private readonly FamilyMemberService $familyService)
    {
    }

    #[Route('/api/family-members', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json($this->familyService->listForUser($user));
    }

    #[Route('/api/family-members', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->familyService->create(
                $user,
                $data['firstName'],
                $data['lastName'],
                $data['relationship'] ?? null,
            );
            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/family-members/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            return $this->json($this->familyService->update($id, $user, $data));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/family-members/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->familyService->delete($id, $user);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
