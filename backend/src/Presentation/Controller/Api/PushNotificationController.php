<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\PushNotificationService;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PushNotificationController extends AbstractController
{
    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {
    }

    /**
     * Get VAPID public key (public endpoint — needed by frontend to subscribe).
     */
    #[Route('/api/public/push/vapid-key', methods: ['GET'])]
    public function vapidKey(): JsonResponse
    {
        $key = $this->pushService->getVapidPublicKey();
        if (!$key) {
            return $this->json(['error' => 'VAPID keys not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json(['publicKey' => $key]);
    }

    /**
     * Subscribe current user's device to push notifications.
     */
    #[Route('/api/push/subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            return $this->json(['error' => 'Subscription data incomplete.'], Response::HTTP_BAD_REQUEST);
        }

        $this->pushService->subscribe($user, $data);

        return $this->json(['success' => true, 'message' => 'Notifications activées.']);
    }

    /**
     * Unsubscribe a device.
     */
    #[Route('/api/push/unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $endpoint = $data['endpoint'] ?? '';

        if (!$endpoint) {
            return $this->json(['error' => 'Endpoint requis.'], Response::HTTP_BAD_REQUEST);
        }

        $this->pushService->unsubscribe($endpoint);

        return $this->json(['success' => true, 'message' => 'Notifications désactivées.']);
    }

    /**
     * Admin: Generate VAPID keys (one-time setup).
     */
    #[Route('/api/admin/push/generate-vapid', methods: ['POST'])]
    public function generateVapid(): JsonResponse
    {
        $result = $this->pushService->generateVapidKeys();

        return $this->json([
            'success' => true,
            'publicKey' => $result['publicKey'],
            'message' => 'Clés VAPID générées avec succès.',
        ]);
    }

    /**
     * Admin: Send push notification to all users.
     */
    #[Route('/api/admin/push/send', methods: ['POST'])]
    public function sendToAll(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $title = $data['title'] ?? '';
        $body = $data['body'] ?? '';
        $url = $data['url'] ?? '/';

        if (!$title || !$body) {
            return $this->json(['error' => 'Titre et message requis.'], Response::HTTP_BAD_REQUEST);
        }

        $sent = $this->pushService->sendToAll($title, $body, $url, 'admin-broadcast');

        return $this->json([
            'success' => true,
            'sent' => $sent,
            'message' => "$sent notification(s) envoyée(s).",
        ]);
    }

    /**
     * Admin: Test push notification (send to admin only).
     */
    #[Route('/api/admin/push/test', methods: ['POST'])]
    public function testPush(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $sent = $this->pushService->sendToUser(
            $user->getId(),
            'Test notification',
            'Les notifications push fonctionnent correctement !',
            '/',
            'test-push',
        );

        return $this->json([
            'success' => $sent > 0,
            'sent' => $sent,
            'message' => $sent > 0 ? 'Notification de test envoyée.' : 'Aucun appareil enregistré.',
        ]);
    }
}
