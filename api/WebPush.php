<?php
/**
 * WebPush Helper
 * Handles VAPID JWT creation and sending empty Web Push notifications.
 * Uses the "empty push → SW fetches data" pattern — no payload encryption required.
 */

class WebPush
{
    // ── Base64URL helpers ──────────────────────────────────────────────────────

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $pad = 4 - strlen($data) % 4;
        if ($pad < 4) $data .= str_repeat('=', $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ── VAPID key generation ───────────────────────────────────────────────────

    /**
     * Generate a VAPID key pair.
     * Returns ['private_pem' => string, 'public_base64url' => string]
     */
    public static function generateVapidKeys(): array
    {
        // Windows XAMPP fix: pass openssl.cnf path explicitly
        $cnf = null;
        foreach ([
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
        ] as $p) {
            if (file_exists($p)) { $cnf = $p; break; }
        }

        $keyOpts = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        if ($cnf) $keyOpts['config'] = $cnf;

        $key = openssl_pkey_new($keyOpts);

        $details = openssl_pkey_get_details($key);
        // Uncompressed EC point: 0x04 || x (32 bytes) || y (32 bytes)
        $pubRaw = "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        openssl_pkey_export($key, $privatePem, null, $cnf ? ['config' => $cnf] : []);

        return [
            'private_pem'      => $privatePem,
            'public_base64url' => self::base64UrlEncode($pubRaw),
        ];
    }

    // ── VAPID JWT ──────────────────────────────────────────────────────────────

    private static function createJwt(string $endpoint, string $privatePem, string $subject): string
    {
        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);

        $header  = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200, // 12 hours
            'sub' => $subject,
        ]));

        $signing = "$header.$payload";
        openssl_sign($signing, $derSig, $privatePem, OPENSSL_ALGO_SHA256);

        return "$signing." . self::base64UrlEncode(self::derToRaw($derSig));
    }

    /** Convert OpenSSL DER-encoded ECDSA signature to raw r||s (64 bytes). */
    private static function derToRaw(string $der): string
    {
        $pos  = 2; // skip 0x30 + total length
        $pos += 1; // skip 0x02

        $rLen = ord($der[$pos++]);
        $r    = substr($der, $pos, $rLen);
        $pos += $rLen;

        $pos += 1; // skip 0x02
        $sLen = ord($der[$pos++]);
        $s    = substr($der, $pos, $sLen);

        // Normalize to exactly 32 bytes each
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, 32, "\x00", STR_PAD_LEFT)
             . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    // ── Send push ──────────────────────────────────────────────────────────────

    /**
     * Send an empty (no payload) Web Push to a single subscription endpoint.
     * The Service Worker, on receiving it, fetches notification data from the server.
     *
     * @param string $endpoint      Push endpoint URL from the subscription
     * @param string $privatePem    VAPID private key in PEM format
     * @param string $publicBase64  VAPID public key in Base64URL format
     * @param string $subject       mailto: or https: URI for VAPID identity
     * @return bool                 true if the push service accepted the request
     */
    public static function sendEmpty(
        string $endpoint,
        string $privatePem,
        string $publicBase64,
        string $subject = 'mailto:admin@tudu.app'
    ): bool {
        $jwt = self::createJwt($endpoint, $privatePem, $subject);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/plain;charset=UTF-8',
                'Content-Length: 0',
                'TTL: 86400',
                'Urgency: normal',
                'Authorization: vapid t=' . $jwt . ',k=' . $publicBase64,
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 201 = Created (FCM/Chrome), 202 = Accepted (Firefox)
        return $code === 201 || $code === 202;
    }
}
?>
