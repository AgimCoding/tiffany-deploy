<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\PushSubscription;
use App\Domain\Entity\User;
use App\Domain\Port\PushSubscriptionRepositoryInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\VAPID;

final class PushNotificationService
{
    public function __construct(
        private readonly PushSubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SiteSettingService $settingService,
    ) {
    }

    public function subscribe(User $user, array $subscriptionData): PushSubscription
    {
        $endpoint = $subscriptionData['endpoint'];
        $keys = $subscriptionData['keys'] ?? [];

        $existing = $this->subscriptionRepository->findByEndpoint($endpoint);
        if ($existing) {
            $this->subscriptionRepository->delete($existing);
        }

        $subscription = new PushSubscription(
            $user,
            $endpoint,
            $keys['p256dh'] ?? '',
            $keys['auth'] ?? '',
            $subscriptionData['contentEncoding'] ?? 'aesgcm',
        );

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    public function unsubscribe(string $endpoint): void
    {
        $this->subscriptionRepository->deleteByEndpoint($endpoint);
    }

    public function sendToUser(int $userId, string $title, string $body, string $url = '/', string $tag = ''): int
    {
        $subscriptions = $this->subscriptionRepository->findByUserId($userId);
        $result = $this->doSend($subscriptions, $title, $body, $url, $tag);
        return $result['sent'];
    }

    /**
     * @return array{sent: int, total: int, errors: string[]}
     */
    public function sendToUserWithDebug(int $userId, string $title, string $body, string $url = '/', string $tag = ''): array
    {
        $subscriptions = $this->subscriptionRepository->findByUserId($userId);
        return $this->doSend($subscriptions, $title, $body, $url, $tag);
    }

    public function sendToAll(string $title, string $body, string $url = '/', string $tag = ''): int
    {
        $subscriptions = $this->subscriptionRepository->findAll();
        $result = $this->doSend($subscriptions, $title, $body, $url, $tag);
        return $result['sent'];
    }

    /**
     * @param PushSubscription[] $subscriptions
     * @return array{sent: int, total: int, errors: string[]}
     */
    private function doSend(array $subscriptions, string $title, string $body, string $url, string $tag): array
    {
        $total = count($subscriptions);
        $errors = [];

        if ($total === 0) {
            return ['sent' => 0, 'total' => 0, 'errors' => ['Aucune subscription trouvee']];
        }

        $vapidPublic = $this->settingService->get('vapid_public_key');
        $vapidPrivate = $this->settingService->get('vapid_private_key');
        $adminEmail = $this->settingService->get('admin_email') ?: 'contact@tiffany-creations.be';
        $vapidSubject = str_starts_with($adminEmail, 'mailto:') ? $adminEmail : 'mailto:' . $adminEmail;

        if (!$vapidPublic || !$vapidPrivate) {
            return ['sent' => 0, 'total' => $total, 'errors' => ['Cles VAPID non configurees']];
        }

        $auth = [
            'VAPID' => [
                'subject' => $vapidSubject,
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ];

        try {
            $webPush = new WebPush($auth);
        } catch (\Throwable $e) {
            return ['sent' => 0, 'total' => $total, 'errors' => ['WebPush init: ' . $e->getMessage()]];
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag ?: 'tiffany-' . time(),
            'icon' => '/icon-192.svg',
            'badge' => '/icon-192.svg',
        ], JSON_UNESCAPED_UNICODE);

        // Queue all notifications
        foreach ($subscriptions as $sub) {
            try {
                $webPush->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub->getEndpoint(),
                        'publicKey' => $sub->getPublicKey(),
                        'authToken' => $sub->getAuthToken(),
                        'contentEncoding' => $sub->getContentEncoding(),
                    ]),
                    $payload,
                );
            } catch (\Throwable $e) {
                $errors[] = 'Queue error: ' . $e->getMessage();
            }
        }

        // Flush and process results
        $sent = 0;
        $i = 0;
        foreach ($webPush->flush() as $report) {
            $sub = $subscriptions[$i] ?? null;

            if ($report->isSuccess()) {
                $sent++;
                if ($sub) {
                    $sub->markUsed();
                    $this->subscriptionRepository->save($sub);
                }
            } else {
                $reason = $report->getReason();
                $statusCode = $report->getResponse()?->getStatusCode() ?? 0;
                $errors[] = "HTTP {$statusCode}: {$reason}";

                // Clean up expired subscriptions (404/410)
                if ($sub && in_array($statusCode, [404, 410])) {
                    $this->subscriptionRepository->delete($sub);
                }
            }
            $i++;
        }

        return ['sent' => $sent, 'total' => $total, 'errors' => $errors];
    }

    /**
     * Generate VAPID key pair and store in site settings.
     */
    public function generateVapidKeys(): array
    {
        $keys = VAPID::createVapidKeys();

        $this->settingService->set('vapid_public_key', $keys['publicKey']);
        $this->settingService->set('vapid_private_key', $keys['privateKey']);

        return [
            'publicKey' => $keys['publicKey'],
        ];
    }

    /**
     * @return PushSubscription[]
     */
    public function getAllSubscriptions(): array
    {
        return $this->subscriptionRepository->findAll();
    }

    public function getVapidPublicKey(): ?string
    {
        return $this->settingService->get('vapid_public_key');
    }
}
