<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StatsController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/api/admin/stats', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $conn = $this->em->getConnection();

        // Appointments this month
        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $monthEnd = (new \DateTimeImmutable('last day of this month'))->format('Y-m-d');

        $appointmentsMonth = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM appointments WHERE date >= ? AND date <= ?',
            [$monthStart, $monthEnd]
        );

        $appointmentsWeek = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM appointments WHERE date >= ? AND date <= ?',
            [(new \DateTimeImmutable('monday this week'))->format('Y-m-d'), (new \DateTimeImmutable('sunday this week'))->format('Y-m-d')]
        );

        // Revenue this month (completed appointments)
        $revenueMonth = (float) ($conn->fetchOne(
            'SELECT COALESCE(SUM(final_price), 0) FROM appointments WHERE date >= ? AND date <= ? AND status = ?',
            [$monthStart, $monthEnd, 'completed']
        ) ?? 0);

        // Total clients
        $totalClients = (int) $conn->fetchOne('SELECT COUNT(*) FROM users');

        // New clients this month
        $newClientsMonth = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM users WHERE created_at >= ?',
            [$monthStart]
        );

        // Top services (last 3 months)
        $topServices = $conn->fetchAllAssociative(
            'SELECT s.name, COUNT(a.id) as count FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.date >= ? GROUP BY s.id, s.name ORDER BY count DESC LIMIT 5',
            [(new \DateTimeImmutable('-3 months'))->format('Y-m-d')]
        );

        // Appointments per month (last 6 months)
        $monthlyStats = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = new \DateTimeImmutable("-{$i} months");
            $ms = $d->modify('first day of this month')->format('Y-m-d');
            $me = $d->modify('last day of this month')->format('Y-m-d');
            $count = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM appointments WHERE date >= ? AND date <= ?',
                [$ms, $me]
            );
            $revenue = (float) ($conn->fetchOne(
                'SELECT COALESCE(SUM(final_price), 0) FROM appointments WHERE date >= ? AND date <= ? AND status = ?',
                [$ms, $me, 'completed']
            ) ?? 0);
            $monthlyStats[] = [
                'month' => $d->format('M Y'),
                'label' => $d->format('m/Y'),
                'appointments' => $count,
                'revenue' => $revenue,
            ];
        }

        // Status breakdown
        $statusBreakdown = $conn->fetchAllAssociative(
            'SELECT status, COUNT(*) as count FROM appointments GROUP BY status'
        );

        return $this->json([
            'appointmentsWeek' => $appointmentsWeek,
            'appointmentsMonth' => $appointmentsMonth,
            'revenueMonth' => $revenueMonth,
            'totalClients' => $totalClients,
            'newClientsMonth' => $newClientsMonth,
            'topServices' => $topServices,
            'monthlyStats' => $monthlyStats,
            'statusBreakdown' => $statusBreakdown,
        ]);
    }
}
