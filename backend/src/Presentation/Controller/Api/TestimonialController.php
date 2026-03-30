<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Domain\Entity\Testimonial;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineTestimonialRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestimonialController extends AbstractController
{
    public function __construct(private readonly DoctrineTestimonialRepository $repo)
    {
    }

    #[Route('/api/public/testimonials', methods: ['GET'])]
    public function published(): JsonResponse
    {
        return $this->json(array_map(fn(Testimonial $t) => [
            'id' => $t->getId(),
            'clientName' => $t->getClientName(),
            'message' => $t->getMessage(),
            'rating' => $t->getRating(),
        ], $this->repo->findPublished()));
    }

    #[Route('/api/admin/testimonials', methods: ['GET'])]
    public function listAll(): JsonResponse
    {
        return $this->json(array_map(fn(Testimonial $t) => [
            'id' => $t->getId(),
            'clientName' => $t->getClientName(),
            'message' => $t->getMessage(),
            'rating' => $t->getRating(),
            'published' => $t->isPublished(),
            'createdAt' => $t->getCreatedAt()->format('Y-m-d'),
        ], $this->repo->findAll()));
    }

    #[Route('/api/admin/testimonials', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $t = new Testimonial(
            $data['clientName'] ?? '',
            $data['message'] ?? '',
            (int) ($data['rating'] ?? 5),
            (bool) ($data['published'] ?? false),
        );
        $this->repo->save($t);

        return $this->json(['id' => $t->getId()], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/testimonials/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $t = $this->repo->find($id);
        if (!$t) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['clientName'])) $t->setClientName($data['clientName']);
        if (isset($data['message'])) $t->setMessage($data['message']);
        if (isset($data['rating'])) $t->setRating((int) $data['rating']);
        if (isset($data['published'])) $t->setPublished((bool) $data['published']);
        $this->repo->save($t);

        return $this->json(['success' => true]);
    }

    #[Route('/api/admin/testimonials/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $t = $this->repo->find($id);
        if (!$t) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $this->repo->delete($t);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
