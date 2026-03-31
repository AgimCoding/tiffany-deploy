<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\ServiceCatalogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceController extends AbstractController
{
    public function __construct(private readonly ServiceCatalogService $catalogService)
    {
    }

    #[Route('/api/public/services', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->catalogService->listAll());
    }

    #[Route('/api/admin/services', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->catalogService->create(
                $data['name'],
                $data['price'],
                (int) $data['durationMin'],
                $data['description'],
                (bool) ($data['isQuote'] ?? false),
                (int) ($data['sortOrder'] ?? 0),
                $data['category'] ?? null,
            );
            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/admin/services/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            return $this->json($this->catalogService->update($id, $data));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/services/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->catalogService->delete($id);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
