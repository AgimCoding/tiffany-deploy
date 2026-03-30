<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\AdminLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AdminLogController extends AbstractController
{
    public function __construct(
        private readonly AdminLogService $adminLogService
    ) {
    }

    #[Route('/api/admin/logs', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $limit = (int) ($request->query->get('limit', 100));
        $logs = $this->adminLogService->getRecent(min($limit, 500));

        return $this->json($logs);
    }

    #[Route('/api/admin/logs/cleanup', methods: ['POST'])]
    public function cleanup(): JsonResponse
    {
        $deleted = $this->adminLogService->cleanup(90);

        return $this->json(['deleted' => $deleted]);
    }
}
