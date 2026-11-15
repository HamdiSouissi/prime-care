<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use PDO;
use RuntimeException;

/**
 * WebPushService — Implémentation PHP native du protocole Web Push :
 * - Génération et chargement de la paire de clés VAPID ECDSA P-256 (`prime256v1`) dans `storage/keys/vapid.json` (hors Git)
 * - Signature et vérification du jeton JWT VAPID `ES256` selon RFC 8292 (conversion signature ASN.1 DER <-> brut r||s 64 octets)
 * - Chiffrement et déchiffrement de la charge utile selon RFC 8291 (`aes128gcm` : ECDH P-256 + HKDF-SHA-256 + AES-128-GCM)
 * - Envoi HTTPS via cURL avec en-têtes `TTL`, `Urgency`, `Content-Encoding: aes128gcm` et gestion des codes 201 / 404 / 410 / 429 / 5xx.
 */
final class WebPushService
{
    /**
     * Retourne le chemin du fichier `storage/keys/vapid.json`.
     */
    public static function vapidFilePath(): string
    {
        $storage = (string) Config::get('paths.storage', dirname(__DIR__, 2) . '/storage');
        return $storage . '/keys/vapid.json';
    }

    /**
     * Génère une nouvelle paire de clés VAPID ECDSA P-256 (`prime256v1`) et la sauvegarde dans `storage/keys/vapid.json`.
     *
     * @return array{publicKey: string, privateKey: string, privateKeyPem: string, publicKeyPem: string, subject: string, created_at: string}
     */
    public static function generateVapidKeys(bool $forceOverwrite = false): array
    {
        $path = self::vapidFilePath();
        if (!$forceOverwrite && is_file($path)) {
            $loaded = json_decode((string) file_get_contents($path), true);
            if (is_array($loaded) && !empty($loaded['publicKey']) && !empty($loaded['privateKeyPem'])) {
                return $loaded;
            }
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($key === false) {
            throw new RuntimeException('Impossible de générer la clé ECDSA prime256v1 via OpenSSL.');
        }

        openssl_pkey_export($key, $privateKeyPem);
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || !isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])) {
            throw new RuntimeException('Détails de la clé ECDSA P-256 invalides.');
        }

        $x = str_pad((string) $details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad((string) $details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $d = str_pad((string) $details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

        // Uncompressed public point: 0x04 || X (32B) || Y (32B) = 65 bytes
        $uncompressedPub = "\x04" . $x . $y;

        $keys = [
            'publicKey' => self::base64UrlEncode($uncompressedPub),
            'privateKey' => self::base64UrlEncode($d),
            'privateKeyPem' => (string) $privateKeyPem,
            'publicKeyPem' => (string) $details['key'],
            'subject' => 'mailto:' . (string) Config::get('mail.from_address', 'ne-pas-repondre@primecare.test'),
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        file_put_contents($path, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($path, 0600);

        return $keys;
    }

    /**
     * Retourne les clés VAPID (les génère automatiquement si absentes).
     *
     * @return array{publicKey: string, privateKey: string, privateKeyPem: string, publicKeyPem: string, subject: string, created_at: string}
     */
    public static function getOrCreateVapidKeys(): array
    {
        return self::generateVapidKeys(false);
    }

    /**
     * Crée un jeton JWT VAPID signé `ES256` conforme à la RFC 8292.
     * La signature OpenSSL (ASN.1 DER) est convertie en format brut `r || s` de 64 octets.
     */
    public static function createVapidJwt(
        string $audienceOrigin,
        ?string $subject = null,
        ?string $privateKeyPem = null,
        ?int $expirationTs = null
    ): string {
        $keys = self::getOrCreateVapidKeys();
        $privateKeyPem ??= $keys['privateKeyPem'];
        $subject ??= $keys['subject'];
        $expirationTs ??= time() + 12 * 3600; // 12h max (RFC 8292 <= 24h)

        $parsed = parse_url($audienceOrigin);
        if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
            $aud = $parsed['scheme'] . '://' . $parsed['host'];
            if (isset($parsed['port'])) {
                $aud .= ':' . $parsed['port'];
            }
        } else {
            $aud = $audienceOrigin;
        }

        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = [
            'aud' => $aud,
            'exp' => $expirationTs,
            'sub' => $subject,
        ];

        $signingInput = self::base64UrlEncode((string) json_encode($header))
            . '.'
            . self::base64UrlEncode((string) json_encode($payload));

        $derSignature = '';
        $ok = openssl_sign($signingInput, $derSignature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        if (!$ok || $derSignature === '') {
            throw new RuntimeException('Échec de la signature ES256 du jeton VAPID.');
        }

        $rawSignature = self::derToRawSignature($derSignature);
        return $signingInput . '.' . self::base64UrlEncode($rawSignature);
    }

    /**
     * Vérifie un jeton JWT VAPID `ES256` avec la clé publique VAPID (utilisé par les tests unitaires).
     *
     * @return array{valid: bool, header: array<string, mixed>, payload: array<string, mixed>, raw_sig_length: int}
     */
    public static function verifyVapidJwt(string $jwt, ?string $publicKeyPem = null): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Jeton JWT VAPID mal formé.');
        }
        [$h64, $p64, $s64] = $parts;
        $header = json_decode(self::base64UrlDecode($h64), true) ?: [];
        $payload = json_decode(self::base64UrlDecode($p64), true) ?: [];
        $rawSig = self::base64UrlDecode($s64);

        if ($publicKeyPem === null) {
            $keys = self::getOrCreateVapidKeys();
            $publicKeyPem = $keys['publicKeyPem'];
        }

        $derSig = self::rawToDerSignature($rawSig);
        $verified = openssl_verify($h64 . '.' . $p64, $derSig, $publicKeyPem, OPENSSL_ALGO_SHA256);

        return [
            'valid' => ($verified === 1),
            'header' => $header,
            'payload' => $payload,
            'raw_sig_length' => strlen($rawSig),
        ];
    }

    /**
     * Chiffre une charge utile Web Push selon la RFC 8291 (`aes128gcm`).
     * Retourne le corps binaire complet prêt à envoyer :
     *   salt (16 octets) || rs (4 octets big-endian = 4096) || idlen (1 octet = 65) || as_public (65 octets) || ciphertext || tag (16 octets)
     *
     * Les paramètres optionnels `$testSalt`, `$testEphemeralPrivateKey` et `$testEphemeralPublicKey`
     * permettent d'injecter le vecteur officiel RFC 8291 Appendix A lors des tests unitaires.
     *
     * @return array{body: string, salt: string, server_public_key: string, cek: string, nonce: string}
     */
    public static function encryptPayload(
        string $plaintext,
        string $userP256dhBase64Url,
        string $userAuthBase64Url,
        ?string $testSalt = null,
        OpenSSLAsymmetricKey|string|null $testEphemeralPrivateKey = null,
        ?string $testEphemeralPublicKey = null
    ): array {
        $uaPublic = self::base64UrlDecode($userP256dhBase64Url);
        $authSecret = self::base64UrlDecode($userAuthBase64Url);

        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04") {
            throw new InvalidArgumentException('Clé publique client p256dh invalide (attendu : 65 octets non compressés 0x04||X||Y).');
        }
        if (strlen($authSecret) < 16) {
            throw new InvalidArgumentException('Secret d’authentification Web Push (auth) invalide (attendu : >= 16 octets).');
        }

        // 1. Générer (ou injecter pour test RFC 8291 Appendix A) la paire de clés éphémère P-256 (as_private, as_public)
        if ($testEphemeralPrivateKey !== null) {
            if ($testEphemeralPrivateKey instanceof OpenSSLAsymmetricKey) {
                $ephemeralKey = $testEphemeralPrivateKey;
            } elseif (str_contains($testEphemeralPrivateKey, 'BEGIN')) {
                $ephemeralKey = openssl_pkey_get_private($testEphemeralPrivateKey);
            } else {
                $rawPriv = strlen($testEphemeralPrivateKey) === 32
                    ? $testEphemeralPrivateKey
                    : self::base64UrlDecode($testEphemeralPrivateKey);
                $rawPub = $testEphemeralPublicKey !== null
                    ? (strlen($testEphemeralPublicKey) === 65 ? $testEphemeralPublicKey : self::base64UrlDecode($testEphemeralPublicKey))
                    : '';
                $pem = self::rawPrivateAndPublicToPem($rawPriv, $rawPub);
                $ephemeralKey = openssl_pkey_get_private($pem);
            }
            if ($ephemeralKey === false) {
                throw new InvalidArgumentException('Clé éphémère de test invalide.');
            }
        } else {
            $ephemeralKey = openssl_pkey_new([
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ]);
            if ($ephemeralKey === false) {
                throw new RuntimeException('Impossible de générer la clé éphémère ECDH P-256.');
            }
        }

        if ($testEphemeralPublicKey !== null) {
            $asPublic = strlen($testEphemeralPublicKey) === 65
                ? $testEphemeralPublicKey
                : self::base64UrlDecode($testEphemeralPublicKey);
        } else {
            $ephDetails = openssl_pkey_get_details($ephemeralKey);
            if (!is_array($ephDetails) || !isset($ephDetails['ec']['x'], $ephDetails['ec']['y'])) {
                throw new RuntimeException('Détails de la clé éphémère ECDH invalides.');
            }
            $asX = str_pad((string) $ephDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT);
            $asY = str_pad((string) $ephDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
            $asPublic = "\x04" . $asX . $asY; // 65 bytes
        }

        // 2. Convertir le point public du navigateur (ua_public 65B) en PEM SubjectPublicKeyInfo P-256 pour openssl_pkey_derive
        $uaPublicKeyPem = self::uncompressedPointToPem($uaPublic);
        $uaPkey = openssl_pkey_get_public($uaPublicKeyPem);
        if ($uaPkey === false) {
            throw new InvalidArgumentException('Impossible d’importer la clé publique p256dh dans OpenSSL.');
        }

        // 3. Calculer le secret partagé ECDH (32 octets)
        $ecdhSecret = openssl_pkey_derive($uaPkey, $ephemeralKey, 256);
        if ($ecdhSecret === false || strlen($ecdhSecret) !== 32) {
            throw new RuntimeException('Échec de la dérivation ECDH P-256.');
        }

        // 4. Dérivation des clés selon RFC 8291 §3.3 & §3.4 (HKDF-SHA-256)
        if ($testSalt !== null) {
            $salt = strlen($testSalt) === 16 ? $testSalt : self::base64UrlDecode($testSalt);
        } else {
            $salt = random_bytes(16);
        }
        if (strlen($salt) !== 16) {
            throw new InvalidArgumentException('Le sel Web Push (salt) doit mesurer exactement 16 octets.');
        }

        $derived = self::deriveAes128GcmKeys($ecdhSecret, $authSecret, $uaPublic, $asPublic, $salt);
        $cek = $derived['cek'];
        $nonce = $derived['nonce'];

        // 5. Chiffrement AES-128-GCM de l'enregistrement unique terminé par l'octet délimiteur 0x02 (RFC 8188 §2)
        $recordPlaintext = $plaintext . "\x02";
        $tag = '';
        $ciphertext = openssl_encrypt(
            $recordPlaintext,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Échec du chiffrement AES-128-GCM.');
        }

        // 6. En-tête binaire RFC 8188 (86 octets : salt 16B + rs 4B + idlen 1B + keyid 65B)
        $rs = pack('N', 4096);
        $idLen = chr(65);
        $header = $salt . $rs . $idLen . $asPublic;

        return [
            'body' => $header . $ciphertext . $tag,
            'salt' => $salt,
            'server_public_key' => $asPublic,
            'cek' => $cek,
            'nonce' => $nonce,
        ];
    }

    /**
     * Déchiffre un corps `aes128gcm` RFC 8291 avec la clé privée du client (utilisé par les tests unitaires pour prouver la conformité RFC 8291).
     */
    public static function decryptPayload(
        string $encryptedBody,
        OpenSSLAsymmetricKey|string $clientPrivateKey,
        string $clientPublicKeyRaw,
        string $authSecretRaw
    ): string {
        if (strlen($encryptedBody) < 86 + 16 + 1) {
            throw new InvalidArgumentException('Corps chiffré aes128gcm trop court.');
        }

        $salt = substr($encryptedBody, 0, 16);
        $rsUnpacked = unpack('Nrs', substr($encryptedBody, 16, 4));
        $rs = (int) ($rsUnpacked['rs'] ?? 0);
        $idLen = ord($encryptedBody[20]);
        if ($rs < 18 || $idLen !== 65) {
            throw new InvalidArgumentException("En-tête RFC 8188 invalide (rs={$rs}, idlen={$idLen}).");
        }

        $asPublic = substr($encryptedBody, 21, 65);
        $ciphertextAndTag = substr($encryptedBody, 86);
        $tag = substr($ciphertextAndTag, -16);
        $ciphertext = substr($ciphertextAndTag, 0, -16);

        $asPubPem = self::uncompressedPointToPem($asPublic);
        $asPkey = openssl_pkey_get_public($asPubPem);
        $privKey = is_string($clientPrivateKey) ? openssl_pkey_get_private($clientPrivateKey) : $clientPrivateKey;
        if ($asPkey === false || $privKey === false) {
            throw new RuntimeException('Clés ECDH invalides pour le déchiffrement.');
        }

        $ecdhSecret = openssl_pkey_derive($asPkey, $privKey, 256);
        if ($ecdhSecret === false) {
            throw new RuntimeException('Échec ECDH lors du déchiffrement.');
        }

        $derived = self::deriveAes128GcmKeys($ecdhSecret, $authSecretRaw, $clientPublicKeyRaw, $asPublic, $salt);
        $decrypted = openssl_decrypt(
            $ciphertext,
            'aes-128-gcm',
            $derived['cek'],
            OPENSSL_RAW_DATA,
            $derived['nonce'],
            $tag
        );
        if ($decrypted === false) {
            throw new RuntimeException('Tag d’authentification GCM invalide lors du déchiffrement.');
        }

        // Remove trailing padding 0x00 and record delimiter 0x02
        $decrypted = rtrim($decrypted, "\x00");
        if (!str_ends_with($decrypted, "\x02")) {
            throw new RuntimeException('Délimiteur d’enregistrement RFC 8188 (0x02) manquant.');
        }

        return substr($decrypted, 0, -1);
    }

    /**
     * Enregistre ou met à jour un abonnement Web Push pour un utilisateur.
     *
     * @param array<string, mixed> $subData
     */
    public static function saveSubscription(int $userId, array $subData, string $userAgent = ''): int
    {
        $endpoint = trim((string) ($subData['endpoint'] ?? ''));
        $keys = is_array($subData['keys'] ?? null) ? $subData['keys'] : $subData;
        $p256dh = trim((string) ($keys['p256dh'] ?? ''));
        $auth = trim((string) ($keys['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new InvalidArgumentException('Paramètres d’abonnement Web Push incomplets (endpoint, p256dh, auth requis).');
        }

        $endpointHash = hash('sha256', $endpoint);
        $now = gmdate('Y-m-d H:i:s');
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO push_subscriptions
             (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent, last_seen_at, created_at, updated_at, created_by, deleted_at)
             VALUES (:uid, :ep, :eh, :p256dh, :auth, :ua, :seen, :c_at, :u_at, :by, NULL)
             ON DUPLICATE KEY UPDATE
               user_id = VALUES(user_id),
               p256dh = VALUES(p256dh),
               auth = VALUES(auth),
               user_agent = VALUES(user_agent),
               last_seen_at = VALUES(last_seen_at),
               updated_at = VALUES(updated_at),
               deleted_at = NULL'
        );
        $stmt->execute([
            'uid' => $userId,
            'ep' => substr($endpoint, 0, 768),
            'eh' => $endpointHash,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'ua' => substr($userAgent, 0, 255),
            'seen' => $now,
            'c_at' => $now,
            'u_at' => $now,
            'by' => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Supprime un abonnement Web Push (par endpoint ou par ID).
     */
    public static function removeSubscription(int $userId, string $endpoint): void
    {
        $pdo = Database::connection();
        $eh = hash('sha256', trim($endpoint));
        $stmt = $pdo->prepare(
            'DELETE FROM push_subscriptions WHERE user_id = :uid AND endpoint_hash = :eh'
        );
        $stmt->execute(['uid' => $userId, 'eh' => $eh]);
    }

    /**
     * Envoie une notification Web Push chiffrée à un abonnement `push_subscriptions`.
     * Gère les codes HTTP :
     * - 200 / 201 / 202 : succès
     * - 404 / 410 : abonnement expiré -> supprimé de la base
     * - 429 / 5xx : échec temporaire reprogrammé
     *
     * @param array<string, mixed> $subscriptionRow
     * @param array<string, mixed> $payloadData
     * @return array{ok: bool, status_code: int, expired: bool, error: ?string}
     */
    public static function sendToSubscription(
        array $subscriptionRow,
        array $payloadData,
        string $urgency = 'normal',
        int $ttl = 86400,
        ?int $testSimulateHttpCode = null
    ): array {
        $endpoint = (string) ($subscriptionRow['endpoint'] ?? '');
        $p256dh = (string) ($subscriptionRow['p256dh'] ?? '');
        $auth = (string) ($subscriptionRow['auth'] ?? '');
        $subId = isset($subscriptionRow['id']) ? (int) $subscriptionRow['id'] : 0;

        $jsonPayload = (string) json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encrypted = self::encryptPayload($jsonPayload, $p256dh, $auth);
        $keys = self::getOrCreateVapidKeys();
        $jwt = self::createVapidJwt($endpoint, $keys['subject'], $keys['privateKeyPem']);

        // In development/testing or local mock endpoints (.test / localhost), log without external network failure
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $isSimulatedHost = ($host === '' || str_ends_with($host, '.test') || str_ends_with($host, '.example') || $host === 'localhost' || $host === '127.0.0.1');

        if ($testSimulateHttpCode !== null || $isSimulatedHost) {
            $simCode = $testSimulateHttpCode;
            if ($simCode === null) {
                if (str_contains($endpoint, 'gone-410') || str_contains($endpoint, 'status=410')) {
                    $simCode = 410;
                } elseif (str_contains($endpoint, 'notfound-404') || str_contains($endpoint, 'status=404')) {
                    $simCode = 404;
                } elseif (str_contains($endpoint, 'fail-503') || str_contains($endpoint, 'status=503')) {
                    $simCode = 503;
                } else {
                    $simCode = 201;
                }
            }

            self::logPushAttempt($endpoint, $simCode, $jsonPayload, "Simulated push endpoint (HTTP {$simCode})");

            if ($simCode >= 200 && $simCode < 300) {
                return [
                    'ok' => true,
                    'status_code' => $simCode,
                    'expired' => false,
                    'error' => null,
                ];
            }

            if ($simCode === 404 || $simCode === 410) {
                if ($subId > 0) {
                    $pdo = Database::connection();
                    $del = $pdo->prepare('DELETE FROM push_subscriptions WHERE id = :id');
                    $del->execute(['id' => $subId]);
                }
                return [
                    'ok' => false,
                    'status_code' => $simCode,
                    'expired' => true,
                    'error' => "Abonnement push expiré (HTTP {$simCode}) — supprimé.",
                ];
            }

            return [
                'ok' => false,
                'status_code' => $simCode,
                'expired' => false,
                'error' => "HTTP {$simCode} : Service Web Push temporairement indisponible.",
            ];
        }

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($encrypted['body']),
            'TTL: ' . $ttl,
            'Urgency: ' . (in_array($urgency, ['very-low', 'low', 'normal', 'high'], true) ? $urgency : 'normal'),
            'Authorization: vapid t=' . $jwt . ', k=' . $keys['publicKey'],
        ];

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'status_code' => 0, 'expired' => false, 'error' => 'Impossible d’initialiser cURL.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encrypted['body'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $respBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        self::logPushAttempt($endpoint, $httpCode, $jsonPayload, $curlErr !== '' ? $curlErr : (string) $respBody);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'status_code' => $httpCode, 'expired' => false, 'error' => null];
        }

        if ($httpCode === 404 || $httpCode === 410) {
            if ($subId > 0) {
                $pdo = Database::connection();
                $del = $pdo->prepare('DELETE FROM push_subscriptions WHERE id = :id');
                $del->execute(['id' => $subId]);
            }
            return [
                'ok' => false,
                'status_code' => $httpCode,
                'expired' => true,
                'error' => "Abonnement push expiré (HTTP {$httpCode}) — supprimé.",
            ];
        }

        return [
            'ok' => false,
            'status_code' => $httpCode,
            'expired' => false,
            'error' => $curlErr !== '' ? $curlErr : "HTTP {$httpCode} : " . substr((string) $respBody, 0, 200),
        ];
    }

    /**
     * Dérive la clé de chiffrement CEK (16 octets) et le NONCE (12 octets) selon RFC 8291 §3.3 & §3.4.
     *
     * @return array{cek: string, nonce: string}
     */
    private static function deriveAes128GcmKeys(
        string $ecdhSecret,
        string $authSecret,
        string $uaPublic,
        string $asPublic,
        string $salt
    ): array {
        // PRK_key = HMAC-SHA-256(auth_secret, ecdh_secret)
        $prkKey = hash_hmac('sha256', $ecdhSecret, $authSecret, true);
        // key_info = "WebPush: info" || 0x00 || ua_public || as_public
        $keyInfo = "WebPush: info\x00" . $uaPublic . $asPublic;
        // IKM = HMAC-SHA-256(PRK_key, key_info || 0x01)
        $ikm = hash_hmac('sha256', $keyInfo . "\x01", $prkKey, true);

        // PRK = HMAC-SHA-256(salt, IKM)
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        // CEK = HMAC-SHA-256(PRK, "Content-Encoding: aes128gcm\x00\x01")[0..15]
        $cekInfo = "Content-Encoding: aes128gcm\x00\x01";
        $cek = substr(hash_hmac('sha256', $cekInfo, $prk, true), 0, 16);

        // NONCE = HMAC-SHA-256(PRK, "Content-Encoding: nonce\x00\x01")[0..11]
        $nonceInfo = "Content-Encoding: nonce\x00\x01";
        $nonce = substr(hash_hmac('sha256', $nonceInfo, $prk, true), 0, 12);

        return ['cek' => $cek, 'nonce' => $nonce];
    }

    /**
     * Convertit une signature ECDSA ASN.1 DER en format brut JWS `r || s` de 64 octets (32 + 32).
     */
    public static function derToRawSignature(string $der): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new InvalidArgumentException('Signature DER ECDSA invalide (0x30 attendu).');
        }
        $seqLen = ord($der[$offset++]);
        if ($seqLen & 0x80) {
            $offset += ($seqLen & 0x7f);
        }

        if (ord($der[$offset++]) !== 0x02) {
            throw new InvalidArgumentException('Signature DER ECDSA invalide (0x02 attendu pour R).');
        }
        $rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        if (ord($der[$offset++]) !== 0x02) {
            throw new InvalidArgumentException('Signature DER ECDSA invalide (0x02 attendu pour S).');
        }
        $sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        $rPadded = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $sPadded = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return substr($rPadded, -32) . substr($sPadded, -32);
    }

    /**
     * Convertit une signature brute JWS `r || s` (64 octets) en séquence ASN.1 DER pour `openssl_verify`.
     */
    public static function rawToDerSignature(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new InvalidArgumentException('Signature brute ES256 invalide (64 octets attendus).');
        }
        $r = ltrim(substr($raw, 0, 32), "\x00");
        $s = ltrim(substr($raw, 32, 32), "\x00");
        if ($r === '') {
            $r = "\x00";
        }
        if ($s === '') {
            $s = "\x00";
        }
        if (ord($r[0]) & 0x80) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) & 0x80) {
            $s = "\x00" . $s;
        }

        $derR = "\x02" . chr(strlen($r)) . $r;
        $derS = "\x02" . chr(strlen($s)) . $s;
        $seq = $derR . $derS;

        return "\x30" . chr(strlen($seq)) . $seq;
    }

    /**
     * Convertit un point public P-256 non compressé de 65 octets (`0x04 || X || Y`) en clé publique PEM SPKI.
     */
    public static function uncompressedPointToPem(string $uncompressedPoint): string
    {
        if (strlen($uncompressedPoint) !== 65 || $uncompressedPoint[0] !== "\x04") {
            throw new InvalidArgumentException('Point P-256 non compressé de 65 octets attendu.');
        }
        // ASN.1 SubjectPublicKeyInfo prefix for id-ecPublicKey + prime256v1 (26 bytes)
        $spkiPrefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der = $spkiPrefix . $uncompressedPoint;
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Convertit une clé privée brute P-256 (32 octets) et son point public non compressé (65 octets) en PEM SEC 1 (RFC 5915).
     */
    public static function rawPrivateAndPublicToPem(string $privateScalar32B, string $uncompressedPoint65B): string
    {
        if (strlen($privateScalar32B) !== 32 || strlen($uncompressedPoint65B) !== 65 || $uncompressedPoint65B[0] !== "\x04") {
            throw new InvalidArgumentException('Clé privée (32 octets) et point public P-256 (65 octets) requis.');
        }
        $der = hex2bin('30770201010420')
            . $privateScalar32B
            . hex2bin('a00a06082a8648ce3d030107a144034200')
            . $uncompressedPoint65B;
        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    private static function logPushAttempt(string $endpoint, int $status, string $payload, string $info): void
    {
        $logDir = (string) Config::get('paths.logs', dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $entry = sprintf(
            "[%s] WEB_PUSH status=%d endpoint=%s payload=%s info=%s\n",
            gmdate('Y-m-d H:i:s'),
            $status,
            $endpoint,
            $payload,
            $info
        );
        @file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND);
    }
}
