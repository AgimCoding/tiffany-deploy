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

        // ─── Orders / Sales stats ───

        $ordersMonth = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ?',
            [$monthStart, (new \DateTimeImmutable('first day of next month'))->format('Y-m-d')]
        );

        $ordersWeek = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ?',
            [(new \DateTimeImmutable('monday this week'))->format('Y-m-d'), (new \DateTimeImmutable('monday next week'))->format('Y-m-d')]
        );

        $salesRevenueMonth = (float) ($conn->fetchOne(
            'SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at >= ? AND created_at < ? AND status IN (?, ?, ?)',
            [$monthStart, (new \DateTimeImmutable('first day of next month'))->format('Y-m-d'), 'confirmed', 'ready', 'completed']
        ) ?? 0);

        $orderStatusBreakdown = $conn->fetchAllAssociative(
            'SELECT status, COUNT(*) as count FROM orders GROUP BY status'
        );

        // Top products (last 3 months)
        $topProducts = $conn->fetchAllAssociative(
            'SELECT p.name, SUM(oi.quantity) as totalQty, SUM(oi.price * oi.quantity) as totalRevenue
             FROM order_items oi
             JOIN products p ON oi.product_id = p.id
             JOIN orders o ON oi.order_id = o.id
             WHERE o.created_at >= ? AND o.status != ?
             GROUP BY p.id, p.name
             ORDER BY totalQty DESC LIMIT 5',
            [(new \DateTimeImmutable('-3 months'))->format('Y-m-d'), 'cancelled']
        );

        // Monthly sales (6 months)
        $monthlySales = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = new \DateTimeImmutable("-{$i} months");
            $ms = $d->modify('first day of this month')->format('Y-m-d');
            $me = $d->modify('first day of next month')->format('Y-m-d');
            $oCount = (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ?',
                [$ms, $me]
            );
            $oRevenue = (float) ($conn->fetchOne(
                'SELECT COALESCE(SUM(total), 0) FROM orders WHERE created_at >= ? AND created_at < ? AND status IN (?, ?, ?)',
                [$ms, $me, 'confirmed', 'ready', 'completed']
            ) ?? 0);
            $monthlySales[] = [
                'month' => $d->format('M Y'),
                'label' => $d->format('m/Y'),
                'orders' => $oCount,
                'revenue' => $oRevenue,
            ];
        }

        return $this->json([
            'appointmentsWeek' => $appointmentsWeek,
            'appointmentsMonth' => $appointmentsMonth,
            'revenueMonth' => $revenueMonth,
            'totalClients' => $totalClients,
            'newClientsMonth' => $newClientsMonth,
            'topServices' => $topServices,
            'monthlyStats' => $monthlyStats,
            'statusBreakdown' => $statusBreakdown,
            'ordersWeek' => $ordersWeek,
            'ordersMonth' => $ordersMonth,
            'salesRevenueMonth' => $salesRevenueMonth,
            'orderStatusBreakdown' => $orderStatusBreakdown,
            'topProducts' => $topProducts,
            'monthlySales' => $monthlySales,
        ]);
    }
}
