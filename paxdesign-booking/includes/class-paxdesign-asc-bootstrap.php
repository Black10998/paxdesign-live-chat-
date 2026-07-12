<?php
/**
 * App Store Connect key bootstrap for remote plugin update and APNs setup.
 *
 * Authenticates callers that possess the ASC .p8 key (same key used for TestFlight).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_ASC_Bootstrap {

    const AUDIENCE = 'paxdesign-wordpress-bootstrap';

    /**
     * @return string
     */
    public static function expected_team_id() {
        $team = trim((string) get_option('paxdesign_apns_team_id', ''));
        if ($team !== '') {
            return $team;
        }

        return '4ZSP8S5A7B';
    }

    /**
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public static function authorize_request(WP_REST_Request $request) {
        $auth = trim((string) $request->get_header('authorization'));
        if ($auth === '' || stripos($auth, 'bearer ') !== 0) {
            return new WP_Error('asc_auth_missing', 'Authorization Bearer token required.', array('status' => 401));
        }

        $jwt = trim(substr($auth, 7));
        if ($jwt === '') {
            return new WP_Error('asc_auth_missing', 'Authorization Bearer token required.', array('status' => 401));
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $key_id = isset($params['key_id']) ? sanitize_text_field((string) $params['key_id']) : '';
        $key_p8 = isset($params['key_p8']) ? trim((string) $params['key_p8']) : '';
        $team_id = isset($params['team_id']) ? sanitize_text_field((string) $params['team_id']) : self::expected_team_id();

        if ($key_id === '' || $key_p8 === '') {
            return new WP_Error('asc_auth_fields', 'key_id and key_p8 are required.', array('status' => 400));
        }

        if (!self::verify_jwt($jwt, $key_id, $team_id, $key_p8)) {
            return new WP_Error('asc_auth_invalid', 'Invalid App Store Connect bootstrap token.', array('status' => 403));
        }

        return true;
    }

    /**
     * @param string $jwt
     * @param string $key_id
     * @param string $team_id
     * @param string $key_p8
     * @return bool
     */
    public static function verify_jwt($jwt, $key_id, $team_id, $key_p8) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }

        list($header_b64, $claims_b64, $sig_b64) = $parts;
        $header = json_decode(self::base64url_decode($header_b64), true);
        $claims = json_decode(self::base64url_decode($claims_b64), true);
        if (!is_array($header) || !is_array($claims)) {
            return false;
        }

        if (($header['alg'] ?? '') !== 'ES256' || ($header['kid'] ?? '') !== $key_id) {
            return false;
        }

        if (($claims['iss'] ?? '') !== $team_id) {
            return false;
        }

        if (($claims['aud'] ?? '') !== self::AUDIENCE) {
            return false;
        }

        $now = time();
        $iat = isset($claims['iat']) ? (int) $claims['iat'] : 0;
        $exp = isset($claims['exp']) ? (int) $claims['exp'] : 0;
        if ($iat <= 0 || $exp <= 0 || $iat > ($now + 60) || $exp < ($now - 30)) {
            return false;
        }

        $key = openssl_pkey_get_private($key_p8);
        if (!$key) {
            return false;
        }

        $input = $header_b64 . '.' . $claims_b64;
        $signature = self::base64url_decode($sig_b64);
        if ($signature === '') {
            return false;
        }

        $der = self::jose_to_der($signature);
        if ($der === '') {
            return false;
        }

        $verified = openssl_verify($input, $der, $key, OPENSSL_ALGO_SHA256);
        return $verified === 1;
    }

    /**
     * @param string $data
     * @return string
     */
    private static function base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }

    /**
     * @param string $jose
     * @return string
     */
    private static function jose_to_der($jose) {
        if (strlen($jose) < 64) {
            return '';
        }

        $r = substr($jose, 0, 32);
        $s = substr($jose, 32, 32);
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        if ($r !== '' && (ord($r[0]) & 0x80)) {
            $r = "\x00" . $r;
        }
        if ($s !== '' && (ord($s[0]) & 0x80)) {
            $s = "\x00" . $s;
        }

        $seq = chr(0x02) . chr(strlen($r)) . $r . chr(0x02) . chr(strlen($s)) . $s;
        return chr(0x30) . chr(strlen($seq)) . $seq;
    }
}
