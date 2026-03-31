<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\AdminLogService;
use App\Application\Service\MailerService;
use App\Application\Service\OrderService;
use App\Application\Service\PushNotificationService;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly MailerService $mailerService,
        private readonly AdminLogService $adminLogService,
        private readonly PushNotificationService $pushService,
    ) {
    }

    #[Route('/api/orders', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->orderService->create($user, $data['items'] ?? []);

            $this->mailerService->sendOrderConfirmed(
                $user->getEmail(),
                $user->getFullName(),
                (string) $dto->id,
                $dto->total,
                $dto->items,
            );

            // Notify admin via push
            try {
                $this->pushService->sendToAdmins(
                    'Nouvelle commande',
                    sprintf('%s - Commande #%d (%s EUR)', $user->getFullName(), $dto->id, $dto->total),
                    '/#admin',
                    'new-order-' . $dto->id,
                );
            } catch (\Throwable) {}

            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/orders', methods: ['GET'])]
    public function listMine(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->orderService->listForUser($user));
    }

    // ─── Admin endpoints ───

    #[Route('/api/admin/orders', methods: ['GET'])]
    public function adminListAll(): JsonResponse
    {
        return $this->json($this->orderService->listAll());
    }

    #[Route('/api/admin/orders/{id}/confirm', methods: ['PATCH'])]
    public function adminConfirm(int $id): JsonResponse
    {
        try {
            $dto = $this->orderService->confirm($id);
            $this->sendOrderEmail($id, 'confirmed');
            $this->sendOrderPush($id, 'confirmed');
            $this->adminLogService->log($this->getUser(), 'confirm', 'order', $id, "Commande #{$id} confirmée");
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/orders/{id}/ready', methods: ['PATCH'])]
    public function adminReady(int $id): JsonResponse
    {
        try {
            $dto = $this->orderService->markReady($id);
            $this->sendOrderEmail($id, 'ready');
            $this->sendOrderPush($id, 'ready');
            $this->adminLogService->log($this->getUser(), 'ready', 'order', $id, "Commande #{$id} prête");
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/orders/{id}/complete', methods: ['PATCH'])]
    public function adminComplete(int $id): JsonResponse
    {
        try {
            $dto = $this->orderService->complete($id);
            $this->sendOrderEmail($id, 'completed');
            $this->sendOrderPush($id, 'completed');
            $this->adminLogService->log($this->getUser(), 'complete', 'order', $id, "Commande #{$id} livrée");
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/orders/{id}/cancel', methods: ['PATCH'])]
    public function adminCancel(int $id): JsonResponse
    {
        try {
            $dto = $this->orderService->cancel($id);
            $this->sendOrderEmail($id, 'cancelled');
            $this->sendOrderPush($id, 'cancelled');
            $this->adminLogService->log($this->getUser(), 'cancel', 'order', $id, "Commande #{$id} annulée");
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    private function sendOrderEmail(int $id, string $status): void
    {
        try {
            $order = $this->orderService->findEntityById($id);
            if ($order) {
                $items = [];
                foreach ($order->getItems() as $item) {
                    $items[] = [
                        'productName' => $item->getProduct()->getName(),
                        'quantity' => $item->getQuantity(),
                        'price' => $item->getPrice(),
                    ];
                }
                $this->mailerService->sendOrderStatusUpdate(
                    $order->getUser()->getEmail(),
                    $order->getUser()->getFullName(),
                    (string) $order->getId(),
                    $order->getTotal(),
                    $status,
                    $items,
                );
            }
        } catch (\Throwable) {
            // Silent - don't block the action
        }
    }

    private function sendOrderPush(int $id, string $status): void
    {
        try {
            $order = $this->orderService->findEntityById($id);
            if (!$order) return;

            $userId = $order->getUser()->getId();
            $orderId = (string) $order->getId();
            $total = $order->getTotal();

            $messages = [
                'confirmed' => ['Commande confirmée', "Votre commande #{$orderId} ({$total}) a été confirmée."],
                'ready' => ['Commande prête', "Votre commande #{$orderId} est prête à être retirée !"],
                'completed' => ['Commande livrée', "Votre commande #{$orderId} est terminée. Merci !"],
                'cancelled' => ['Commande annulée', "Votre commande #{$orderId} a été annulée."],
            ];

            [$title, $body] = $messages[$status] ?? ['Commande', 'Mise à jour de votre commande.'];

            $this->pushService->sendToUser($userId, $title, $body, '/', "order-{$status}-{$orderId}");
        } catch (\Throwable) {
            // Silent
        }
    }

    #[Route('/api/admin/orders/{id}', methods: ['DELETE'])]
    public function adminDelete(int $id): JsonResponse
    {
        try {
            $this->adminLogService->log($this->getUser(), 'delete', 'order', $id, "Commande #{$id} supprimée");
            $this->orderService->delete($id);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
