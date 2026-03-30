<?php

declare(strict_types=1);

namespace App\Application\Service;

final class SmsService
{
    private const API_BASE = 'https://restapi.easysendsms.app/v1/rest';

    public function __construct(private readonly SiteSettingService $settings)
    {
    }

    public function getBalance(): ?array
    {
        $apiKey = $this->settings->get('sms_api_key');
        if (!$apiKey) {
            return null;
        }

        return $this->request('POST', '/sms/balance', $apiKey);
    }

    public function send(string $to, string $message): bool
    {
        $apiKey = $this->settings->get('sms_api_key');
        $sender = $this->settings->get('sms_sender_name') ?: 'Tiffany';

        if (!$apiKey) {
            return false;
        }

        $response = $this->request('POST', '/sms/send', $apiKey, [
            'from' => $sender,
            'to' => $to,
            'text' => $message,
            'type' => '0',
        ]);

        return $response !== null && isset($response['status']) && $response['status'] === 'success';
    }

    public function sendAppointmentReminder(string $to, string $clientName, string $serviceName, string $date, string $timeSlot): bool
    {
        $message = sprintf(
            'Bonjour %s, rappel de votre RDV demain %s a %s pour %s. Les Creations de Tiffany - 0497 92 60 03',
            $clientName,
            $date,
            $timeSlot,
            $serviceName,
        );

        return $this->send($to, $message);
    }

    private function request(string $method, string $path, string $apiKey, ?array $body = null): ?array
    {
        $url = self::API_BASE . $path;
        $jsonBody = $body !== null ? json_encode($body) : '';

        $headers = [
            'apikey: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (function_exists('curl_init')) {
            return $this->requestCurl($method, $url, $headers, $jsonBody);
        }

        return $this->requestStream($method, $url, $headers, $jsonBody);
    }

    private function requestCurl(string $method, string $url, array $headers, string $jsonBody): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result === false) {
            return null;
        }

        $decoded = json_decode($result, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function requestStream(string $method, string $url, array $headers, string $jsonBody): ?array
    {
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $jsonBody,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        $context = stream_context_create($options);

        try {
            $result = @file_get_contents($url, false, $context);
        } catch (\Throwable) {
            return null;
        }

        if ($result === false) {
            return null;
        }

        $decoded = json_decode($result, true);

        return is_array($decoded) ? $decoded : null;
    }
}
