<?php

declare(strict_types=1);

namespace App\Application\Service;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

final class MailerService
{
    public function __construct(private readonly SiteSettingService $settings)
    {
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $host = $this->settings->get('smtp_host');
        $port = $this->settings->get('smtp_port') ?: '587';
        $user = $this->settings->get('smtp_user');
        $pass = $this->settings->get('smtp_password');
        $fromEmail = $this->settings->get('smtp_from_email') ?: $user;
        $fromName = $this->settings->get('smtp_from_name') ?: 'Les Créations de Tiffany';

        if (!$host || !$user || !$pass) {
            return false;
        }

        try {
            $dsn = sprintf(
                'smtp://%s:%s@%s:%s',
                urlencode($user),
                urlencode($pass),
                $host,
                $port,
            );
            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from(new Address($fromEmail, $fromName))
                ->to($to)
                ->subject($subject)
                ->html($htmlBody);

            $mailer->send($email);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function sendRegistration(string $to, string $fullName): bool
    {
        $subject = 'Bienvenue chez Les Créations de Tiffany';
        $html = $this->template(
            'Bienvenue ' . htmlspecialchars($fullName) . ' !',
            '<p>Merci de vous être inscrit(e) sur notre site.</p>
            <p>Vous pouvez désormais prendre rendez-vous en ligne, gérer vos membres de famille et suivre vos commandes.</p>
            <p>À très bientôt au salon !</p>'
        );

        return $this->send($to, $subject, $html);
    }

    public function sendAppointmentConfirmed(string $to, string $clientName, string $serviceName, string $date, string $timeSlot): bool
    {
        $subject = 'Rendez-vous confirmé - Les Créations de Tiffany';
        $html = $this->template(
            'Rendez-vous confirmé',
            '<p>Bonjour ' . htmlspecialchars($clientName) . ',</p>
            <p>Votre rendez-vous a été confirmé :</p>
            <table style="margin:15px 0;border-collapse:collapse;">
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Prestation</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($serviceName) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Date</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($date) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Heure</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($timeSlot) . '</td></tr>
            </table>
            <p>À bientôt !</p>'
        );

        return $this->send($to, $subject, $html);
    }

    public function sendAppointmentRescheduled(string $to, string $clientName, string $serviceName, string $newDate, string $newTimeSlot): bool
    {
        $subject = 'Rendez-vous déplacé - Les Créations de Tiffany';
        $html = $this->template(
            'Rendez-vous déplacé',
            '<p>Bonjour ' . htmlspecialchars($clientName) . ',</p>
            <p>Votre rendez-vous a été déplacé aux nouvelles dates suivantes :</p>
            <table style="margin:15px 0;border-collapse:collapse;">
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Prestation</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($serviceName) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Nouvelle date</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($newDate) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Nouvelle heure</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($newTimeSlot) . '</td></tr>
            </table>
            <p>Si ce créneau ne vous convient pas, n\'hésitez pas à nous contacter.</p>'
        );

        return $this->send($to, $subject, $html);
    }

    public function sendAppointmentCancelled(string $to, string $clientName, string $serviceName, string $date, string $timeSlot): bool
    {
        $subject = 'Rendez-vous annulé - Les Créations de Tiffany';
        $html = $this->template(
            'Rendez-vous annulé',
            '<p>Bonjour ' . htmlspecialchars($clientName) . ',</p>
            <p>Nous sommes au regret de vous informer que votre rendez-vous a été annulé :</p>
            <table style="margin:15px 0;border-collapse:collapse;">
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Prestation</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($serviceName) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Date</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($date) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Heure</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($timeSlot) . '</td></tr>
            </table>
            <p>N\'hésitez pas à reprendre rendez-vous sur notre site ou à nous contacter.</p>'
        );

        return $this->send($to, $subject, $html);
    }

    public function sendOrderConfirmed(string $to, string $clientName, string $orderId, string $total, array $items = []): bool
    {
        $subject = 'Commande confirmée - Les Créations de Tiffany';
        $html = $this->template(
            'Commande confirmée',
            '<p>Bonjour ' . htmlspecialchars($clientName) . ',</p>
            <p>Votre commande <strong>#' . htmlspecialchars($orderId) . '</strong> a été enregistrée.</p>
            ' . $this->orderItemsTable($items) . '
            <p style="font-size:1.2rem;font-weight:600;margin:15px 0;">Total : ' . htmlspecialchars($total) . ' €</p>
            <p>Vous pourrez récupérer votre commande au salon.</p>
            <p>Merci pour votre confiance !</p>'
        );

        return $this->send($to, $subject, $html);
    }

    public function sendOrderStatusUpdate(string $to, string $clientName, string $orderId, string $total, string $status, array $items = []): bool
    {
        $statusLabels = [
            'confirmed' => 'confirmée',
            'ready' => 'prête à retirer',
            'completed' => 'livrée',
            'cancelled' => 'annulée',
        ];
        $label = $statusLabels[$status] ?? $status;
        $subject = "Commande #{$orderId} {$label} - Les Créations de Tiffany";

        $extra = '';
        if ($status === 'ready') {
            $extra = '<p style="background:#f0f0f0;padding:15px;margin:15px 0;">Votre commande est prête ! Vous pouvez venir la retirer au salon.</p>';
        } elseif ($status === 'cancelled') {
            $extra = '<p>Si vous avez des questions, n\'hésitez pas à nous contacter.</p>';
        }

        $html = $this->template(
            'Commande #' . htmlspecialchars($orderId) . ' ' . $label,
            '<p>Bonjour ' . htmlspecialchars($clientName) . ',</p>
            <p>Votre commande <strong>#' . htmlspecialchars($orderId) . '</strong> est désormais <strong>' . $label . '</strong>.</p>
            ' . $this->orderItemsTable($items) . '
            <p style="font-size:1.1rem;margin:15px 0;">Total : ' . htmlspecialchars($total) . ' €</p>
            ' . $extra . '
            <p>Merci pour votre confiance !</p>'
        );

        return $this->send($to, $subject, $html);
    }

    public function sendAppointmentReminder(string $to, string $clientName, string $serviceName, string $date, string $timeSlot): bool
    {
        $subject = 'Rappel rendez-vous demain - Les Créations de Tiffany';
        $html = $this->template(
            'Rappel de votre rendez-vous',
            '<p>Bonjour ' . htmlspecialchars($clientName) . ',</p>
            <p>Nous vous rappelons votre rendez-vous <strong>demain</strong> :</p>
            <table style="margin:15px 0;border-collapse:collapse;">
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Prestation</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($serviceName) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Date</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($date) . '</td></tr>
                <tr><td style="padding:5px 15px 5px 0;color:#888;">Heure</td><td style="padding:5px 0;font-weight:600;">' . htmlspecialchars($timeSlot) . '</td></tr>
            </table>
            <p>Si vous devez annuler ou modifier votre rendez-vous, merci de nous contacter dès que possible.</p>
            <p>À demain !</p>'
        );

        return $this->send($to, $subject, $html);
    }

    private function orderItemsTable(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $rows = '';
        foreach ($items as $item) {
            $name = htmlspecialchars($item['productName'] ?? '');
            $qty = (int) ($item['quantity'] ?? 1);
            $price = htmlspecialchars($item['price'] ?? '0');
            $lineTotal = number_format((float) $price * $qty, 2, ',', '');
            $rows .= '<tr>
                <td style="padding:8px 12px;border-bottom:1px solid #eee;">' . $name . '</td>
                <td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;">' . $qty . '</td>
                <td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:right;">' . $price . ' €</td>
                <td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:right;">' . $lineTotal . ' €</td>
            </tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;margin:15px 0;">
            <tr style="background:#f9f9f9;">
                <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #b8860b;font-size:0.85rem;color:#888;">Produit</th>
                <th style="padding:8px 12px;text-align:center;border-bottom:2px solid #b8860b;font-size:0.85rem;color:#888;">Qté</th>
                <th style="padding:8px 12px;text-align:right;border-bottom:2px solid #b8860b;font-size:0.85rem;color:#888;">Prix unit.</th>
                <th style="padding:8px 12px;text-align:right;border-bottom:2px solid #b8860b;font-size:0.85rem;color:#888;">Sous-total</th>
            </tr>
            ' . $rows . '
        </table>';
    }

    private function template(string $title, string $content): string
    {
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Georgia,serif;">
<div style="max-width:600px;margin:30px auto;background:#fff;">
    <div style="background:#1a1a1a;padding:30px;text-align:center;">
        <h1 style="color:#b8860b;font-size:1.8rem;font-weight:300;letter-spacing:4px;margin:0;">TIFFANY</h1>
        <p style="color:#888;font-size:0.8rem;letter-spacing:2px;margin:5px 0 0;">LES CRÉATIONS DE TIFFANY</p>
    </div>
    <div style="padding:35px 40px;">
        <h2 style="font-size:1.3rem;font-weight:400;color:#1a1a1a;margin:0 0 20px;">' . $title . '</h2>
        <div style="color:#444;line-height:1.7;font-size:0.95rem;">' . $content . '</div>
    </div>
    <div style="background:#f9f9f9;padding:20px 40px;text-align:center;font-size:0.8rem;color:#999;">
        <p style="margin:0;">Les Créations de Tiffany &mdash; Impasse de l\'épi, 4280 Hannut</p>
        <p style="margin:5px 0 0;">0497 92 60 03 &mdash; contact@lescreationsdetiffany.com</p>
    </div>
</div>
</body>
</html>';
    }
}
