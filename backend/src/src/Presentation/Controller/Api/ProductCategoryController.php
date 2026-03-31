<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\ProductCategoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductCategoryController extends AbstractController
{
    public function __construct(private readonly ProductCategoryService $categoryService)
    {
    }

    #[Route('/api/public/product-categories', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->categoryService->listAll());
    }

    #[Route('/api/admin/product-categories', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->categoryService->create(
                $data['name'],
                (int) ($data['sortOrder'] ?? 0),
            );
            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/admin/product-categories/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            return $this->json($this->categoryService->update($id, $data));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/product-categories/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->categoryService->delete($id);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
