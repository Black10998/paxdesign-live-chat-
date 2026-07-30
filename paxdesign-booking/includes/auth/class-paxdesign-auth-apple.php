<?php
/**
 * Sign in with Apple — mobile identity tokens and website OAuth.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAXdesign_Auth_Apple {

	const META_APPLE_SUB          = 'pdx_apple_sub';
	const OPTION_WEB_SERVICE_ID   = 'paxdesign_apple_web_service_id';
	const ISSUER                  = 'https://appleid.apple.com';
	const JWKS_URL                = 'https://appleid.apple.com/auth/keys';
	const AUTHORIZE_URL           = 'https://appleid.apple.com/auth/authorize';
	const TOKEN_URL               = 'https://appleid.apple.com/auth/token';
	const IOS_BUNDLE_ID           = 'at.paxdesign.livechat';
	const OAUTH_STATE_TTL         = 600;

	/**
	 * Complete a mobile session using a verified Apple identity token.
	 *
	 * @param array<string, mixed> $profile Optional name/email hints from the client (first sign-in only).
	 * @return array<string, mixed>
	 */
	public static function mobile_login( string $identity_token, string $device_label = '', array $profile = array() ): array {
		$identity_token = trim( $identity_token );
		if ( $identity_token === '' ) {
			return array(
				'success' => false,
				'error'   => 'missing_token',
				'message' => 'Apple identity token is required.',
			);
		}

		$claims = self::verify_identity_token( $identity_token, array( self::IOS_BUNDLE_ID ) );
		if ( is_wp_error( $claims ) ) {
			self::log_failure( $claims->get_error_code() );
			return array(
				'success' => false,
				'error'   => 'apple_invalid',
				'message' => $claims->get_error_message(),
			);
		}

		$user = self::resolve_user_from_claims( $claims, $profile );
		if ( is_wp_error( $user ) ) {
			return self::error_array_from_wp_error( $user );
		}

		return PAXdesign_Auth_Native::mobile_login_for_user( (int) $user->ID, $device_label );
	}

	/**
	 * @return string
	 */
	public static function web_service_id(): string {
		return trim( (string) get_option( self::OPTION_WEB_SERVICE_ID, '' ) );
	}

	/**
	 * Exact Return URL for Apple Developer → Service ID → Web Authentication Configuration.
	 *
	 * @return string
	 */
	public static function web_callback_url(): string {
		return rest_url( 'pdx/v1/auth/apple/callback' );
	}

	/**
	 * @return string
	 */
	public static function web_start_url(): string {
		return rest_url( 'pdx/v1/auth/apple/start' );
	}

	/**
	 * @return bool
	 */
	public static function is_web_configured(): bool {
		$cfg = self::web_config();
		return $cfg['service_id'] !== '' && $cfg['team_id'] !== '' && $cfg['key_id'] !== '' && $cfg['key_p8'] !== '';
	}

	/**
	 * Redirect the browser to Apple's authorization page.
	 *
	 * @return string|WP_Error Apple authorize URL.
	 */
	public static function web_begin_authorization( WP_REST_Request $request ) {
		if ( ! self::is_web_configured() ) {
			return new WP_Error( 'apple_web_unconfigured', 'Sign in with Apple is not configured on the server yet.' );
		}

		$cfg         = self::web_config();
		$return_path = sanitize_text_field( (string) $request->get_param( 'return_to' ) );
		$return_url  = self::sanitize_return_url( $return_path );
		$state       = self::create_oauth_state( $return_url );

		$params = array(
			'client_id'     => $cfg['service_id'],
			'redirect_uri'  => self::web_callback_url(),
			'response_type' => 'code',
			'scope'         => 'name email',
			'response_mode' => 'form_post',
			'state'         => $state,
		);

		return add_query_arg( $params, self::AUTHORIZE_URL );
	}

	/**
	 * Handle Apple's redirect back to the website.
	 *
	 * @return string Safe redirect URL for wp_safe_redirect().
	 */
	public static function web_handle_callback( WP_REST_Request $request ): string {
		$error = sanitize_text_field( (string) $request->get_param( 'error' ) );
		if ( $error !== '' ) {
			$detail = sanitize_text_field( (string) $request->get_param( 'error_description' ) );
			$msg    = $detail !== '' ? $detail : 'Apple sign-in was cancelled or denied.';
			return self::web_error_redirect_url( $msg );
		}

		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		if ( $code === '' || $state === '' ) {
			return self::web_error_redirect_url( 'Apple did not return a valid authorization response.' );
		}

		$return_url = self::consume_oauth_state( $state );
		if ( is_wp_error( $return_url ) ) {
			return self::web_error_redirect_url( $return_url->get_error_message() );
		}

		$tokens = self::exchange_authorization_code( $code );
		if ( is_wp_error( $tokens ) ) {
			self::log_failure( $tokens->get_error_code() );
			return self::web_error_redirect_url( $tokens->get_error_message() );
		}

		$id_token = (string) ( $tokens['id_token'] ?? '' );
		if ( $id_token === '' ) {
			return self::web_error_redirect_url( 'Apple did not return an identity token.' );
		}

		$profile = array();
		$user_json = (string) $request->get_param( 'user' );
		if ( $user_json !== '' ) {
			$decoded = json_decode( $user_json, true );
			if ( is_array( $decoded ) ) {
				$profile['email']       = sanitize_email( (string) ( $decoded['email'] ?? '' ) );
				$name                   = isset( $decoded['name'] ) && is_array( $decoded['name'] ) ? $decoded['name'] : array();
				$profile['given_name']  = sanitize_text_field( (string) ( $name['firstName'] ?? '' ) );
				$profile['family_name'] = sanitize_text_field( (string) ( $name['lastName'] ?? '' ) );
			}
		}

		$service_id = self::web_service_id();
		$claims     = self::verify_identity_token( $id_token, array( $service_id ) );
		if ( is_wp_error( $claims ) ) {
			self::log_failure( $claims->get_error_code() );
			return self::web_error_redirect_url( $claims->get_error_message() );
		}

		$user = self::resolve_user_from_claims( $claims, $profile );
		if ( is_wp_error( $user ) ) {
			return self::web_error_redirect_url( $user->get_error_message() );
		}

		$result = PAXdesign_Auth_Native::web_login_for_user( (int) $user->ID );
		if ( empty( $result['success'] ) ) {
			return self::web_error_redirect_url( (string) ( $result['message'] ?? 'Could not sign you in.' ) );
		}

		return $return_url;
	}

	/**
	 * @return string
	 */
	public static function web_error_redirect_url( string $message ): string {
		return add_query_arg(
			array(
				'pdx_apple' => 'error',
				'pdx_msg'   => $message,
			),
			PAXdesign_Auth_Page::page_url()
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function web_config(): array {
		return array(
			'service_id' => self::web_service_id(),
			'team_id'    => trim( (string) get_option( 'paxdesign_apns_team_id', '' ) ),
			'key_id'     => trim( (string) get_option( 'paxdesign_apns_key_id', '' ) ),
			'key_p8'     => trim( (string) get_option( 'paxdesign_apns_key_p8', '' ) ),
		);
	}

	/**
	 * @return string|WP_Error
	 */
	private static function exchange_authorization_code( string $code ) {
		$cfg = self::web_config();
		if ( ! self::is_web_configured() ) {
			return new WP_Error( 'apple_web_unconfigured', 'Sign in with Apple is not configured on the server yet.' );
		}

		$client_secret = self::make_client_secret( $cfg );
		if ( is_wp_error( $client_secret ) ) {
			return $client_secret;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'client_id'     => $cfg['service_id'],
					'client_secret' => $client_secret,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => self::web_callback_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'apple_token_exchange', 'Could not contact Apple to complete sign-in.' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			$message = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? '' ) : '';
			if ( $message === '' ) {
				$message = 'Apple token exchange failed.';
			}
			return new WP_Error( 'apple_token_exchange', $message );
		}

		return $body;
	}

	/**
	 * @param array<string, string> $cfg
	 * @return string|WP_Error
	 */
	private static function make_client_secret( array $cfg ) {
		$header = self::base64url_encode(
			wp_json_encode(
				array(
					'alg' => 'ES256',
					'kid' => $cfg['key_id'],
					'typ' => 'JWT',
				),
				JSON_UNESCAPED_SLASHES
			)
		);
		$claims = self::base64url_encode(
			wp_json_encode(
				array(
					'iss' => $cfg['team_id'],
					'iat' => time(),
					'exp' => time() + 15777000,
					'aud' => self::ISSUER,
					'sub' => $cfg['service_id'],
				),
				JSON_UNESCAPED_SLASHES
			)
		);
		$input  = $header . '.' . $claims;

		$key = openssl_pkey_get_private( $cfg['key_p8'] );
		if ( ! $key ) {
			return new WP_Error( 'apple_client_secret', 'Apple Sign in private key is invalid.' );
		}

		$signature = '';
		if ( ! openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'apple_client_secret', 'Could not sign Apple client secret.' );
		}

		$sig = self::der_to_jose( $signature );
		if ( $sig === '' ) {
			return new WP_Error( 'apple_client_secret', 'Could not encode Apple client secret signature.' );
		}

		return $input . '.' . self::base64url_encode( $sig );
	}

	/**
	 * @return string
	 */
	private static function create_oauth_state( string $return_url ): string {
		$state = bin2hex( random_bytes( 16 ) );
		set_transient(
			'pax_apple_oauth_' . hash( 'sha256', $state ),
			array(
				'return_url' => $return_url,
				'created'    => time(),
			),
			self::OAUTH_STATE_TTL
		);
		return $state;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function consume_oauth_state( string $state ) {
		$key  = 'pax_apple_oauth_' . hash( 'sha256', $state );
		$data = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $data ) || empty( $data['return_url'] ) ) {
			return new WP_Error( 'apple_state_invalid', 'Your Apple sign-in session expired. Please try again.' );
		}
		return (string) $data['return_url'];
	}

	/**
	 * @return string
	 */
	private static function sanitize_return_url( string $return_path ): string {
		$default = PAXdesign_Auth_Page::page_url() . '#/overview';
		if ( $return_path === '' ) {
			return $default;
		}

		$parsed = wp_parse_url( $return_path );
		if ( is_array( $parsed ) && ! empty( $parsed['host'] ) ) {
			$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			if ( ! is_string( $site_host ) || strcasecmp( (string) $parsed['host'], $site_host ) !== 0 ) {
				return $default;
			}
			return esc_url_raw( $return_path );
		}

		if ( $return_path[0] === '/' ) {
			return esc_url_raw( home_url( $return_path ) );
		}

		return esc_url_raw( PAXdesign_Auth_Page::page_url() . '#/overview' );
	}

	/**
	 * @param array<string, mixed> $claims
	 * @param array<string, mixed> $profile
	 * @return WP_User|WP_Error
	 */
	private static function resolve_user_from_claims( array $claims, array $profile ) {
		$sub   = sanitize_text_field( (string) ( $claims['sub'] ?? '' ) );
		$email = sanitize_email( (string) ( $claims['email'] ?? ( $profile['email'] ?? '' ) ) );

		if ( $sub === '' ) {
			return new WP_Error( 'apple_invalid', 'Apple account identifier is missing.' );
		}

		$user = self::find_user_by_apple_sub( $sub );
		if ( ! $user && $email !== '' ) {
			$by_email = get_user_by( 'email', $email );
			if ( $by_email instanceof WP_User ) {
				update_user_meta( $by_email->ID, self::META_APPLE_SUB, $sub );
				$user = $by_email;
			}
		}

		if ( ! $user ) {
			if ( $email === '' ) {
				return new WP_Error(
					'email_required',
					'Apple did not share an email address. Sign in with email once, or allow email sharing with Apple.'
				);
			}
			$created = self::create_customer_from_apple( $sub, $email, $profile );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$user = $created;
		}

		if ( ! PAXdesign_Customers::is_login_allowed( (int) $user->ID ) ) {
			return new WP_Error( 'suspended', 'Your account has been suspended. Please contact support.' );
		}

		update_user_meta( (int) $user->ID, 'pdx_email_verified', 1 );
		update_user_meta( (int) $user->ID, self::META_APPLE_SUB, $sub );

		if ( PAXdesign_Customers::STATUS_PENDING === PAXdesign_Customers::account_status( (int) $user->ID ) ) {
			PAXdesign_Customers::set_account_status( (int) $user->ID, PAXdesign_Customers::STATUS_ACTIVE );
		}

		return $user;
	}

	/**
	 * @param array<int, string> $allowed_audiences
	 * @return array<string, mixed>|WP_Error
	 */
	private static function verify_identity_token( string $token, array $allowed_audiences ) {
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 3 ) {
			return new WP_Error( 'apple_jwt', 'Invalid Apple identity token.' );
		}

		$header  = json_decode( self::base64url_decode( $parts[0] ), true );
		$payload = json_decode( self::base64url_decode( $parts[1] ), true );
		if ( ! is_array( $header ) || ! is_array( $payload ) ) {
			return new WP_Error( 'apple_jwt', 'Invalid Apple identity token payload.' );
		}

		$kid = sanitize_text_field( (string) ( $header['kid'] ?? '' ) );
		$alg = sanitize_text_field( (string) ( $header['alg'] ?? '' ) );
		if ( $kid === '' || $alg !== 'RS256' ) {
			return new WP_Error( 'apple_jwt', 'Unsupported Apple identity token.' );
		}

		$public_key = self::public_key_for_kid( $kid );
		if ( is_wp_error( $public_key ) ) {
			return $public_key;
		}

		$signed_input = $parts[0] . '.' . $parts[1];
		$signature    = self::base64url_decode( $parts[2] );
		$verified     = openssl_verify( $signed_input, $signature, $public_key, OPENSSL_ALGO_SHA256 );
		if ( $verified !== 1 ) {
			return new WP_Error( 'apple_jwt', 'Apple identity token signature is invalid.' );
		}

		$now = time();
		$iss = (string) ( $payload['iss'] ?? '' );
		$aud = (string) ( $payload['aud'] ?? '' );
		$exp = (int) ( $payload['exp'] ?? 0 );

		if ( $iss !== self::ISSUER ) {
			return new WP_Error( 'apple_jwt', 'Apple identity token issuer is invalid.' );
		}
		if ( ! in_array( $aud, $allowed_audiences, true ) ) {
			return new WP_Error( 'apple_jwt', 'Apple identity token audience is invalid.' );
		}
		if ( $exp > 0 && $exp < ( $now - 60 ) ) {
			return new WP_Error( 'apple_jwt', 'Apple identity token has expired.' );
		}

		return $payload;
	}

	/**
	 * @return resource|string|WP_Error
	 */
	private static function public_key_for_kid( string $kid ) {
		$cache_key = 'pax_apple_jwks_v1';
		$jwks      = get_transient( $cache_key );
		if ( ! is_array( $jwks ) ) {
			$response = wp_remote_get(
				self::JWKS_URL,
				array(
					'timeout' => 12,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'apple_jwks', 'Could not fetch Apple public keys.' );
			}
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body['keys'] ) || ! is_array( $body['keys'] ) ) {
				return new WP_Error( 'apple_jwks', 'Apple public keys response is invalid.' );
			}
			$jwks = $body['keys'];
			set_transient( $cache_key, $jwks, HOUR_IN_SECONDS );
		}

		foreach ( $jwks as $key ) {
			if ( ! is_array( $key ) || (string) ( $key['kid'] ?? '' ) !== $kid ) {
				continue;
			}
			$pem = self::jwk_to_pem( $key );
			if ( $pem === '' ) {
				break;
			}
			$resource = openssl_pkey_get_public( $pem );
			if ( $resource === false ) {
				return new WP_Error( 'apple_jwks', 'Apple public key is invalid.' );
			}
			return $resource;
		}

		delete_transient( $cache_key );
		return new WP_Error( 'apple_jwks', 'Apple public key was not found.' );
	}

	/**
	 * @param array<string, mixed> $jwk
	 */
	private static function jwk_to_pem( array $jwk ): string {
		if ( ( $jwk['kty'] ?? '' ) !== 'RSA' || empty( $jwk['n'] ) || empty( $jwk['e'] ) ) {
			return '';
		}
		$n = self::base64url_decode( (string) $jwk['n'] );
		$e = self::base64url_decode( (string) $jwk['e'] );
		if ( $n === '' || $e === '' ) {
			return '';
		}

		$modulus  = self::encode_asn1_integer( $n );
		$exponent = self::encode_asn1_integer( $e );
		$sequence = self::encode_asn1_sequence( $modulus . $exponent );
		$bitstr   = "\x03" . self::encode_asn1_length( strlen( $sequence ) + 1 ) . "\x00" . $sequence;
		$rsa_oid  = hex2bin( '300d06092a864886f70d0101010500' );
		$outer    = self::encode_asn1_sequence( $rsa_oid . $bitstr );
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $outer ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private static function encode_asn1_integer( string $value ): string {
		$value = ltrim( $value, "\x00" );
		if ( $value === '' ) {
			$value = "\x00";
		}
		if ( ord( $value[0] ) & 0x80 ) {
			$value = "\x00" . $value;
		}
		return "\x02" . self::encode_asn1_length( strlen( $value ) ) . $value;
	}

	private static function encode_asn1_sequence( string $value ): string {
		return "\x30" . self::encode_asn1_length( strlen( $value ) ) . $value;
	}

	private static function encode_asn1_length( int $length ): string {
		if ( $length < 0x80 ) {
			return chr( $length );
		}
		$bytes = '';
		while ( $length > 0 ) {
			$bytes = chr( $length & 0xff ) . $bytes;
			$length >>= 8;
		}
		return chr( 0x80 | strlen( $bytes ) ) . $bytes;
	}

	private static function base64url_decode( string $data ): string {
		$remainder = strlen( $data ) % 4;
		if ( $remainder > 0 ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		$decoded = base64_decode( strtr( $data, '-_', '+/' ), true );
		return $decoded === false ? '' : $decoded;
	}

	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	private static function der_to_jose( string $der ): string {
		$pos = 0;
		if ( ord( $der[ $pos++ ] ) !== 0x30 ) {
			return '';
		}
		self::read_asn1_length( $der, $pos );
		if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
			return '';
		}
		$rlen = self::read_asn1_length( $der, $pos );
		$r    = substr( $der, $pos, $rlen );
		$pos += $rlen;
		if ( ord( $der[ $pos++ ] ) !== 0x02 ) {
			return '';
		}
		$slen = self::read_asn1_length( $der, $pos );
		$s    = substr( $der, $pos, $slen );

		$r = ltrim( $r, "\x00" );
		$s = ltrim( $s, "\x00" );
		$r = str_pad( $r, 32, "\x00", STR_PAD_LEFT );
		$s = str_pad( $s, 32, "\x00", STR_PAD_LEFT );
		return $r . $s;
	}

	private static function read_asn1_length( string $data, int &$pos ): int {
		$len = ord( $data[ $pos++ ] );
		if ( ( $len & 0x80 ) === 0 ) {
			return $len;
		}
		$num = $len & 0x7f;
		$len = 0;
		for ( $i = 0; $i < $num; $i++ ) {
			$len = ( $len << 8 ) | ord( $data[ $pos++ ] );
		}
		return $len;
	}

	/**
	 * @return WP_User|null
	 */
	private static function find_user_by_apple_sub( string $sub ) {
		$users = get_users(
			array(
				'meta_key'   => self::META_APPLE_SUB,
				'meta_value' => $sub,
				'number'     => 1,
				'fields'     => 'all',
			)
		);
		return ! empty( $users[0] ) && $users[0] instanceof WP_User ? $users[0] : null;
	}

	/**
	 * @param array<string, mixed> $profile
	 * @return WP_User|WP_Error
	 */
	private static function create_customer_from_apple( string $sub, string $email, array $profile ) {
		$given  = sanitize_text_field( (string) ( $profile['given_name'] ?? '' ) );
		$family = sanitize_text_field( (string) ( $profile['family_name'] ?? '' ) );
		$name   = trim( $given . ' ' . $family );
		if ( $name === '' ) {
			$name = sanitize_text_field( (string) ( $profile['name'] ?? '' ) );
		}
		if ( $name === '' ) {
			$name = sanitize_text_field( current( explode( '@', $email ) ) );
		}

		$password = wp_generate_password( 24, true, true );
		$result   = PAXdesign_Auth::register( $email, $password, $name );
		if ( empty( $result['success'] ) ) {
			$message = (string) ( $result['message'] ?? 'Could not create your account.' );
			if ( ! empty( $result['error'] ) && $result['error'] === 'email_exists' ) {
				$existing = get_user_by( 'email', $email );
				if ( $existing instanceof WP_User ) {
					update_user_meta( $existing->ID, self::META_APPLE_SUB, $sub );
					return $existing;
				}
			}
			return new WP_Error( 'registration_failed', $message );
		}

		$user_id = (int) ( $result['user_id'] ?? 0 );
		if ( $user_id <= 0 ) {
			$user    = get_user_by( 'email', $email );
			$user_id = $user instanceof WP_User ? (int) $user->ID : 0;
		}
		if ( $user_id <= 0 ) {
			return new WP_Error( 'registration_failed', 'Could not create your account.' );
		}

		update_user_meta( $user_id, self::META_APPLE_SUB, $sub );
		update_user_meta( $user_id, 'pdx_email_verified', 1 );

		$created = get_user_by( 'id', $user_id );
		return $created instanceof WP_User ? $created : new WP_Error( 'registration_failed', 'Could not create your account.' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function error_array_from_wp_error( WP_Error $error ): array {
		$code = $error->get_error_code();
		$map  = array(
			'email_required'      => 'email_required',
			'suspended'           => 'suspended',
			'registration_failed' => 'registration_failed',
		);
		return array(
			'success' => false,
			'error'   => isset( $map[ $code ] ) ? $map[ $code ] : 'apple_invalid',
			'message' => $error->get_error_message(),
		);
	}

	private static function log_failure( string $reason ): void {
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'apple_login_failed', array( 'reason' => $reason ), 'warn' );
		}
	}
}
