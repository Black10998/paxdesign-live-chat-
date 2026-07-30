<?php
/**
 * Sign in with Apple — identity token verification and mobile session bootstrap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAXdesign_Auth_Apple {

	const META_APPLE_SUB = 'pdx_apple_sub';
	const ISSUER         = 'https://appleid.apple.com';
	const JWKS_URL       = 'https://appleid.apple.com/auth/keys';
	const IOS_BUNDLE_ID  = 'at.paxdesign.livechat';

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

		$claims = self::verify_identity_token( $identity_token );
		if ( is_wp_error( $claims ) ) {
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'apple_login_failed', array( 'reason' => $claims->get_error_code() ), 'warn' );
			}
			return array(
				'success' => false,
				'error'   => 'apple_invalid',
				'message' => $claims->get_error_message(),
			);
		}

		$sub   = sanitize_text_field( (string) ( $claims['sub'] ?? '' ) );
		$email = sanitize_email( (string) ( $claims['email'] ?? ( $profile['email'] ?? '' ) ) );

		if ( $sub === '' ) {
			return array(
				'success' => false,
				'error'   => 'apple_invalid',
				'message' => 'Apple account identifier is missing.',
			);
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
				return array(
					'success' => false,
					'error'   => 'email_required',
					'message' => 'Apple did not share an email address. Sign in with email once, or allow email sharing with Apple.',
				);
			}
			$created = self::create_customer_from_apple( $sub, $email, $profile );
			if ( is_wp_error( $created ) ) {
				return array(
					'success' => false,
					'error'   => 'registration_failed',
					'message' => $created->get_error_message(),
				);
			}
			$user = $created;
		}

		if ( ! PAXdesign_Customers::is_login_allowed( (int) $user->ID ) ) {
			return array(
				'success' => false,
				'error'   => 'suspended',
				'message' => 'Your account has been suspended. Please contact support.',
			);
		}

		update_user_meta( (int) $user->ID, 'pdx_email_verified', 1 );
		update_user_meta( (int) $user->ID, self::META_APPLE_SUB, $sub );

		return PAXdesign_Auth_Native::mobile_login_for_user( (int) $user->ID, $device_label );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function verify_identity_token( string $token ) {
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
		if ( $aud !== self::IOS_BUNDLE_ID ) {
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
			$user = get_user_by( 'email', $email );
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
}
