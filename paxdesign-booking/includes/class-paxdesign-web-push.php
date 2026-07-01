<?php
/**
 * Minimal Web Push sender (VAPID + aes128gcm).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Web_Push {

    /**
     * @param array<string, mixed>  $subscription PushSubscription JSON.
     * @param array<string, mixed>  $payload      Notification payload (title, body, url, badge, tag).
     * @param array<string, string> $vapid        public_key, private_pem, subject.
     * @return bool|WP_Error
     */
    public static function send($subscription, $payload, $vapid) {
        if (empty($subscription['endpoint']) || empty($subscription['keys']['p256dh']) || empty($subscription['keys']['auth'])) {
            return new WP_Error('invalid_subscription', 'Invalid push subscription.');
        }

        $endpoint = esc_url_raw($subscription['endpoint']);
        if ($endpoint === '') {
            return new WP_Error('invalid_endpoint', 'Invalid push endpoint.');
        }

        $user_public = self::base64url_decode($subscription['keys']['p256dh']);
        $user_auth   = self::base64url_decode($subscription['keys']['auth']);
        if (strlen($user_public) !== 65 || $user_auth === '') {
            return new WP_Error('invalid_keys', 'Invalid subscription keys.');
        }

        $local_key = openssl_pkey_new(array(
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ));
        if (!$local_key) {
            return new WP_Error('openssl', 'Could not create local EC key.');
        }

        $local_details = openssl_pkey_get_details($local_key);
        if (empty($local_details['ec']['x']) || empty($local_details['ec']['y'])) {
            return new WP_Error('openssl', 'Invalid local EC key.');
        }

        $local_public = "\x04"
            . str_pad($local_details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($local_details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        $peer_pem = self::uncompressed_to_pem($user_public);
        $shared   = openssl_pkey_derive($peer_pem, $local_key, 256);
        if ($shared === false) {
            return new WP_Error('openssl', 'ECDH failed.');
        }
        $shared = str_pad($shared, 32, "\x00", STR_PAD_LEFT);

        $salt = random_bytes(16);
        $ikm  = self::hkdf($user_auth, $shared, 'WebPush: info' . "\x00" . $user_public . $local_public, 32);
        $cek  = self::hkdf($salt, $ikm, 'Content-Encoding: aes128gcm' . "\x00", 16);
        $nonce = self::hkdf($salt, $ikm, 'Content-Encoding: nonce' . "\x00", 12);

        $json    = wp_json_encode($payload);
        $padded  = $json . chr(2);
        $tag     = '';
        $cipher  = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            return new WP_Error('encrypt', 'Payload encryption failed.');
        }

        $header = $salt . pack('N', 4096) . chr(strlen($local_public)) . $local_public;
        $body   = $header . $cipher . $tag;

        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $jwt      = self::create_vapid_jwt($audience, $vapid);
        if ($jwt === '') {
            return new WP_Error('vapid', 'VAPID JWT failed.');
        }

        $response = wp_remote_post($endpoint, array(
            'timeout' => 12,
            'headers' => array(
                'Content-Type'     => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL'              => '86400',
                'Urgency'          => 'high',
                'Authorization'    => 'vapid t=' . $jwt . ', k=' . $vapid['public_key'],
            ),
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return true;
        }
        if ($code === 404 || $code === 410) {
            return new WP_Error('gone', 'Subscription expired.', array('status' => $code));
        }

        return new WP_Error('push_failed', 'Push failed with HTTP ' . $code, array('status' => $code));
    }

    /**
     * @return array{public_key: string, private_pem: string}|false
     */
    public static function generate_vapid_keys() {
        $key = openssl_pkey_new(array(
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ));
        if (!$key) {
            return false;
        }

        $details = openssl_pkey_get_details($key);
        if (empty($details['ec']['x']) || empty($details['ec']['y'])) {
            return false;
        }

        $private_pem = '';
        if (!openssl_pkey_export($key, $private_pem) || $private_pem === '') {
            return false;
        }

        $public_raw = "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        return array(
            'public_key'  => self::base64url_encode($public_raw),
            'private_pem' => $private_pem,
        );
    }

    /**
     * @param array<string, string> $vapid
     */
    private static function create_vapid_jwt($audience, $vapid) {
        $header  = self::base64url_encode(wp_json_encode(array('typ' => 'JWT', 'alg' => 'ES256')));
        $payload = self::base64url_encode(wp_json_encode(array(
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $vapid['subject'],
        )));

        $data        = $header . '.' . $payload;
        $private_key = openssl_pkey_get_private($vapid['private_pem']);
        if (!$private_key) {
            return '';
        }

        $der = '';
        if (!openssl_sign($data, $der, $private_key, OPENSSL_ALGO_SHA256)) {
            return '';
        }

        return $data . '.' . self::base64url_encode(self::der_to_raw($der));
    }

    private static function der_to_raw($der) {
        if (strlen($der) === 64) {
            return $der;
        }
        $offset = 3;
        if (isset($der[1]) && (ord($der[1]) & 0x80)) {
            $offset += ord($der[2]);
        }
        $r = substr($der, $offset + 1, 32);
        $s = substr($der, $offset + 34, 32);
        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    private static function hkdf($salt, $ikm, $info, $length) {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        return substr(hash_hmac('sha256', $info . chr(1), $prk, true), 0, $length);
    }

    private static function uncompressed_to_pem($uncompressed) {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $uncompressed;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        return $pem;
    }

    public static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
