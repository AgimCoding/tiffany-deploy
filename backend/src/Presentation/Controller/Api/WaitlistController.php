<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\MailerService;
use App\Application\Service\PushNotificationService;
use App\Domain\Entity\User;
use App\Domain\Entity\Waitlist;
use App\Domain\Port\ServiceRepositoryInterface;
use App\Domain\Port\WaitlistRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WaitlistController extends AbstractController
{
    public function __construct(
        private readonly WaitlistRepositoryInterface $waitlistRepo,
        private readonly ServiceRepositoryInterface $serviceRepo,
        private readonly MailerService $mailerService,
        private readonly PushNotificationService $pushService,
    ) {}

    /** Add current user to waitlist for a service. */
    #[Route('/api/waitlist', methods: ['POST'])]
    public function join(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $serviceId = (int) ($data['serviceId'] ?? 0);
        $preferredDate = !empty($data['preferredDate']) ? new \DateTimeImmutable($data['preferredDate']) : null;

        if ($serviceId <= 0) {
            return $this->json(['error' => 'Prestation requise.'], Response::HTTP_BAD_REQUEST);
        }

        $service = $this->serviceRepo->findById($serviceId);
        if (!$service) {
            return $this->json(['error' => 'Prestation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Check if already on waitlist for this service
        $existing = $this->waitlistRepo->findByUser($user->getId());
        foreach ($existing as $e) {
            if ($e->getService()->getId() === $serviceId && !$e->isNotified()) {
                return $this->json(['error' => 'Vous etes deja sur la liste d\'attente pour cette prestation.'], Response::HTTP_CONFLICT);
            }
        }

        $entry = new Waitlist($user, $service, $preferredDate);
        $this->waitlistRepo->save($entry);

        return $this->json([
            'id' => $entry->getId(),
            'service' => $service->getName(),
            'preferredDate' => $preferredDate?->format('Y-m-d'),
            'message' => 'Vous serez notifie des qu\'un creneau se libere.',
        ], Response::HTTP_CREATED);
    }

    /** List my waitlist entries. */
    #[Route('/api/waitlist', methods: ['GET'])]
    public function listMine(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $entries = $this->waitlistRepo->findByUser($user->getId());

        return $this->json(array_map(fn(Waitlist $w) => [
            'id' => $w->getId(),
            'serviceName' => $w->getService()->getName(),
            'serviceId' => $w->getService()->getId(),
            'preferredDate' => $w->getPreferredDate()?->format('Y-m-d'),
            'notified' => $w->isNotified(),
            'createdAt' => $w->getCreatedAt()->format('Y-m-d H:i'),
        ], $entries));
    }

    /** Remove from waitlist. */
    #[Route('/api/waitlist/{id}', methods: ['DELETE'])]
    public function remove(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $entry = $this->waitlistRepo->findById($id);

        if (!$entry || $entry->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Entree introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->waitlistRepo->delete($entry);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** Admin: notify waitlist when a slot opens up. */
    #[Route('/api/admin/waitlist/notify/{serviceId}', methods: ['POST'])]
    public function notifyWaitlist(int $serviceId): JsonResponse
    {
        $service = $this->serviceRepo->findById($serviceId);
        if (!$service) {
            return $this->json(['error' => 'Prestation introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $pending = $this->waitlistRepo->findPendingByService($serviceId);
        $notified = 0;

        foreach ($pending as $entry) {
            $user = $entry->getUser();

            // Send email
            $this->mailerService->send(
                $user->getEmail(),
                'Un creneau s\'est libere !',
                sprintf(
                    '<p>Bonjour %s,</p><p>Un creneau est disponible pour <strong>%s</strong>. Reservez vite sur notre site !</p><p>Les Creations de Tiffany</p>',
                    $user->getFullName(),
                    $service->getName(),
                ),
            );

            // Send push notification
            $this->pushService->sendToUser(
                $user->getId(),
                'Creneau disponible !',
                sprintf('Un creneau pour %s est disponible. Reservez vite !', $service->getName()),
                '/#reservation',
                'waitlist-' . $serviceId,
            );

            $entry->markNotified();
            $this->waitlistRepo->save($entry);
            $notified++;
        }

        return $this->json([
            'notified' => $notified,
            'message' => "$notified personne(s) notifiee(s).",
        ]);
    }
}
