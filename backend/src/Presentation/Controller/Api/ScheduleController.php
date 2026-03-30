<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\SiteSettingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ScheduleController extends AbstractController
{
    private const DEFAULT_WEEKLY = '{"lundi":{"active":false,"slots":[]},"mardi":{"active":true,"slots":["09:00","09:30","10:00","10:30","11:00","11:30","14:00","14:30","15:00","15:30","16:00","16:30","17:00"]},"mercredi":{"active":true,"slots":["09:00","09:30","10:00","10:30","11:00","11:30","14:00","14:30","15:00","15:30","16:00","16:30","17:00"]},"jeudi":{"active":true,"slots":["09:00","09:30","10:00","10:30","11:00","11:30","14:00","14:30","15:00","15:30","16:00","16:30","17:00"]},"vendredi":{"active":true,"slots":["09:00","09:30","10:00","10:30","11:00","11:30","14:00","14:30","15:00","15:30","16:00","16:30","17:00"]},"samedi":{"active":true,"slots":["09:00","09:30","10:00","10:30","11:00","11:30","14:00","14:30","15:00","15:30","16:00","16:30","17:00"]},"dimanche":{"active":false,"slots":[]}}';

    public function __construct(private readonly SiteSettingService $settingService)
    {
    }

    #[Route('/api/public/schedule', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $schedule = $this->settingService->getByCategory('schedule');

        return $this->json([
            'schedule_days' => $schedule['schedule_days'] ?? 'Mardi - Samedi',
            'schedule_hours' => $schedule['schedule_hours'] ?? 'Sur rendez-vous',
            'schedule_note' => $schedule['schedule_note'] ?? 'Flexibilité selon vos besoins',
            'schedule_weekly' => $schedule['schedule_weekly'] ?? self::DEFAULT_WEEKLY,
        ]);
    }

    #[Route('/api/admin/schedule', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        foreach ($data as $key => $value) {
            $this->settingService->set($key, $value, 'schedule');
        }

        return $this->json(['success' => true]);
    }
}
