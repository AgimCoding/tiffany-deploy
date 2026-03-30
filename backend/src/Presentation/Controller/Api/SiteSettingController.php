<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\MailerService;
use App\Application\Service\SiteSettingService;
use App\Application\Service\SmsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SiteSettingController extends AbstractController
{
    public function __construct(
        private readonly SiteSettingService $settingService,
        private readonly MailerService $mailerService,
        private readonly SmsService $smsService,
    ) {
    }

    #[Route('/api/public/settings', methods: ['GET'])]
    public function getAll(): JsonResponse
    {
        return $this->json($this->settingService->getAll());
    }

    #[Route('/api/public/settings/{category}', methods: ['GET'])]
    public function getByCategory(string $category): JsonResponse
    {
        return $this->json($this->settingService->getByCategory($category));
    }

    #[Route('/api/admin/settings', methods: ['PUT'])]
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->settingService->bulkUpdate($data);

        return $this->json(['success' => true]);
    }

    #[Route('/api/admin/smtp/test', methods: ['POST'])]
    public function testSmtp(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        if (!$to) {
            return $this->json(['error' => 'Email destinataire requis.'], Response::HTTP_BAD_REQUEST);
        }

        $ok = $this->mailerService->send($to, 'Test SMTP - Les Créations de Tiffany', '<p>Ce mail confirme que la configuration SMTP fonctionne correctement.</p>');

        return $this->json(['success' => $ok, 'message' => $ok ? 'Email envoyé.' : 'Échec de l\'envoi. Vérifiez la configuration SMTP.']);
    }

    #[Route('/api/admin/sms/balance', methods: ['GET'])]
    public function smsBalance(): JsonResponse
    {
        $balance = $this->smsService->getBalance();

        return $this->json($balance ?? ['error' => 'Impossible de récupérer le solde. Vérifiez la clé API.']);
    }

    #[Route('/api/admin/sms/test', methods: ['POST'])]
    public function testSms(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $to = $data['to'] ?? null;
        if (!$to) {
            return $this->json(['error' => 'Numéro requis.'], Response::HTTP_BAD_REQUEST);
        }

        $ok = $this->smsService->send($to, 'Test sms pour Tiffany');

        return $this->json(['success' => $ok, 'message' => $ok ? 'SMS envoyé.' : 'Échec de l\'envoi. Vérifiez la clé API et le numéro.']);
    }

    #[Route('/api/maintenance/status', methods: ['GET'])]
    public function maintenanceStatus(): JsonResponse
    {
        $value = $this->settingService->get('maintenance_mode');

        return $this->json(['enabled' => $value === '1']);
    }

    #[Route('/api/maintenance/toggle', methods: ['POST'])]
    public function maintenanceToggle(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $enable = $data['enabled'] ?? false;
        $this->settingService->set('maintenance_mode', $enable ? '1' : '0', 'system');

        return $this->json(['enabled' => (bool) $enable]);
    }
}
