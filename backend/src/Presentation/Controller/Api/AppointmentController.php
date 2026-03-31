<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\AdminLogService;
use App\Application\Service\AppointmentService;
use App\Application\Service\MailerService;
use App\Application\Service\PushNotificationService;
use App\Application\Service\SmsService;
use App\Application\Service\SiteSettingService;
use App\Domain\Entity\User;
use App\Domain\Port\UserRepositoryInterface;
use App\Domain\Port\FamilyMemberRepositoryInterface;
use App\Domain\Port\ServiceRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppointmentController extends AbstractController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly MailerService $mailerService,
        private readonly SmsService $smsService,
        private readonly SiteSettingService $settingService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly FamilyMemberRepositoryInterface $familyMemberRepository,
        private readonly ServiceRepositoryInterface $serviceRepository,
        private readonly AdminLogService $adminLogService,
        private readonly PushNotificationService $pushService,
    ) {
    }

    #[Route('/api/public/appointments/booked-slots', methods: ['GET'])]
    public function bookedSlots(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        if (!$date) {
            return $this->json(['error' => 'Date requise.'], Response::HTTP_BAD_REQUEST);
        }

        $slots = $this->appointmentService->getBookedSlots($date);
        return $this->json($slots);
    }

    #[Route('/api/public/appointments/next-slot', methods: ['GET'])]
    public function nextSlot(Request $request): JsonResponse
    {
        $serviceId = (int) $request->query->get('serviceId', 0);
        if ($serviceId <= 0) {
            return $this->json(['error' => 'serviceId requis.'], Response::HTTP_BAD_REQUEST);
        }

        $weeklySchedule = $this->settingService->get('schedule_weekly');
        $result = $this->appointmentService->findNextAvailableSlot($serviceId, $weeklySchedule);

        if ($result === null) {
            return $this->json(['available' => false, 'message' => 'Aucun créneau disponible dans les 14 prochains jours.']);
        }

        return $this->json([
            'available' => true,
            'date' => $result['date'],
            'timeSlot' => $result['timeSlot'],
            'dayLabel' => $result['dayLabel'],
        ]);
    }

    #[Route('/api/appointments/loyalty', methods: ['GET'])]
    public function loyalty(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $completed = $this->appointmentService->countCompletedForUser($user->getId());

        // Loyalty levels
        $levels = [
            ['min' => 0,  'name' => 'Nouvelle cliente',    'next' => 5,  'reward' => null],
            ['min' => 5,  'name' => 'Cliente fidèle',      'next' => 10, 'reward' => 'Soin capillaire offert'],
            ['min' => 10, 'name' => 'Cliente privilégiée',  'next' => 20, 'reward' => '10% de réduction'],
            ['min' => 20, 'name' => 'Cliente VIP',          'next' => 50, 'reward' => '15% de réduction + produit offert'],
            ['min' => 50, 'name' => 'Cliente Diamant',      'next' => null, 'reward' => '20% de réduction permanente'],
        ];

        $currentLevel = $levels[0];
        foreach ($levels as $level) {
            if ($completed >= $level['min']) {
                $currentLevel = $level;
            }
        }

        $nextLevel = null;
        foreach ($levels as $level) {
            if ($level['min'] > $completed) {
                $nextLevel = $level;
                break;
            }
        }

        return $this->json([
            'completedVisits' => $completed,
            'level' => $currentLevel['name'],
            'reward' => $currentLevel['reward'],
            'nextLevel' => $nextLevel ? $nextLevel['name'] : null,
            'visitsToNext' => $nextLevel ? ($nextLevel['min'] - $completed) : 0,
            'nextReward' => $nextLevel ? $nextLevel['reward'] : null,
            'progress' => $nextLevel ? round(($completed - $currentLevel['min']) / ($nextLevel['min'] - $currentLevel['min']) * 100) : 100,
        ]);
    }

    #[Route('/api/appointments', methods: ['POST'])]
    public function book(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->appointmentService->book(
                $user,
                (int) $data['serviceId'],
                $data['date'],
                $data['timeSlot'],
                $data['alternativeDate'] ?? null,
                $data['comment'] ?? null,
                isset($data['familyMemberId']) ? (int) $data['familyMemberId'] : null,
            );

            // Send confirmation email to client
            $dateFR = (new \DateTimeImmutable($dto->date))->format('d/m/Y');
            $this->mailerService->sendAppointmentConfirmed(
                $user->getEmail(),
                $user->getFullName(),
                $dto->serviceName,
                $dateFR,
                $dto->timeSlot,
            );

            // Send SMS confirmation to client
            $phone = $user->getPhone();
            if ($phone) {
                $this->smsService->send(
                    $phone,
                    sprintf(
                        'Bonjour %s, votre RDV du %s a %s pour %s est confirme. Les Creations de Tiffany - 0497 92 60 03',
                        $user->getFullName(),
                        $dateFR,
                        $dto->timeSlot,
                        $dto->serviceName,
                    ),
                );
            }

            // Notify admin by email
            $adminEmail = $this->settingService->get('admin_email');
            if ($adminEmail) {
                $this->mailerService->send(
                    $adminEmail,
                    'Nouveau RDV - ' . $user->getFullName(),
                    sprintf(
                        '<p><strong>Nouveau rendez-vous</strong></p><p>Client : %s<br>Email : %s<br>Tél : %s<br>Prestation : %s<br>Date : %s à %s</p>',
                        $user->getFullName(),
                        $user->getEmail(),
                        $phone ?: 'Non renseigné',
                        $dto->serviceName,
                        $dateFR,
                        $dto->timeSlot,
                    ),
                );
            }

            // Notify admin via push
            try {
                $this->pushService->sendToAdmins(
                    'Nouveau RDV',
                    sprintf('%s - %s le %s a %s', $user->getFullName(), $dto->serviceName, $dateFR, $dto->timeSlot),
                    '/#admin',
                    'new-appointment-' . $dto->id,
                );
            } catch (\Throwable) {}

            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/appointments', methods: ['GET'])]
    public function listMine(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($this->appointmentService->listForUser($user));
    }

    #[Route('/api/appointments/{id}/cancel', methods: ['PATCH'])]
    public function cancelMine(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $dto = $this->appointmentService->findById($id);
            if (!$dto || $dto->clientEmail !== $user->getEmail()) {
                return $this->json(['error' => 'Rendez-vous introuvable.'], Response::HTTP_NOT_FOUND);
            }

            // Check 24h minimum
            $appointmentDateTime = new \DateTimeImmutable($dto->date . ' ' . $dto->timeSlot);
            $now = new \DateTimeImmutable();
            $diff = $appointmentDateTime->getTimestamp() - $now->getTimestamp();
            if ($diff < 86400) {
                return $this->json(['error' => 'Annulation impossible moins de 24h avant le rendez-vous.'], Response::HTTP_BAD_REQUEST);
            }

            $this->appointmentService->cancel($id);

            // Notify admin via push
            try {
                $dateFR = (new \DateTimeImmutable($dto->date))->format('d/m/Y');
                $this->pushService->sendToAdmins(
                    'RDV annule par client',
                    sprintf('%s a annule son RDV du %s a %s (%s)', $user->getFullName(), $dateFR, $dto->timeSlot, $dto->serviceName),
                    '/#admin',
                    'cancel-appointment-' . $id,
                );
            } catch (\Throwable) {}

            return $this->json(['success' => true, 'message' => 'Rendez-vous annulé.']);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/admin/appointments', methods: ['GET'])]
    public function listAll(): JsonResponse
    {
        return $this->json($this->appointmentService->listAll());
    }

    #[Route('/api/admin/appointments/by-date', methods: ['GET'])]
    public function listByDate(Request $request): JsonResponse
    {
        $date = $request->query->get('date');
        if (!$date) {
            return $this->json(['error' => 'Date requise.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->appointmentService->listByDate($date));
    }

    #[Route('/api/admin/appointments/export', methods: ['GET'])]
    public function export(Request $request): JsonResponse
    {
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        if (!$from || !$to) {
            return $this->json(['error' => 'Dates requises (from, to).'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->appointmentService->listByDateRange($from, $to));
    }

    #[Route('/api/admin/appointments/{id}/confirm', methods: ['PATCH'])]
    public function confirm(int $id): JsonResponse
    {
        try {
            $dto = $this->appointmentService->confirm($id);
            $this->adminLogService->log($this->getUser(), 'confirm', 'appointment', $id, "RDV #{$id} confirmé");

            // Email + Push client
            $dateFR = (new \DateTimeImmutable($dto->date))->format('d/m/Y');
            try {
                $this->mailerService->sendAppointmentConfirmed(
                    $dto->clientEmail,
                    $dto->clientName,
                    $dto->serviceName,
                    $dateFR,
                    $dto->timeSlot,
                );
            } catch (\Throwable) {}
            $this->sendAppointmentPush($dto, 'confirm');

            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/appointments/{id}/complete', methods: ['PATCH'])]
    public function complete(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->appointmentService->complete($id, $data['finalPrice']);
            $this->adminLogService->log($this->getUser(), 'complete', 'appointment', $id, "RDV #{$id} terminé ({$data['finalPrice']}€)");

            // Email + Push client
            try {
                $dateFR = (new \DateTimeImmutable($dto->date))->format('d/m/Y');
                $this->mailerService->send(
                    $dto->clientEmail,
                    'Merci pour votre visite !',
                    $this->mailerService->template(
                        'Rendez-vous termine',
                        sprintf(
                            '<p>Bonjour %s,</p><p>Votre rendez-vous <strong>%s</strong> du %s est termine.</p><p>Merci pour votre confiance ! N\'hesitez pas a reprendre rendez-vous.</p><p style="margin-top:20px;"><a href="https://tiffany.garagepro.be/#reservation" style="background:#b8860b;color:#fff;padding:12px 30px;text-decoration:none;border-radius:5px;">REPRENDRE RENDEZ-VOUS</a></p>',
                            $dto->clientName,
                            $dto->serviceName,
                            $dateFR,
                        ),
                    ),
                );
            } catch (\Throwable) {}
            $this->sendAppointmentPush($dto, 'complete');

            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/appointments/{id}/cancel', methods: ['PATCH'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $dto = $this->appointmentService->cancel($id);

            // Always send email + push to client on cancellation
            $dateFR = (new \DateTimeImmutable($dto->date))->format('d/m/Y');
            try {
                $this->mailerService->sendAppointmentCancelled(
                    $dto->clientEmail,
                    $dto->clientName,
                    $dto->serviceName,
                    $dateFR,
                    $dto->timeSlot,
                );
            } catch (\Throwable) {}

            $this->adminLogService->log($this->getUser(), 'cancel', 'appointment', $id, "RDV #{$id} annulé");
            $this->sendAppointmentPush($dto, 'cancel');
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/appointments/{id}/reschedule', methods: ['PATCH'])]
    public function reschedule(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        try {
            $dto = $this->appointmentService->reschedule(
                $id,
                $data['date'],
                $data['timeSlot'],
            );

            // Send reschedule email
            $dateFR = (new \DateTimeImmutable($dto->date))->format('d/m/Y');
            $this->mailerService->sendAppointmentRescheduled(
                $dto->clientEmail,
                $dto->clientName,
                $dto->serviceName,
                $dateFR,
                $dto->timeSlot,
            );

            $this->sendAppointmentPush($dto, 'reschedule');
            return $this->json($dto);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/appointments/{id}/edit', methods: ['PUT'])]
    public function adminEdit(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $appointment = $this->appointmentService->findEntityById($id);
        if ($appointment === null) {
            return $this->json(['error' => 'Rendez-vous introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Update service
        if (isset($data['serviceId'])) {
            $service = $this->serviceRepository->findById((int) $data['serviceId']);
            if ($service === null) {
                return $this->json(['error' => 'Prestation introuvable.'], Response::HTTP_BAD_REQUEST);
            }
            $appointment->setService($service);
        }

        // Update client
        if (isset($data['clientId'])) {
            $user = $this->userRepository->findById((int) $data['clientId']);
            if ($user === null) {
                return $this->json(['error' => 'Client introuvable.'], Response::HTTP_BAD_REQUEST);
            }
            $appointment->setUser($user);
        }

        // Update date/time
        if (isset($data['date'])) {
            $appointment->setDate(new \DateTimeImmutable($data['date']));
        }
        if (isset($data['timeSlot'])) {
            $appointment->setTimeSlot($data['timeSlot']);
        }

        // Update family member
        if (array_key_exists('familyMemberId', $data)) {
            if ($data['familyMemberId']) {
                $member = $this->familyMemberRepository->findById((int) $data['familyMemberId']);
                $appointment->setFamilyMember($member);
            } else {
                $appointment->setFamilyMember(null);
            }
        }

        // Update comment
        if (array_key_exists('comment', $data)) {
            $appointment->setComment($data['comment']);
        }

        $this->appointmentService->saveEntity($appointment);
        $this->adminLogService->log($this->getUser(), 'update', 'appointment', $id, "RDV #{$id} modifié");

        return $this->json(\App\Application\DTO\AppointmentDTO::fromEntity($appointment));
    }

    #[Route('/api/admin/appointments/{id}', methods: ['DELETE'])]
    public function deleteAppointment(int $id): JsonResponse
    {
        try {
            $this->adminLogService->log($this->getUser(), 'delete', 'appointment', $id, "RDV #{$id} supprimé");
            $this->appointmentService->delete($id);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/api/admin/clients', methods: ['GET'])]
    public function listClients(): JsonResponse
    {
        $users = $this->userRepository->findAll();
        $result = [];

        foreach ($users as $user) {
            $entry = [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail(),
                'phone' => $user->getPhone(),
                'familyMembers' => [],
            ];

            $members = $this->familyMemberRepository->findByOwner($user->getId());
            foreach ($members as $m) {
                $entry['familyMembers'][] = [
                    'id' => $m->getId(),
                    'fullName' => $m->getFullName(),
                    'relationship' => $m->getRelationship(),
                ];
            }

            $result[] = $entry;
        }

        return $this->json($result);
    }

    #[Route('/api/admin/appointments/create', methods: ['POST'])]
    public function adminCreate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $serviceId = (int) ($data['serviceId'] ?? 0);
        $date = $data['date'] ?? '';
        $timeSlot = $data['timeSlot'] ?? '';
        $blockSlot = $data['blockSlot'] ?? true;
        $clientId = isset($data['clientId']) ? (int) $data['clientId'] : null;
        $familyMemberId = isset($data['familyMemberId']) ? (int) $data['familyMemberId'] : null;
        $walkInName = $data['walkInName'] ?? null;
        $walkInPhone = $data['walkInPhone'] ?? null;
        $comment = $data['comment'] ?? null;

        if (!$serviceId || !$date || !$timeSlot) {
            return $this->json(['error' => 'Prestation, date et créneau requis.'], Response::HTTP_BAD_REQUEST);
        }

        $service = $this->serviceRepository->findById($serviceId);
        if ($service === null) {
            return $this->json(['error' => 'Prestation introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        // If no client selected, create a walk-in appointment under the admin user
        if ($clientId) {
            $user = $this->userRepository->findById($clientId);
            if ($user === null) {
                return $this->json(['error' => 'Client introuvable.'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            // Walk-in: use admin's own account as placeholder
            /** @var User $user */
            $user = $this->getUser();
            if ($walkInName) {
                $comment = ($comment ? $comment . ' — ' : '') . 'Client sans compte : ' . $walkInName . ($walkInPhone ? ' (' . $walkInPhone . ')' : '');
            }
        }

        try {
            $dto = $this->appointmentService->book(
                $user,
                $serviceId,
                $date,
                $timeSlot,
                null,
                $comment,
                $familyMemberId,
            );

            // If blockSlot is false, cancel immediately to free the slot but keep record
            // Actually: blockSlot=true means keep slot occupied (default behavior)
            // blockSlot=false means don't block — we skip this, the slot stays available for others
            // For blockSlot=false, we mark as completed immediately so slot logic doesn't block it
            if (!$blockSlot) {
                $this->appointmentService->complete($dto->id, $service->getPrice() ?? '0');
                $dto = $this->appointmentService->findById($dto->id);
            }

            // Send confirmation to client if they have an account
            if ($clientId) {
                $dateFR = (new \DateTimeImmutable($date))->format('d/m/Y');
                $this->mailerService->sendAppointmentConfirmed(
                    $user->getEmail(),
                    $user->getFullName(),
                    $dto->serviceName,
                    $dateFR,
                    $timeSlot,
                );

                $phone = $user->getPhone();
                if ($phone) {
                    $this->smsService->send(
                        $phone,
                        sprintf(
                            'Bonjour %s, votre RDV du %s a %s pour %s est confirme. Les Creations de Tiffany - 0497 92 60 03',
                            $user->getFullName(),
                            $dateFR,
                            $timeSlot,
                            $dto->serviceName,
                        ),
                    );
                }
            }

            // Push notification to admin for new booking
            try {
                $adminUsers = $this->userRepository->findAll();
                foreach ($adminUsers as $au) {
                    if (in_array('ROLE_ADMIN', $au->getRoles())) {
                        $this->pushService->sendToUser(
                            $au->getId(),
                            'Nouveau rendez-vous',
                            sprintf('%s - %s le %s à %s', $user->getFullName(), $dto->serviceName, $dateFR, $dto->timeSlot),
                            '/',
                            'new-appointment-' . $dto->id,
                        );
                    }
                }
            } catch (\Throwable) {}

            return $this->json($dto, Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function sendAppointmentPush(\App\Application\DTO\AppointmentDTO $dto, string $action): void
    {
        try {
            $appointment = $this->appointmentService->findEntityById($dto->id);
            if (!$appointment) return;

            $userId = $appointment->getUser()->getId();
            $dateFR = (new \DateTimeImmutable($dto->date))->format('d/m/Y');

            $messages = [
                'confirm' => ['Rendez-vous confirmé', "Votre RDV du {$dateFR} à {$dto->timeSlot} pour {$dto->serviceName} est confirmé."],
                'complete' => ['Rendez-vous terminé', "Merci pour votre visite ! Votre RDV {$dto->serviceName} est terminé."],
                'cancel' => ['Rendez-vous annulé', "Votre RDV du {$dateFR} à {$dto->timeSlot} pour {$dto->serviceName} a été annulé."],
                'reschedule' => ['Rendez-vous déplacé', "Votre RDV pour {$dto->serviceName} est déplacé au {$dateFR} à {$dto->timeSlot}."],
            ];

            [$title, $body] = $messages[$action] ?? ['Rendez-vous', 'Mise à jour de votre rendez-vous.'];

            $this->pushService->sendToUser($userId, $title, $body, '/', "appointment-{$action}-{$dto->id}");
        } catch (\Throwable) {
            // Silent — don't block admin action
        }
    }
}
