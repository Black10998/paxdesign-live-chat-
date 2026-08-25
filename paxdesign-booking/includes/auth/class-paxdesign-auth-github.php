<?php
/**
 * Sign in with GitHub — website OAuth for the customer account page.
 *
 * Uses a GitHub OAuth App (Client ID + Client Secret). Credentials live in
 * WordPress options and can be filled from GitHub Actions secrets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAXdesign_Auth_GitHub {

	const META_GITHUB_ID          = 'pdx_github_id';
	const META_GITHUB_LOGIN       = 'pdx_github_login';
	const OPTION_CLIENT_ID        = 'paxdesign_github_oauth_client_id';
	const OPTION_CLIENT_SECRET    = 'paxdesign_github_oauth_client_secret';
	const OPTION_TRACE            = 'paxdesign_github_oauth_trace';
	const OPTION_LAST_ERROR       = 'paxdesign_github_oauth_last_error';
	const AUTHORIZE_URL           = 'https://github.com/login/oauth/authorize';
	const TOKEN_URL               = 'https://github.com/login/oauth/access_token';
	const USER_URL                = 'https://api.github.com/user';
	const EMAILS_URL              = 'https://api.github.com/user/emails';
	const OAUTH_STATE_TTL         = 1800;
	const LOGIN_TICKET_TTL        = 600;

	/**
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( __CLASS__, 'maybe_finish_web_login' ), 0 );
	}

	/**
	 * @return string
	 */
	public static function client_id(): string {
		return trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
	}

	/**
	 * @return string
	 */
	public static function client_secret(): string {
		return trim( (string) get_option( self::OPTION_CLIENT_SECRET, '' ) );
	}

	/**
	 * Exact Authorization callback URL for the GitHub OAuth App.
	 *
	 * @return string
	 */
	public static function web_callback_url(): string {
		$path = '/' . ltrim( rest_get_url_prefix(), '/' ) . '/pdx/v1/auth/github/callback';
		return untrailingslashit( home_url( $path, 'https' ) );
	}

	/**
	 * @return string
	 */
	public static function web_start_url(): string {
		return rest_url( 'pdx/v1/auth/github/start' );
	}

	/**
	 * @return bool
	 */
	public static function is_web_configured(): bool {
		return self::client_id() !== '' && self::client_secret() !== '';
	}

	/**
	 * Redirect the browser to GitHub's authorization page.
	 *
	 * @return string|WP_Error GitHub authorize URL.
	 */
	public static function web_begin_authorization( WP_REST_Request $request ) {
		if ( ! self::is_web_configured() ) {
			return new WP_Error( 'github_web_unconfigured', 'Sign in with GitHub is not configured on the server yet.' );
		}

		$return_path  = sanitize_text_field( (string) $request->get_param( 'return_to' ) );
		$return_url   = self::sanitize_return_url( $return_path );
		$redirect_uri = self::web_callback_url();
		$client_id    = self::client_id();
		$state        = self::create_oauth_state( $return_url, $client_id, $redirect_uri );

		$params = array(
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'scope'        => 'user:email',
			'state'        => $state,
			'allow_signup' => 'true',
		);

		return add_query_arg( $params, self::AUTHORIZE_URL );
	}

	/**
	 * Handle GitHub's redirect back to the website.
	 *
	 * @return string Safe redirect URL for wp_safe_redirect().
	 */
	public static function web_handle_callback( WP_REST_Request $request ): string {
		self::trace( 'callback_start', array( 'method' => $request->get_method() ) );
		$params = $request->get_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$error = sanitize_text_field( (string) ( $params['error'] ?? '' ) );
		if ( $error !== '' ) {
			$detail = sanitize_text_field( (string) ( $params['error_description'] ?? '' ) );
			$msg    = $detail !== '' ? $detail : 'GitHub sign-in was cancelled or denied.';
			self::log_failure( 'github_callback_denied', array( 'error' => $error ) );
			self::trace( 'callback_denied', array( 'error' => $error ) );
			return self::web_error_redirect_url( $msg );
		}

		$code  = sanitize_text_field( (string) ( $params['code'] ?? '' ) );
		$state = sanitize_text_field( (string) ( $params['state'] ?? '' ) );
		if ( $code === '' || $state === '' ) {
			self::log_failure( 'github_callback_missing_params', array( 'has_code' => $code !== '', 'has_state' => $state !== '' ) );
			self::trace( 'callback_missing_params', array( 'has_code' => $code !== '', 'has_state' => $state !== '' ) );
			return self::web_error_redirect_url( 'GitHub did not return a valid authorization response.' );
		}

		$state_data = self::consume_oauth_state( $state );
		if ( is_wp_error( $state_data ) ) {
			self::log_failure( 'github_state_invalid' );
			self::trace( 'callback_state_invalid' );
			return self::web_error_redirect_url( $state_data->get_error_message() );
		}

		$return_url   = (string) ( $state_data['return_url'] ?? '' );
		$client_id    = (string) ( $state_data['client_id'] ?? self::client_id() );
		$redirect_uri = (string) ( $state_data['redirect_uri'] ?? self::web_callback_url() );

		$tokens = self::exchange_authorization_code( $code, $client_id, $redirect_uri );
		if ( is_wp_error( $tokens ) ) {
			self::log_failure( 'github_token_exchange', array( 'reason' => $tokens->get_error_code(), 'message' => $tokens->get_error_message() ) );
			self::trace( 'callback_token_exchange_failed', array( 'reason' => $tokens->get_error_code() ) );
			return self::web_error_redirect_url( 'GitHub sign-in failed. Please try again.' );
		}

		$access_token = (string) ( $tokens['access_token'] ?? '' );
		if ( $access_token === '' ) {
			self::log_failure( 'github_missing_access_token' );
			self::trace( 'callback_missing_access_token' );
			return self::web_error_redirect_url( 'GitHub did not return an access token.' );
		}

		$profile = self::fetch_github_profile( $access_token );
		if ( is_wp_error( $profile ) ) {
			self::log_failure( 'github_profile', array( 'reason' => $profile->get_error_code(), 'message' => $profile->get_error_message() ) );
			self::trace( 'callback_profile_failed', array( 'reason' => $profile->get_error_code() ) );
			return self::web_error_redirect_url( $profile->get_error_message() );
		}

		$user = self::resolve_user_from_profile( $profile );
		if ( is_wp_error( $user ) ) {
			self::log_failure( 'github_user_resolve', array( 'reason' => $user->get_error_code(), 'message' => $user->get_error_message() ) );
			self::trace( 'callback_user_resolve_failed', array( 'reason' => $user->get_error_code() ) );
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
		if ( empty( $_GET['pdx_github_finish'] ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$ticket = sanitize_text_field( wp_unslash( (string) $_GET['pdx_github_finish'] ) );
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
	public static function complete_login_from_ticket( string $ticket ): string {
		if ( $ticket === '' ) {
			self::trace( 'complete_missing_ticket' );
			return self::web_error_redirect_url( 'GitHub sign-in session is invalid. Please try again.' );
		}

		$data = self::consume_login_ticket( $ticket );
		if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
			self::log_failure( 'github_complete_ticket_invalid' );
			self::trace( 'complete_ticket_invalid' );
			return self::web_error_redirect_url( 'GitHub sign-in session expired. Please try again.' );
		}

		$result = PAXdesign_Auth_Native::web_login_for_user( (int) $data['user_id'] );
		if ( empty( $result['success'] ) ) {
			self::log_failure( 'github_web_session', array( 'message' => (string) ( $result['message'] ?? '' ) ) );
			self::trace( 'complete_session_failed', array( 'message' => (string) ( $result['message'] ?? '' ) ) );
			return self::web_error_redirect_url( (string) ( $result['message'] ?? 'Could not sign you in.' ) );
		}

		self::trace( 'complete_success', array( 'user_id' => (int) $data['user_id'] ) );
		if ( class_exists( 'PAXdesign_Auth_Native' ) && PAXdesign_Auth_Native::is_owner_account( (int) $data['user_id'] ) ) {
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

		return add_query_arg( 'pdx_github_finish', $ticket, PAXdesign_Auth_Page::page_url() );
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
				'pdx_github' => 'error',
				'pdx_msg'    => $message,
			),
			PAXdesign_Auth_Page::page_url()
		);
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
		return 'pax_github_login_' . hash( 'sha256', $ticket );
	}

	/**
	 * @return string
	 */
	private static function create_oauth_state( string $return_url, string $client_id, string $redirect_uri ): string {
		$state = bin2hex( random_bytes( 16 ) );
		$key   = 'pax_github_oauth_' . hash( 'sha256', $state );
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
		$key  = 'pax_github_oauth_' . hash( 'sha256', $state );
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			$stored = get_option( $key, null );
			$data   = is_array( $stored ) ? $stored : null;
		}
		delete_transient( $key );
		delete_option( $key );
		if ( ! is_array( $data ) || empty( $data['return_url'] ) ) {
			return new WP_Error( 'github_state_invalid', 'Your GitHub sign-in session expired. Please try again.' );
		}
		return array(
			'return_url'   => (string) $data['return_url'],
			'client_id'    => (string) ( $data['client_id'] ?? self::client_id() ),
			'redirect_uri' => (string) ( $data['redirect_uri'] ?? self::web_callback_url() ),
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function exchange_authorization_code( string $code, string $client_id, string $redirect_uri ) {
		if ( ! self::is_web_configured() ) {
			return new WP_Error( 'github_web_unconfigured', 'Sign in with GitHub is not configured on the server yet.' );
		}
		if ( $client_id === '' ) {
			$client_id = self::client_id();
		}
		if ( $redirect_uri === '' ) {
			$redirect_uri = self::web_callback_url();
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'paxdesign-booking',
				),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => self::client_secret(),
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'github_token_http', $response->get_error_message() );
		}

		$code_http = (int) wp_remote_retrieve_response_code( $response );
		$body      = (string) wp_remote_retrieve_body( $response );
		$data      = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'github_token_parse', 'GitHub token response was invalid.' );
		}
		if ( $code_http < 200 || $code_http >= 300 || ! empty( $data['error'] ) ) {
			$msg = sanitize_text_field( (string) ( $data['error_description'] ?? $data['error'] ?? 'token_exchange_failed' ) );
			return new WP_Error( 'github_token_rejected', $msg, array( 'http' => $code_http ) );
		}

		return $data;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function fetch_github_profile( string $access_token ) {
		$user = self::github_api_get( self::USER_URL, $access_token );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$github_id = (int) ( $user['id'] ?? 0 );
		$login     = sanitize_user( (string) ( $user['login'] ?? '' ), true );
		if ( $github_id <= 0 ) {
			return new WP_Error( 'github_invalid', 'GitHub account identifier is missing.' );
		}

		$email = sanitize_email( (string) ( $user['email'] ?? '' ) );
		if ( $email === '' ) {
			$email = self::fetch_primary_email( $access_token );
		}

		$name = sanitize_text_field( (string) ( $user['name'] ?? '' ) );
		if ( $name === '' ) {
			$name = $login;
		}

		return array(
			'id'    => $github_id,
			'login' => $login,
			'email' => $email,
			'name'  => $name,
		);
	}

	/**
	 * @return string
	 */
	private static function fetch_primary_email( string $access_token ): string {
		$emails = self::github_api_get( self::EMAILS_URL, $access_token );
		if ( is_wp_error( $emails ) || ! is_array( $emails ) ) {
			return '';
		}

		$fallback = '';
		foreach ( $emails as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$candidate = sanitize_email( (string) ( $row['email'] ?? '' ) );
			if ( $candidate === '' || ! is_email( $candidate ) ) {
				continue;
			}
			if ( ! empty( $row['primary'] ) && ! empty( $row['verified'] ) ) {
				return $candidate;
			}
			if ( $fallback === '' && ! empty( $row['verified'] ) ) {
				$fallback = $candidate;
			}
		}

		return $fallback;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function github_api_get( string $url, string $access_token ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'Authorization'        => 'Bearer ' . $access_token,
					'User-Agent'           => 'paxdesign-booking',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'github_api_http', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'github_api_rejected', 'Could not read the GitHub account profile.' );
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $profile
	 * @return WP_User|WP_Error
	 */
	private static function resolve_user_from_profile( array $profile ) {
		$github_id = (int) ( $profile['id'] ?? 0 );
		$email     = sanitize_email( (string) ( $profile['email'] ?? '' ) );
		$login     = sanitize_user( (string) ( $profile['login'] ?? '' ), true );
		$name      = sanitize_text_field( (string) ( $profile['name'] ?? '' ) );

		if ( $github_id <= 0 ) {
			return new WP_Error( 'github_invalid', 'GitHub account identifier is missing.' );
		}

		$user = self::find_user_by_github_id( $github_id );
		if ( ! $user && $email !== '' ) {
			$by_email = get_user_by( 'email', $email );
			if ( $by_email instanceof WP_User ) {
				update_user_meta( $by_email->ID, self::META_GITHUB_ID, (string) $github_id );
				$user = $by_email;
			}
		}

		if ( ! $user && $email !== '' && class_exists( 'PAXdesign_Customer_Master_Admin' ) && PAXdesign_Customer_Master_Admin::is_master_email( $email ) ) {
			$master = PAXdesign_Customer_Master_Admin::find_master_user();
			if ( $master instanceof WP_User ) {
				update_user_meta( (int) $master->ID, self::META_GITHUB_ID, (string) $github_id );
				$user = $master;
			}
		}

		if ( ! $user ) {
			if ( $email === '' ) {
				$email = self::github_account_email_for_id( $github_id );
			}
			$created = self::create_customer_from_github( $github_id, $email, $name, $login );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$user = $created;
		}

		if ( ! PAXdesign_Customers::is_login_allowed( (int) $user->ID ) ) {
			return new WP_Error( 'suspended', 'Your account has been suspended. Please contact support.' );
		}

		update_user_meta( (int) $user->ID, 'pdx_email_verified', 1 );
		update_user_meta( (int) $user->ID, self::META_GITHUB_ID, (string) $github_id );
		if ( $login !== '' ) {
			update_user_meta( (int) $user->ID, self::META_GITHUB_LOGIN, $login );
		}

		if ( PAXdesign_Customers::STATUS_PENDING === PAXdesign_Customers::account_status( (int) $user->ID ) ) {
			PAXdesign_Customers::set_account_status( (int) $user->ID, PAXdesign_Customers::STATUS_ACTIVE );
		}

		if ( class_exists( 'PAXdesign_Customer_Registry' ) ) {
			PAXdesign_Customer_Registry::ensure_portal_customer( (int) $user->ID );
		}

		return $user;
	}

	/**
	 * @return WP_User|null
	 */
	private static function find_user_by_github_id( int $github_id ) {
		$users = get_users(
			array(
				'meta_key'   => self::META_GITHUB_ID,
				'meta_value' => (string) $github_id,
				'number'     => 1,
				'fields'     => 'all',
			)
		);
		return ! empty( $users[0] ) && $users[0] instanceof WP_User ? $users[0] : null;
	}

	/**
	 * @return WP_User|WP_Error
	 */
	private static function create_customer_from_github( int $github_id, string $email, string $name, string $login ) {
		if ( $name === '' ) {
			$name = $login !== '' ? $login : sanitize_text_field( current( explode( '@', $email ) ) );
		}

		$password = wp_generate_password( 24, true, true );
		$result   = PAXdesign_Auth::register( $email, $password, $name );
		if ( empty( $result['success'] ) ) {
			$message = (string) ( $result['message'] ?? 'Could not create your account.' );
			if ( ! empty( $result['error'] ) && $result['error'] === 'email_exists' ) {
				$existing = get_user_by( 'email', $email );
				if ( $existing instanceof WP_User ) {
					update_user_meta( $existing->ID, self::META_GITHUB_ID, (string) $github_id );
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

		update_user_meta( $user_id, self::META_GITHUB_ID, (string) $github_id );
		update_user_meta( $user_id, 'pdx_email_verified', 1 );

		$created = get_user_by( 'id', $user_id );
		return $created instanceof WP_User ? $created : new WP_Error( 'registration_failed', 'Could not create your account.' );
	}

	/**
	 * @return string
	 */
	private static function github_account_email_for_id( int $github_id ): string {
		return 'github+' . substr( hash( 'sha256', (string) $github_id ), 0, 32 ) . '@id.paxdesign.at';
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
	 * @param array<string, mixed> $context
	 */
	private static function log_failure( string $reason, array $context = array() ): void {
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'github_login_failed', array_merge( array( 'reason' => $reason ), $context ), 'warn' );
		}
	}
}
