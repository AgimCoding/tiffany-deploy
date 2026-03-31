<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\PushSubscription;
use App\Domain\Entity\User;
use App\Domain\Port\PushSubscriptionRepositoryInterface;

final class PushNotificationService
{
    public function __construct(
        private readonly PushSubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SiteSettingService $settingService,
    ) {
    }

    public function subscribe(User $user, array $subscriptionData): PushSubscription
    {
        $endpoint = $subscriptionData['endpoint'];
        $keys = $subscriptionData['keys'] ?? [];

        // Remove existing subscription with same endpoint
        $existing = $this->subscriptionRepository->findByEndpoint($endpoint);
        if ($existing) {
            $this->subscriptionRepository->delete($existing);
        }

        $subscription = new PushSubscription(
            $user,
            $endpoint,
            $keys['p256dh'] ?? '',
            $keys['auth'] ?? '',
            $subscriptionData['contentEncoding'] ?? 'aesgcm',
        );

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    public function unsubscribe(string $endpoint): void
    {
        $this->subscriptionRepository->deleteByEndpoint($endpoint);
    }

    /**
     * Send push notification to a specific user (all their devices).
     */
    public function sendToUser(int $userId, string $title, string $body, string $url = '/', string $tag = ''): int
    {
        $subscriptions = $this->subscriptionRepository->findByUserId($userId);
        return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $tag);
    }

    /**
     * Send with debug info for admin test.
     * @return array{sent: int, total: int, errors: string[]}
     */
    public function sendToUserWithDebug(int $userId, string $title, string $body, string $url = '/', string $tag = ''): array
    {
        $subscriptions = $this->subscriptionRepository->findByUserId($userId);
        return $this->sendToSubscriptionsDebug($subscriptions, $title, $body, $url, $tag);
    }

    /**
     * Send push notification to all subscribed users.
     */
    public function sendToAll(string $title, string $body, string $url = '/', string $tag = ''): int
    {
        $subscriptions = $this->subscriptionRepository->findAll();
        return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $tag);
    }

    /**
     * @param PushSubscription[] $subscriptions
     */
    private function sendToSubscriptions(array $subscriptions, string $title, string $body, string $url, string $tag): int
    {
        $vapidPublic = $this->settingService->get('vapid_public_key');
        $vapidPrivate = $this->settingService->get('vapid_private_key');
        $adminEmail = $this->settingService->get('admin_email') ?: 'contact@tiffany-creations.be';
        $vapidSubject = str_starts_with($adminEmail, 'mailto:') ? $adminEmail : 'mailto:' . $adminEmail;

        if (!$vapidPublic || !$vapidPrivate) {
            return 0;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag ?: 'tiffany-' . time(),
            'icon' => '/icon-192.svg',
            'badge' => '/icon-192.svg',
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;
        $expired = [];

        foreach ($subscriptions as $subscription) {
            try {
                $result = $this->sendWebPush(
                    $subscription->getEndpoint(),
                    $subscription->getPublicKey(),
                    $subscription->getAuthToken(),
                    $payload,
                    $vapidPublic,
                    $vapidPrivate,
                    $vapidSubject,
                );

                if ($result === true) {
                    $subscription->markUsed();
                    $this->subscriptionRepository->save($subscription);
                    $sent++;
                } elseif ($result === false) {
                    // Subscription expired or invalid — mark for cleanup
                    $expired[] = $subscription;
                }
            } catch (\Throwable) {
                // Silent fail per subscription
            }
        }

        // Clean up expired subscriptions
        foreach ($expired as $sub) {
            $this->subscriptionRepository->delete($sub);
        }

        return $sent;
    }

    /**
     * @param PushSubscription[] $subscriptions
     * @return array{sent: int, total: int, errors: string[]}
     */
    private function sendToSubscriptionsDebug(array $subscriptions, string $title, string $body, string $url, string $tag): array
    {
        $errors = [];
        $total = count($subscriptions);

        if ($total === 0) {
            return ['sent' => 0, 'total' => 0, 'errors' => ['Aucune subscription trouvee']];
        }

        $vapidPublic = $this->settingService->get('vapid_public_key');
        $vapidPrivate = $this->settingService->get('vapid_private_key');
        $adminEmail = $this->settingService->get('admin_email') ?: 'contact@tiffany-creations.be';
        $vapidSubject = str_starts_with($adminEmail, 'mailto:') ? $adminEmail : 'mailto:' . $adminEmail;

        if (!$vapidPublic || !$vapidPrivate) {
            return ['sent' => 0, 'total' => $total, 'errors' => ['Cles VAPID non configurees']];
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag ?: 'tiffany-' . time(),
            'icon' => '/icon-192.svg',
            'badge' => '/icon-192.svg',
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $result = $this->sendWebPushDebug(
                    $subscription->getEndpoint(),
                    $subscription->getPublicKey(),
                    $subscription->getAuthToken(),
                    $payload,
                    $vapidPublic,
                    $vapidPrivate,
                    $vapidSubject,
                );

                if ($result['success'] === true) {
                    $subscription->markUsed();
                    $this->subscriptionRepository->save($subscription);
                    $sent++;
                } elseif ($result['expired']) {
                    $errors[] = "Subscription expiree (HTTP {$result['httpCode']})";
                } else {
                    $errors[] = "HTTP {$result['httpCode']}: {$result['body']} (curl: {$result['curlError']})";
                }
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return ['sent' => $sent, 'total' => $total, 'errors' => $errors];
    }

    /**
     * Send a single Web Push notification using raw PHP (no external library needed).
     * Compatible with RFC 8291 (Web Push) + RFC 8188 (aes128gcm) + VAPID.
     */
    private function sendWebPush(
        string $endpoint,
        string $userPublicKey,
        string $userAuthToken,
        string $payload,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject,
    ): ?bool {
        // Build VAPID JWT
        $vapidHeaders = $this->createVapidHeaders($endpoint, $vapidPublicKey, $vapidPrivateKey, $vapidSubject);

        // Encrypt the payload
        $encrypted = $this->encryptPayload($payload, $userPublicKey, $userAuthToken);
        if ($encrypted === null) {
            return null;
        }

        // Build HTTP request
        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($encrypted['cipherText']),
            'TTL: 2419200',
            'Urgency: high',
            'Topic: ' . substr(md5($payload), 0, 32),
            'Authorization: vapid t=' . $vapidHeaders['token'] . ', k=' . $vapidHeaders['key'],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encrypted['cipherText'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        // 404 or 410 = subscription expired
        if ($httpCode === 404 || $httpCode === 410) {
            return false;
        }

        return null; // Other error, don't delete subscription
    }

    /**
     * Debug version of sendWebPush that returns detailed error info.
     */
    private function sendWebPushDebug(
        string $endpoint,
        string $userPublicKey,
        string $userAuthToken,
        string $payload,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject,
    ): array {
        $vapidHeaders = $this->createVapidHeaders($endpoint, $vapidPublicKey, $vapidPrivateKey, $vapidSubject);

        $encrypted = $this->encryptPayload($payload, $userPublicKey, $userAuthToken);
        if ($encrypted === null) {
            return ['success' => null, 'expired' => false, 'httpCode' => 0, 'body' => 'Encryption failed', 'curlError' => ''];
        }

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($encrypted['cipherText']),
            'TTL: 2419200',
            'Urgency: high',
            'Topic: ' . substr(md5($payload), 0, 32),
            'Authorization: vapid t=' . $vapidHeaders['token'] . ', k=' . $vapidHeaders['key'],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encrypted['cipherText'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'expired' => $httpCode === 404 || $httpCode === 410,
            'httpCode' => $httpCode,
            'body' => substr((string) $body, 0, 200),
            'curlError' => $curlError,
        ];
    }

    private function createVapidHeaders(string $endpoint, string $publicKey, string $privateKey, string $subject): array
    {
        $parsed = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];

        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $subject,
        ]));

        $signingInput = $header . '.' . $payload;

        // Decode the private key from base64url
        $privateKeyRaw = $this->base64UrlDecode($privateKey);

        // Build PEM from raw EC private key (32 bytes)
        $pem = $this->buildEcPem($privateKeyRaw, $this->base64UrlDecode($publicKey));

        $key = openssl_pkey_get_private($pem);
        openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);

        // Convert DER signature to raw r||s (64 bytes)
        $signature = $this->derToRaw($signature);

        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);

        return [
            'token' => $jwt,
            'key' => $publicKey,
        ];
    }

    private function encryptPayload(string $payload, string $userPublicKeyB64, string $userAuthB64): ?array
    {
        $userPublicKey = $this->base64UrlDecode($userPublicKeyB64);
        $userAuth = $this->base64UrlDecode($userAuthB64);

        if (strlen($userPublicKey) !== 65 || strlen($userAuth) !== 16) {
            return null;
        }

        // Generate local ECDH key pair
        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $localKeyDetails = openssl_pkey_get_details($localKey);
        $localPublicKey = $this->getUncompressedPublicKey($localKeyDetails);

        // Compute ECDH shared secret
        $sharedSecret = $this->computeEcdhSecret($localKey, $userPublicKey);
        if ($sharedSecret === null) {
            return null;
        }

        // HKDF for auth info (IKM)
        $ikm = $this->hkdf(
            $sharedSecret,
            $userAuth,
            "WebPush: info\x00" . $userPublicKey . $localPublicKey,
            32
        );

        // Generate salt (16 bytes)
        $salt = random_bytes(16);

        // HKDF for Content-Encryption-Key
        $cek = $this->hkdf($ikm, $salt, "Content-Encoding: aes128gcm\x00", 16);

        // HKDF for nonce
        $nonce = $this->hkdf($ikm, $salt, "Content-Encoding: nonce\x00", 12);

        // Pad the payload (add 0x02 delimiter + zero padding)
        $paddedPayload = $payload . "\x02";

        // Encrypt with AES-128-GCM
        $tag = '';
        $encrypted = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($encrypted === false) {
            return null;
        }

        // Build aes128gcm header: salt (16) + rs (4) + idlen (1) + keyid (65)
        $recordSize = pack('N', 4096);
        $header = $salt . $recordSize . chr(65) . $localPublicKey;

        return [
            'cipherText' => $header . $encrypted . $tag,
        ];
    }

    private function computeEcdhSecret($localPrivateKey, string $remotePublicKeyBin): ?string
    {
        // Convert uncompressed point to PEM
        $remotePem = $this->buildEcPublicPem($remotePublicKeyBin);
        $remoteKey = openssl_pkey_get_public($remotePem);
        if (!$remoteKey) {
            return null;
        }

        $secret = openssl_pkey_derive($localPrivateKey, $remoteKey, 256);
        if ($secret === false) {
            // Fallback: try with key length auto-detect
            $secret = openssl_pkey_derive($localPrivateKey, $remoteKey);
        }

        return $secret !== false ? $secret : null;
    }

    private function getUncompressedPublicKey(array $details): string
    {
        $x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        return "\x04" . $x . $y;
    }

    private function buildEcPem(string $privateKeyRaw, string $publicKeyRaw): string
    {
        // SEC1 EC private key format
        $der = "\x30" . $this->asn1Length(
            "\x02\x01\x01" . // version
            "\x04" . chr(strlen($privateKeyRaw)) . $privateKeyRaw . // private key
            "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" . // OID prime256v1
            "\xa1" . $this->asn1Length("\x03" . $this->asn1Length("\x00" . $publicKeyRaw)) // public key
        );

        return "-----BEGIN EC PRIVATE KEY-----\n" .
            chunk_split(base64_encode($der), 64, "\n") .
            "-----END EC PRIVATE KEY-----\n";
    }

    private function buildEcPublicPem(string $publicKeyBin): string
    {
        // SubjectPublicKeyInfo for EC key on prime256v1
        $header = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der = $header . $publicKeyBin;

        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($der), 64, "\n") .
            "-----END PUBLIC KEY-----\n";
    }

    private function asn1Length(string $data): string
    {
        $len = strlen($data);
        if ($len < 128) {
            return chr($len) . $data;
        }
        $lenBytes = '';
        $tmp = $len;
        while ($tmp > 0) {
            $lenBytes = chr($tmp & 0xFF) . $lenBytes;
            $tmp >>= 8;
        }
        return chr(0x80 | strlen($lenBytes)) . $lenBytes . $data;
    }

    private function derToRaw(string $der): string
    {
        // Parse DER SEQUENCE containing two INTEGERs
        $pos = 2; // skip SEQUENCE tag + length
        if (ord($der[1]) & 0x80) {
            $pos += (ord($der[1]) & 0x7F);
        }

        // First INTEGER (r)
        $pos++; // skip INTEGER tag (0x02)
        $rLen = ord($der[$pos]);
        $pos++;
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        // Second INTEGER (s)
        $pos++; // skip INTEGER tag (0x02)
        $sLen = ord($der[$pos]);
        $pos++;
        $s = substr($der, $pos, $sLen);

        // Ensure each is exactly 32 bytes (pad or trim leading zeros)
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private function hkdf(string $ikm, string $salt, string $info, int $length): string
    {
        // Extract
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        // Expand
        return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $length);
    }

    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
    }

    /**
     * Generate VAPID key pair and store in site settings.
     */
    public function generateVapidKeys(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        $details = openssl_pkey_get_details($key);

        $publicKey = $this->getUncompressedPublicKey($details);
        $privateKey = str_pad($details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

        $publicKeyB64 = $this->base64UrlEncode($publicKey);
        $privateKeyB64 = $this->base64UrlEncode($privateKey);

        $this->settingService->set('vapid_public_key', $publicKeyB64);
        $this->settingService->set('vapid_private_key', $privateKeyB64);

        return [
            'publicKey' => $publicKeyB64,
        ];
    }

    /**
     * @return PushSubscription[]
     */
    public function getAllSubscriptions(): array
    {
        return $this->subscriptionRepository->findAll();
    }

    public function getVapidPublicKey(): ?string
    {
        return $this->settingService->get('vapid_public_key');
    }
}
