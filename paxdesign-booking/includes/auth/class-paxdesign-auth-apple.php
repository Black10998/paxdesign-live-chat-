<?php
/**
 * Sign in with Apple — mobile identity tokens and website OAuth.
 *
 * Mobile (iOS app): client sends identity_token; audience = IOS_BUNDLE_ID; no client_secret.
 * Website (OAuth):  browser authorization code; client_id = web Service ID; server exchanges
 *                   code using ES256 client_secret JWT (sub = Service ID, not bundle ID).
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
	const OAUTH_STATE_TTL         = 1800;
	const LOGIN_TICKET_TTL        = 600;
	const OPTION_TRACE            = 'paxdesign_apple_oauth_trace';
	const OPTION_LAST_ERROR       = 'paxdesign_apple_oauth_last_error';

	/**
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( __CLASS__, 'maybe_finish_web_login' ), 0 );
	}

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
		$path = '/' . ltrim( rest_get_url_prefix(), '/' ) . '/pdx/v1/auth/apple/callback';
		return untrailingslashit( home_url( $path, 'https' ) );
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

		$cfg           = self::web_config();
		$return_path   = sanitize_text_field( (string) $request->get_param( 'return_to' ) );
		$return_url    = self::sanitize_return_url( $return_path );
		$redirect_uri  = self::web_callback_url();
		$state         = self::create_oauth_state( $return_url, $cfg['service_id'], $redirect_uri );

		$params = array(
			'client_id'     => $cfg['service_id'],
			'redirect_uri'  => $redirect_uri,
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
		self::trace( 'callback_start', array( 'method' => $request->get_method() ) );
		$params = self::callback_params( $request );

		$error = sanitize_text_field( (string) ( $params['error'] ?? '' ) );
		if ( $error !== '' ) {
			$detail = sanitize_text_field( (string) ( $params['error_description'] ?? '' ) );
			$msg    = $detail !== '' ? $detail : 'Apple sign-in was cancelled or denied.';
			self::log_failure( 'apple_callback_denied', array( 'error' => $error ) );
			self::trace( 'callback_denied', array( 'error' => $error ) );
			return self::web_error_redirect_url( $msg );
		}

		$code  = self::normalize_authorization_code( (string) ( $params['code'] ?? '' ) );
		$state = sanitize_text_field( (string) ( $params['state'] ?? '' ) );
		if ( $code === '' || $state === '' ) {
			self::log_failure( 'apple_callback_missing_params', array( 'has_code' => $code !== '', 'has_state' => $state !== '' ) );
			self::trace( 'callback_missing_params', array( 'has_code' => $code !== '', 'has_state' => $state !== '' ) );
			return self::web_error_redirect_url( 'Apple did not return a valid authorization response.' );
		}

		self::trace(
			'callback_received_code',
			array(
				'code_len'    => strlen( $code ),
				'code_parts'  => substr_count( $code, '.' ) + 1,
				'content_type'=> (string) ( $_SERVER['CONTENT_TYPE'] ?? '' ),
			)
		);

		$state_data = self::consume_oauth_state( $state );
		if ( is_wp_error( $state_data ) ) {
			self::log_failure( 'apple_state_invalid' );
			self::trace( 'callback_state_invalid' );
			return self::web_error_redirect_url( $state_data->get_error_message() );
		}

		$return_url   = (string) ( $state_data['return_url'] ?? '' );
		$client_id    = (string) ( $state_data['client_id'] ?? '' );
		$redirect_uri = (string) ( $state_data['redirect_uri'] ?? '' );

		$tokens = self::exchange_authorization_code( $code, $client_id, $redirect_uri );
		if ( is_wp_error( $tokens ) ) {
			$fail_ctx = array_merge(
				self::token_exchange_trace_context( $client_id, $redirect_uri ),
				array(
					'reason'  => $tokens->get_error_code(),
					'message' => $tokens->get_error_message(),
				)
			);
			$extra = $tokens->get_error_data();
			if ( is_array( $extra ) ) {
				$fail_ctx = array_merge( $fail_ctx, $extra );
			}
			self::log_failure( 'apple_token_exchange', array( 'reason' => $tokens->get_error_code(), 'message' => $tokens->get_error_message() ) );
			self::trace( 'callback_token_exchange_failed', $fail_ctx );
			return self::web_error_redirect_url( self::friendly_token_exchange_error( $tokens->get_error_message() ) );
		}

		$id_token = (string) ( $tokens['id_token'] ?? '' );
		if ( $id_token === '' ) {
			self::log_failure( 'apple_missing_id_token' );
			self::trace( 'callback_missing_id_token' );
			return self::web_error_redirect_url( 'Apple did not return an identity token.' );
		}

		$profile = self::profile_from_callback_params( $params );

		$service_id = self::web_service_id();
		$claims     = self::verify_identity_token( $id_token, array( $service_id ) );
		if ( is_wp_error( $claims ) ) {
			self::log_failure( 'apple_jwt_verify', array( 'reason' => $claims->get_error_code(), 'message' => $claims->get_error_message() ) );
			self::trace( 'callback_jwt_verify_failed', array( 'reason' => $claims->get_error_code(), 'message' => $claims->get_error_message() ) );
			return self::web_error_redirect_url( $claims->get_error_message() );
		}

		$user = self::resolve_user_from_claims( $claims, $profile );
		if ( is_wp_error( $user ) ) {
			self::log_failure( 'apple_user_resolve', array( 'reason' => $user->get_error_code(), 'message' => $user->get_error_message() ) );
			self::trace( 'callback_user_resolve_failed', array( 'reason' => $user->get_error_code(), 'message' => $user->get_error_message() ) );
			return self::web_error_redirect_url( $user->get_error_message() );
		}

		$finish_url = self::web_complete_url_for_user( (int) $user->ID, $return_url );
		self::trace( 'callback_success', array( 'user_id' => (int) $user->ID ) );
		return $finish_url;
	}

	/**
	 * Finish login on a normal WordPress page request (reliable auth cookies).
	 *
	 * @return void
	 */
	public static function maybe_finish_web_login(): void {
		if ( empty( $_GET['pdx_apple_finish'] ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$ticket = sanitize_text_field( wp_unslash( (string) $_GET['pdx_apple_finish'] ) );
		if ( $ticket === '' ) {
			return;
		}

		self::trace( 'finish_request', array( 'ticket_len' => strlen( $ticket ) ) );
		nocache_headers();

		$redirect = self::complete_login_from_ticket( $ticket );
		wp_safe_redirect( self::safe_redirect_url( $redirect ) );
		exit;
	}

	/**
	 * @return string
	 */
	public static function web_complete_login( WP_REST_Request $request ): string {
		$ticket = sanitize_text_field( (string) $request->get_param( 'ticket' ) );
		self::trace( 'complete_request_rest', array( 'ticket_len' => strlen( $ticket ) ) );
		return self::complete_login_from_ticket( $ticket );
	}

	/**
	 * @return string
	 */
	public static function complete_login_from_ticket( string $ticket ): string {
		if ( $ticket === '' ) {
			self::trace( 'complete_missing_ticket' );
			return self::web_error_redirect_url( 'Apple sign-in session is invalid. Please try again.' );
		}

		$data = self::consume_login_ticket( $ticket );
		if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
			self::log_failure( 'apple_complete_ticket_invalid' );
			self::trace( 'complete_ticket_invalid' );
			return self::web_error_redirect_url( 'Apple sign-in session expired. Please try again.' );
		}

		$result = PAXdesign_Auth_Native::web_login_for_user( (int) $data['user_id'] );
		if ( empty( $result['success'] ) ) {
			self::log_failure( 'apple_web_session', array( 'message' => (string) ( $result['message'] ?? '' ) ) );
			self::trace( 'complete_session_failed', array( 'message' => (string) ( $result['message'] ?? '' ) ) );
			return self::web_error_redirect_url( (string) ( $result['message'] ?? 'Could not sign you in.' ) );
		}

		self::trace( 'complete_success', array( 'user_id' => (int) $data['user_id'] ) );
		if ( PAXdesign_Auth_Native::is_owner_account( (int) $data['user_id'] ) ) {
			return admin_url();
		}
		return (string) ( $data['return_url'] ?? ( PAXdesign_Auth_Page::page_url() . '#/overview' ) );
	}

	/**
	 * @return string
	 */
	public static function web_complete_url_for_user( int $user_id, string $return_url ): string {
		$ticket = bin2hex( random_bytes( 16 ) );
		self::store_login_ticket(
			$ticket,
			array(
				'user_id'    => $user_id,
				'return_url' => $return_url,
				'created'    => time(),
			)
		);

		return add_query_arg( 'pdx_apple_finish', $ticket, PAXdesign_Auth_Page::page_url() );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function store_login_ticket( string $ticket, array $data ): void {
		$key = self::login_ticket_key( $ticket );
		set_transient( $key, $data, self::LOGIN_TICKET_TTL );
		update_option( $key, $data, false );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function consume_login_ticket( string $ticket ) {
		$key  = self::login_ticket_key( $ticket );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$stored = get_option( $key, null );
			$data   = is_array( $stored ) ? $stored : null;
		}
		delete_transient( $key );
		delete_option( $key );
		return $data;
	}

	/**
	 * @return string
	 */
	private static function login_ticket_key( string $ticket ): string {
		return 'pax_apple_login_' . hash( 'sha256', $ticket );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function trace( string $step, array $context = array() ): void {
		$rows = get_option( self::OPTION_TRACE, array() );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}
		$rows[] = array_merge(
			array(
				't'    => gmdate( 'c' ),
				'step' => $step,
			),
			$context
		);
		if ( count( $rows ) > 40 ) {
			$rows = array_slice( $rows, -40 );
		}
		update_option( self::OPTION_TRACE, $rows, false );
	}

	/**
	 * Preserve URL fragments that wp_validate_redirect() may strip.
	 *
	 * @return string
	 */
	public static function safe_redirect_url( string $url ): string {
		$fallback = PAXdesign_Auth_Page::page_url();
		$safe     = wp_validate_redirect( $url, $fallback );
		if ( ! is_string( $safe ) || $safe === '' ) {
			return $fallback;
		}
		if ( strpos( $url, '#' ) !== false && strpos( $safe, '#' ) === false ) {
			$safe .= substr( $url, strpos( $url, '#' ) );
		}
		return $safe;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function callback_params( WP_REST_Request $request ): array {
		$params = $request->get_body_params();
		if ( ! is_array( $params ) || $params === array() ) {
			$params = $request->get_params();
		}
		if ( ( ! is_array( $params ) || $params === array() ) && ! empty( $_POST ) && is_array( $_POST ) ) {
			$params = wp_unslash( $_POST );
		}
		return is_array( $params ) ? $params : array();
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private static function profile_from_callback_params( array $params ): array {
		$profile   = array();
		$user_json = (string) ( $params['user'] ?? '' );
		if ( $user_json === '' ) {
			return $profile;
		}

		$decoded = json_decode( wp_unslash( $user_json ), true );
		if ( ! is_array( $decoded ) ) {
			$decoded = json_decode( $user_json, true );
		}
		if ( ! is_array( $decoded ) ) {
			return $profile;
		}

		$profile['email'] = sanitize_email( (string) ( $decoded['email'] ?? '' ) );
		$name             = isset( $decoded['name'] ) && is_array( $decoded['name'] ) ? $decoded['name'] : array();
		$profile['given_name']  = sanitize_text_field( (string) ( $name['firstName'] ?? '' ) );
		$profile['family_name'] = sanitize_text_field( (string) ( $name['lastName'] ?? '' ) );
		return $profile;
	}

	/**
	 * @return string
	 */
	public static function web_error_redirect_url( string $message ): string {
		update_option(
			self::OPTION_LAST_ERROR,
			array(
				't'       => gmdate( 'c' ),
				'message' => $message,
			),
			false
		);
		self::trace( 'error_redirect', array( 'message' => $message ) );

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
		$web_key_id = trim( (string) get_option( 'paxdesign_apple_web_key_id', '' ) );
		$web_key_p8 = self::normalize_private_key( (string) get_option( 'paxdesign_apple_web_key_p8', '' ) );
		$apns_key_id = trim( (string) get_option( 'paxdesign_apns_key_id', '' ) );
		$apns_key_p8 = self::normalize_private_key( (string) get_option( 'paxdesign_apns_key_p8', '' ) );

		return array(
			'service_id' => self::web_service_id(),
			'team_id'    => trim( (string) get_option( 'paxdesign_apns_team_id', '' ) ),
			'key_id'     => $web_key_id !== '' ? $web_key_id : $apns_key_id,
			'key_p8'     => $web_key_p8 !== '' ? $web_key_p8 : $apns_key_p8,
		);
	}

	/**
	 * @return string|WP_Error
	 */
	private static function exchange_authorization_code( string $code, string $client_id = '', string $redirect_uri = '' ) {
		$cfg = self::web_config();
		if ( ! self::is_web_configured() ) {
			return new WP_Error( 'apple_web_unconfigured', 'Sign in with Apple is not configured on the server yet.' );
		}

		if ( $client_id === '' ) {
			$client_id = $cfg['service_id'];
		}
		if ( $redirect_uri === '' ) {
			$redirect_uri = self::web_callback_url();
		}

		$secret_cfg             = $cfg;
		$secret_cfg['service_id'] = $client_id;
		$client_secret          = self::make_client_secret( $secret_cfg );
		if ( is_wp_error( $client_secret ) ) {
			return $client_secret;
		}

		$post_body = self::build_token_exchange_body( $client_id, $client_secret, $code, $redirect_uri );

		$http = self::apple_token_http_post( $post_body );
		if ( is_wp_error( $http ) ) {
			return $http;
		}

		$status = (int) ( $http['status'] ?? 0 );
		$raw    = (string) ( $http['body'] ?? '' );
		$body   = json_decode( $raw, true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			$error_code = is_array( $body ) ? (string) ( $body['error'] ?? '' ) : '';
			$message    = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? '' ) : '';
			if ( $message === '' ) {
				$message = 'Apple token exchange failed.';
			}
			$err = new WP_Error( 'apple_token_exchange', $message );
			$err->add_data(
				array(
					'apple_http_status'        => $status,
					'apple_error'              => $error_code,
					'apple_error_description'  => is_array( $body ) ? (string) ( $body['error_description'] ?? '' ) : '',
					'code_len'                 => strlen( $code ),
					'client_secret_len'        => strlen( $client_secret ),
					'transport'                => (string) ( $http['transport'] ?? '' ),
				)
			);
			return $err;
		}

		self::trace( 'callback_token_exchange_ok', self::token_exchange_trace_context( $client_id, $redirect_uri ) );
		return $body;
	}

	/**
	 * @return string
	 */
	private static function build_token_exchange_body( string $client_id, string $client_secret, string $code, string $redirect_uri ): string {
		return http_build_query(
			array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'code'          => $code,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $redirect_uri,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function apple_token_http_post( string $post_body ) {
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/x-www-form-urlencoded',
			'User-Agent: PAXDesign-AppleWebOAuth/' . ( defined( 'PAXDESIGN_BOOKING_VERSION' ) ? PAXDESIGN_BOOKING_VERSION : '1.0' ),
		);

		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init( self::TOKEN_URL );
			if ( $ch !== false ) {
				curl_setopt_array(
					$ch,
					array(
						CURLOPT_POST           => true,
						CURLOPT_POSTFIELDS     => $post_body,
						CURLOPT_HTTPHEADER     => $headers,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_TIMEOUT        => 20,
					)
				);
				$raw    = curl_exec( $ch );
				$status = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
				$error  = curl_error( $ch );
				curl_close( $ch );
				if ( is_string( $raw ) ) {
					return array(
						'status'    => $status,
						'body'      => $raw,
						'transport' => 'curl',
					);
				}
				if ( $error !== '' ) {
					return new WP_Error( 'apple_token_exchange', 'Could not contact Apple to complete sign-in.' );
				}
			}
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
					'User-Agent'   => 'PAXDesign-AppleWebOAuth/' . ( defined( 'PAXDESIGN_BOOKING_VERSION' ) ? PAXDESIGN_BOOKING_VERSION : '1.0' ),
				),
				'body'    => $post_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'apple_token_exchange', 'Could not contact Apple to complete sign-in.' );
		}

		return array(
			'status'    => (int) wp_remote_retrieve_response_code( $response ),
			'body'      => wp_remote_retrieve_body( $response ),
			'transport' => 'wp_remote_post',
		);
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
					'iat' => time() - 30,
					'exp' => time() + MONTH_IN_SECONDS * 3,
					'aud' => self::ISSUER,
					'sub' => $cfg['service_id'],
				),
				JSON_UNESCAPED_SLASHES
			)
		);
		$input  = $header . '.' . $claims;

		$key = openssl_pkey_get_private( self::normalize_private_key( $cfg['key_p8'] ) );
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
	private static function create_oauth_state( string $return_url, string $client_id, string $redirect_uri ): string {
		$state = bin2hex( random_bytes( 16 ) );
		$key   = 'pax_apple_oauth_' . hash( 'sha256', $state );
		$data  = array(
			'return_url'   => $return_url,
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'created'      => time(),
		);
		set_transient( $key, $data, self::OAUTH_STATE_TTL );
		update_option( $key, $data, false );
		self::trace(
			'oauth_state_created',
			array(
				'client_id'    => $client_id,
				'redirect_uri' => $redirect_uri,
			)
		);
		return $state;
	}

	/**
	 * @return array<string, string>|WP_Error
	 */
	private static function consume_oauth_state( string $state ) {
		$key  = 'pax_apple_oauth_' . hash( 'sha256', $state );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$stored = get_option( $key, null );
			$data   = is_array( $stored ) ? $stored : null;
		}
		delete_transient( $key );
		delete_option( $key );
		if ( ! is_array( $data ) || empty( $data['return_url'] ) ) {
			return new WP_Error( 'apple_state_invalid', 'Your Apple sign-in session expired. Please try again.' );
		}
		return array(
			'return_url'   => (string) $data['return_url'],
			'client_id'    => (string) ( $data['client_id'] ?? self::web_service_id() ),
			'redirect_uri' => (string) ( $data['redirect_uri'] ?? self::web_callback_url() ),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function token_exchange_trace_context( string $client_id, string $redirect_uri ): array {
		$cfg = self::web_config();
		return array(
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'key_id'       => $cfg['key_id'],
			'team_id_tail' => $cfg['team_id'] !== '' ? substr( $cfg['team_id'], -4 ) : '',
		);
	}

	/**
	 * @return string
	 */
	private static function friendly_token_exchange_error( string $message ): string {
		$normalized = strtolower( trim( $message ) );
		if ( $normalized === 'invalid_client' ) {
			return 'Apple could not verify the website sign-in configuration (invalid_client). Please try again in a private window. If this continues, contact support.';
		}
		if ( $normalized === 'invalid_grant' ) {
			return 'Your Apple sign-in authorization expired. Please start again from the account page.';
		}
		return $message !== '' ? $message : 'Apple sign-in failed. Please try again.';
	}

	/**
	 * @return string
	 */
	private static function normalize_authorization_code( string $code ): string {
		$code = trim( wp_unslash( $code ) );
		if ( $code === '' ) {
			return '';
		}
		if ( preg_match( '/%[0-9A-Fa-f]{2}/', $code ) ) {
			$decoded = rawurldecode( $code );
			if ( is_string( $decoded ) && $decoded !== '' ) {
				$code = $decoded;
			}
		}
		return $code;
	}

	/**
	 * @return string
	 */
	private static function normalize_private_key( string $key ): string {
		$key = trim( $key );
		if ( $key === '' ) {
			return '';
		}
		if ( strpos( $key, '\\n' ) !== false ) {
			$key = str_replace( '\\n', "\n", $key );
		}
		return $key;
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

		// Same Master Administrator may sign in with iCloud or Apple Private Relay email.
		if ( ! $user && $email !== '' && class_exists( 'PAXdesign_Customer_Master_Admin' ) && PAXdesign_Customer_Master_Admin::is_master_email( $email ) ) {
			$master = PAXdesign_Customer_Master_Admin::find_master_user();
			if ( $master instanceof WP_User ) {
				update_user_meta( (int) $master->ID, self::META_APPLE_SUB, $sub );
				$user = $master;
			}
		}

		if ( ! $user ) {
			if ( $email === '' ) {
				$email = self::apple_account_email_for_sub( $sub );
			}
			$created = self::create_customer_from_apple( $sub, $email, $profile );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$user = $created;
		} else {
			self::maybe_sync_account_email( (int) $user->ID, $email );
		}

		if ( ! PAXdesign_Customers::is_login_allowed( (int) $user->ID ) ) {
			return new WP_Error( 'suspended', 'Your account has been suspended. Please contact support.' );
		}

		update_user_meta( (int) $user->ID, 'pdx_email_verified', 1 );
		update_user_meta( (int) $user->ID, self::META_APPLE_SUB, $sub );

		if ( PAXdesign_Customers::STATUS_PENDING === PAXdesign_Customers::account_status( (int) $user->ID ) ) {
			PAXdesign_Customers::set_account_status( (int) $user->ID, PAXdesign_Customers::STATUS_ACTIVE );
		}

		if ( class_exists( 'PAXdesign_Customer_Registry' ) ) {
			PAXdesign_Customer_Registry::ensure_portal_customer( (int) $user->ID );
		}

		return $user;
	}

	/**
	 * Keep the WordPress account email aligned with Apple Sign in (including Private Relay).
	 *
	 * @param int $user_id
	 * @param string $email
	 */
	private static function maybe_sync_account_email( int $user_id, string $email ): void {
		$email = sanitize_email( $email );
		if ( $user_id <= 0 || $email === '' || ! is_email( $email ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$current = trim( (string) $user->user_email );
		if ( $current === $email ) {
			return;
		}

		$should_update = ( $current === '' || ! is_email( $current ) || self::is_generated_apple_email( $current ) );
		if ( ! $should_update ) {
			return;
		}

		$existing = email_exists( $email );
		if ( $existing && (int) $existing !== $user_id ) {
			return;
		}

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $email,
			)
		);
	}

	/**
	 * @param string $email
	 * @return bool
	 */
	private static function is_generated_apple_email( string $email ): bool {
		$email = strtolower( trim( $email ) );
		return (bool) preg_match( '/^apple\+[a-f0-9]{32}@id\.paxdesign\.at$/', $email );
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
		$exp = (int) ( $payload['exp'] ?? 0 );

		if ( $iss !== self::ISSUER ) {
			return new WP_Error( 'apple_jwt', 'Apple identity token issuer is invalid.' );
		}
		if ( ! self::audience_matches( $payload, $allowed_audiences ) ) {
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

	/**
	 * Stable internal email when Apple omits email on repeat web authorizations.
	 *
	 * @return string
	 */
	private static function apple_account_email_for_sub( string $sub ): string {
		return 'apple+' . substr( hash( 'sha256', $sub ), 0, 32 ) . '@id.paxdesign.at';
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<int, string>   $allowed_audiences
	 */
	private static function audience_matches( array $payload, array $allowed_audiences ): bool {
		$aud = $payload['aud'] ?? '';
		if ( is_array( $aud ) ) {
			foreach ( $aud as $value ) {
				if ( in_array( (string) $value, $allowed_audiences, true ) ) {
					return true;
				}
			}
			return false;
		}

		if ( in_array( (string) $aud, $allowed_audiences, true ) ) {
			return true;
		}

		$azp = (string) ( $payload['azp'] ?? '' );
		return $azp !== '' && in_array( $azp, $allowed_audiences, true );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function log_failure( string $reason, array $context = array() ): void {
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'apple_login_failed', array_merge( array( 'reason' => $reason ), $context ), 'warn' );
		}
	}
}
