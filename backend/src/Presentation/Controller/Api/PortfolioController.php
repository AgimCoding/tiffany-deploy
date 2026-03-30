<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\PortfolioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortfolioController extends AbstractController
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    // ── Public ──────────────────────────────────────────

    #[Route('/api/public/portfolio', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->portfolioService->listAll());
    }

    #[Route('/api/public/portfolio/albums', methods: ['GET'])]
    public function listAlbums(): JsonResponse
    {
        return $this->json($this->portfolioService->listAlbums());
    }

    #[Route('/api/public/portfolio/albums/{albumId}/photos', methods: ['GET'])]
    public function listByAlbum(int $albumId): JsonResponse
    {
        return $this->json($this->portfolioService->listByAlbum($albumId));
    }

    // ── Admin Albums ────────────────────────────────────

    #[Route('/api/admin/portfolio/albums', methods: ['POST'])]
    public function createAlbum(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->portfolioService->createAlbum(
                $data['name'],
                $data['description'] ?? null,
                $data['coverImageUrl'] ?? null,
                (int) ($data['sortOrder'] ?? 0),
            );
            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/admin/portfolio/albums/{id}', methods: ['PUT'])]
    public function updateAlbum(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->portfolioService->updateAlbum(
                $id,
                $data['name'],
                $data['description'] ?? null,
                $data['coverImageUrl'] ?? null,
                (int) ($data['sortOrder'] ?? 0),
            );
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/portfolio/albums/{id}', methods: ['DELETE'])]
    public function deleteAlbum(int $id): JsonResponse
    {
        try {
            $this->portfolioService->deleteAlbum($id);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    // ── Admin Items ─────────────────────────────────────

    #[Route('/api/admin/portfolio', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->portfolioService->create(
                $data['title'],
                $data['imageUrl'],
                $data['description'] ?? null,
                (int) ($data['sortOrder'] ?? 0),
                isset($data['albumId']) ? (int) $data['albumId'] : null,
                (bool) ($data['isFeatured'] ?? false),
            );
            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/admin/portfolio/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->portfolioService->update($id, $data);
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/portfolio/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->portfolioService->delete($id);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
