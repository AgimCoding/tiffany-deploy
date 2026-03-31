<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UploadController extends AbstractController
{
    #[Route('/api/admin/upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        try {
            $file = $request->files->get('file');

            if (!$file) {
                return $this->json(['error' => 'Aucun fichier envoyé.'], Response::HTTP_BAD_REQUEST);
            }

            if (!$file->isValid()) {
                return $this->json(['error' => 'Erreur upload: ' . $file->getErrorMessage()], Response::HTTP_BAD_REQUEST);
            }

            // Validate by extension (fileinfo may not be available)
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowedExtensions, true)) {
                return $this->json(['error' => 'Format non supporté. Utilisez JPG, PNG, WebP ou GIF.'], Response::HTTP_BAD_REQUEST);
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->json(['error' => 'Fichier trop volumineux (max 5 Mo).'], Response::HTTP_BAD_REQUEST);
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = uniqid('img_', true) . '.' . $ext;
            $file->move($uploadDir, $filename);

            $url = '/uploads/' . $filename;

            return $this->json(['url' => $url], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
