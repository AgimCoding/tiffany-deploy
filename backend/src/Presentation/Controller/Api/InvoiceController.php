<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Domain\Entity\User;
use App\Domain\Port\OrderRepositoryInterface;
use App\Application\Service\SiteSettingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InvoiceController extends AbstractController
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SiteSettingService $settingService,
    ) {
    }

    #[Route('/api/orders/{id}/invoice', methods: ['GET'])]
    public function invoice(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $order = $this->orderRepository->findById($id);
        if ($order === null || $order->getUser()->getId() !== $user->getId()) {
            return new Response('Commande introuvable.', Response::HTTP_NOT_FOUND);
        }

        $phone = $this->settingService->get('header_phone') ?: '0497 92 60 03';
        $salonName = 'Les Créations de Tiffany';
        $address = "Impasse de l'épi, 4280 Hannut";
        $email = $this->settingService->get('smtp_from_email') ?: 'contact@lescreationsdetiffany.com';
        $tva = $this->settingService->get('tva_number') ?: '';

        $orderDate = $order->getCreatedAt()->format('d/m/Y');
        $invoiceNumber = 'TIFF-' . str_pad((string) $order->getId(), 5, '0', STR_PAD_LEFT);

        $itemsHtml = '';
        foreach ($order->getItems() as $item) {
            $lineTotal = number_format((float) $item->getPrice() * $item->getQuantity(), 2, ',', ' ');
            $unitPrice = number_format((float) $item->getPrice(), 2, ',', ' ');
            $itemsHtml .= '<tr>
                <td style="padding:12px 15px;border-bottom:1px solid #eee;">' . htmlspecialchars($item->getProduct()->getName()) . '</td>
                <td style="padding:12px 15px;border-bottom:1px solid #eee;text-align:center;">' . $item->getQuantity() . '</td>
                <td style="padding:12px 15px;border-bottom:1px solid #eee;text-align:right;">' . $unitPrice . ' €</td>
                <td style="padding:12px 15px;border-bottom:1px solid #eee;text-align:right;font-weight:600;">' . $lineTotal . ' €</td>
            </tr>';
        }

        $total = number_format((float) $order->getTotal(), 2, ',', ' ');
        $clientName = htmlspecialchars($user->getFullName());
        $clientEmail = htmlspecialchars($user->getEmail());
        $clientPhone = htmlspecialchars($user->getPhone() ?: '');

        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture ' . $invoiceNumber . '</title>
<style>
    @import url("https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Cormorant+Garamond:wght@300;400;600&display=swap");
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Montserrat", sans-serif; color: #1a1a1a; background: #f5f5f5; }
    .invoice-page { max-width: 800px; margin: 20px auto; background: #fff; padding: 50px; box-shadow: 0 2px 20px rgba(0,0,0,0.08); }
    .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; padding-bottom: 30px; border-bottom: 2px solid #1a1a1a; }
    .salon-info h1 { font-family: "Cormorant Garamond", serif; font-size: 1.8rem; font-weight: 300; letter-spacing: 4px; color: #1a1a1a; }
    .salon-info p { font-size: 0.8rem; color: #888; margin-top: 4px; line-height: 1.6; }
    .invoice-meta { text-align: right; }
    .invoice-meta h2 { font-size: 1.5rem; font-weight: 300; letter-spacing: 3px; text-transform: uppercase; color: #b8860b; margin-bottom: 10px; }
    .invoice-meta p { font-size: 0.85rem; color: #666; line-height: 1.8; }
    .invoice-meta strong { color: #1a1a1a; }
    .client-info { background: #fafafa; padding: 20px 25px; margin-bottom: 30px; border-left: 3px solid #b8860b; }
    .client-info h3 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; color: #888; margin-bottom: 10px; }
    .client-info p { font-size: 0.9rem; line-height: 1.7; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    thead th { background: #1a1a1a; color: #fff; padding: 12px 15px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 500; }
    thead th:first-child { text-align: left; }
    thead th:nth-child(2) { text-align: center; }
    thead th:nth-child(3), thead th:nth-child(4) { text-align: right; }
    .total-row td { padding: 15px; font-size: 1.1rem; border-top: 2px solid #1a1a1a; }
    .total-row td:last-child { font-weight: 700; font-size: 1.2rem; color: #b8860b; }
    .invoice-footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; font-size: 0.75rem; color: #999; }
    .invoice-footer p { margin: 3px 0; }
    .print-btn { display: block; margin: 20px auto; padding: 12px 40px; background: #1a1a1a; color: #fff; border: none; cursor: pointer; font-family: "Montserrat", sans-serif; font-size: 0.85rem; letter-spacing: 2px; text-transform: uppercase; transition: background 0.3s; }
    .print-btn:hover { background: #333; }
    @media print {
        body { background: #fff; }
        .invoice-page { box-shadow: none; margin: 0; padding: 30px; }
        .print-btn { display: none; }
    }
</style>
</head>
<body>
<div class="invoice-page">
    <div class="invoice-header">
        <div class="salon-info">
            <h1>' . htmlspecialchars($salonName) . '</h1>
            <p>' . htmlspecialchars($address) . '<br>' . htmlspecialchars($phone) . '<br>' . htmlspecialchars($email) . '</p>
            ' . ($tva ? '<p style="margin-top:5px;font-size:0.75rem;">TVA : ' . htmlspecialchars($tva) . '</p>' : '') . '
        </div>
        <div class="invoice-meta">
            <h2>Facture</h2>
            <p><strong>N° ' . $invoiceNumber . '</strong><br>Date : ' . $orderDate . '</p>
        </div>
    </div>

    <div class="client-info">
        <h3>Facturé à</h3>
        <p><strong>' . $clientName . '</strong><br>' . $clientEmail . ($clientPhone ? '<br>' . $clientPhone : '') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Qté</th>
                <th>Prix unit.</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            ' . $itemsHtml . '
            <tr class="total-row">
                <td colspan="3" style="text-align:right;font-weight:600;">TOTAL</td>
                <td style="text-align:right;">' . $total . ' €</td>
            </tr>
        </tbody>
    </table>

    <div class="invoice-footer">
        <p>' . htmlspecialchars($salonName) . ' — ' . htmlspecialchars($address) . '</p>
        <p>' . htmlspecialchars($phone) . ' — ' . htmlspecialchars($email) . '</p>
        <p style="margin-top:10px;">Merci pour votre confiance !</p>
    </div>
</div>
<button class="print-btn" onclick="window.print()">Imprimer / Sauvegarder en PDF</button>
</body>
</html>';

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
