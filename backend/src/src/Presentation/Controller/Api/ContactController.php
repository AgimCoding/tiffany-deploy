<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(private readonly MailerService $mailerService)
    {
    }

    #[Route('/api/public/contact', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $message = trim($data['message'] ?? '');

        if (!$name || !$email || !$message) {
            return $this->json(['error' => 'Veuillez remplir tous les champs obligatoires.'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Adresse email invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $htmlBody = '
            <p><strong>Nouveau message depuis le site web</strong></p>
            <table style="border-collapse:collapse;margin:15px 0;">
                <tr><td style="padding:8px 15px 8px 0;color:#888;vertical-align:top;">Nom</td><td style="padding:8px 0;font-weight:600;">' . htmlspecialchars($name) . '</td></tr>
                <tr><td style="padding:8px 15px 8px 0;color:#888;vertical-align:top;">Email</td><td style="padding:8px 0;"><a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></td></tr>
                ' . ($phone ? '<tr><td style="padding:8px 15px 8px 0;color:#888;vertical-align:top;">Téléphone</td><td style="padding:8px 0;"><a href="tel:' . htmlspecialchars($phone) . '">' . htmlspecialchars($phone) . '</a></td></tr>' : '') . '
                <tr><td style="padding:8px 15px 8px 0;color:#888;vertical-align:top;">Message</td><td style="padding:8px 0;white-space:pre-line;">' . htmlspecialchars($message) . '</td></tr>
            </table>
        ';

        $adminEmail = 'contact@lescreationsdetiffany.com';
        $sent = $this->mailerService->send(
            $adminEmail,
            'Nouveau message de ' . $name . ' — Les Créations de Tiffany',
            $htmlBody
        );

        if (!$sent) {
            return $this->json(['error' => 'Impossible d\'envoyer le message. Veuillez nous appeler directement.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['success' => true, 'message' => 'Message envoyé avec succès.']);
    }
}
