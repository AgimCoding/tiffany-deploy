<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\MailerService;
use App\Domain\Entity\GiftCard;
use App\Domain\Entity\User;
use App\Domain\Port\GiftCardRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GiftCardController extends AbstractController
{
    public function __construct(
        private readonly GiftCardRepositoryInterface $giftCardRepo,
        private readonly MailerService $mailerService,
    ) {}

    /** Purchase a gift card (authenticated user). */
    #[Route('/api/gift-cards', methods: ['POST'])]
    public function purchase(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $amount = $data['amount'] ?? '';
        $recipientName = $data['recipientName'] ?? '';
        $recipientEmail = $data['recipientEmail'] ?? '';
        $message = $data['message'] ?? null;

        if (!$amount || (float) $amount < 10 || (float) $amount > 500) {
            return $this->json(['error' => 'Montant entre 10 et 500 euros.'], Response::HTTP_BAD_REQUEST);
        }
        if (!$recipientName || !$recipientEmail) {
            return $this->json(['error' => 'Nom et email du destinataire requis.'], Response::HTTP_BAD_REQUEST);
        }

        $card = new GiftCard(
            number_format((float) $amount, 2, '.', ''),
            $recipientName,
            $recipientEmail,
            $message,
            $user,
        );

        $this->giftCardRepo->save($card);

        // Send gift card email to recipient
        $this->mailerService->send(
            $recipientEmail,
            'Vous avez recu une carte cadeau !',
            sprintf(
                '<div style="text-align:center;font-family:Georgia,serif;max-width:500px;margin:0 auto;padding:40px 20px;">
                    <h1 style="color:#b8860b;font-size:1.8rem;margin-bottom:5px;">CARTE CADEAU</h1>
                    <p style="color:#666;font-size:0.9rem;">Les Creations de Tiffany</p>
                    <div style="background:#1a1a1a;color:#fff;padding:30px;margin:25px 0;border-radius:12px;">
                        <p style="font-size:2.5rem;font-weight:bold;color:#b8860b;margin:0;">%s&euro;</p>
                        <p style="font-size:1.2rem;letter-spacing:4px;margin:15px 0 0;font-family:monospace;">%s</p>
                    </div>
                    <p style="color:#333;">De la part de <strong>%s</strong></p>
                    %s
                    <p style="color:#888;font-size:0.8rem;margin-top:20px;">Valable 1 an. Presentez ce code lors de votre visite.</p>
                </div>',
                number_format((float) $card->getAmount(), 0),
                $card->getCode(),
                $user->getFullName(),
                $message ? '<p style="color:#555;font-style:italic;">&laquo; ' . htmlspecialchars($message) . ' &raquo;</p>' : '',
            ),
        );

        return $this->json([
            'id' => $card->getId(),
            'code' => $card->getCode(),
            'amount' => $card->getAmount(),
            'recipientName' => $card->getRecipientName(),
            'recipientEmail' => $card->getRecipientEmail(),
            'expiresAt' => $card->getExpiresAt()->format('Y-m-d'),
        ], Response::HTTP_CREATED);
    }

    /** Check a gift card balance (public). */
    #[Route('/api/public/gift-cards/check', methods: ['GET'])]
    public function check(Request $request): JsonResponse
    {
        $code = strtoupper($request->query->get('code', ''));
        if (!$code) {
            return $this->json(['error' => 'Code requis.'], Response::HTTP_BAD_REQUEST);
        }

        $card = $this->giftCardRepo->findByCode($code);
        if (!$card) {
            return $this->json(['error' => 'Carte cadeau introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'code' => $card->getCode(),
            'amount' => $card->getAmount(),
            'balance' => $card->getBalance(),
            'status' => $card->getStatus(),
            'valid' => $card->isValid(),
            'expiresAt' => $card->getExpiresAt()->format('Y-m-d'),
        ]);
    }

    /** List my purchased gift cards. */
    #[Route('/api/gift-cards', methods: ['GET'])]
    public function listMine(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $cards = $this->giftCardRepo->findByPurchaser($user->getId());

        return $this->json(array_map(fn(GiftCard $c) => [
            'id' => $c->getId(),
            'code' => $c->getCode(),
            'amount' => $c->getAmount(),
            'balance' => $c->getBalance(),
            'recipientName' => $c->getRecipientName(),
            'recipientEmail' => $c->getRecipientEmail(),
            'status' => $c->getStatus(),
            'createdAt' => $c->getCreatedAt()->format('Y-m-d'),
            'expiresAt' => $c->getExpiresAt()->format('Y-m-d'),
        ], $cards));
    }

    /** Admin: list all gift cards. */
    #[Route('/api/admin/gift-cards', methods: ['GET'])]
    public function adminListAll(): JsonResponse
    {
        $cards = $this->giftCardRepo->findAll();

        return $this->json(array_map(fn(GiftCard $c) => [
            'id' => $c->getId(),
            'code' => $c->getCode(),
            'amount' => $c->getAmount(),
            'balance' => $c->getBalance(),
            'recipientName' => $c->getRecipientName(),
            'recipientEmail' => $c->getRecipientEmail(),
            'purchasedBy' => $c->getPurchasedBy()?->getFullName(),
            'status' => $c->getStatus(),
            'valid' => $c->isValid(),
            'createdAt' => $c->getCreatedAt()->format('Y-m-d'),
            'expiresAt' => $c->getExpiresAt()->format('Y-m-d'),
        ], $cards));
    }
}
