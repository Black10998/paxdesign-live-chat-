<?php
/**
 * Continue with GitHub — website and iOS OAuth.
 *
 * GitHub redirects only to the registered HTTPS callback. The client secret
 * stays on the server (WP option / wp-config / env). It is never returned by
 * REST, written to logs, or shipped in the iOS app.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAXdesign_Auth_GitHub {

	const META_GITHUB_ID        = 'pdx_github_id';
	const META_GITHUB_LOGIN     = 'pdx_github_login';
	const OPTION_CLIENT_ID      = 'paxdesign_github_client_id';
	const OPTION_CLIENT_SECRET  = 'paxdesign_github_client_secret';
	const OPTION_LAST_ERROR     = 'paxdesign_github_oauth_last_error';
	const PUBLIC_CLIENT_ID      = 'Ov23lixDUzzbRfCy1a1a';
	const AUTHORIZE_URL         = 'https://github.com/login/oauth/authorize';
	const TOKEN_URL             = 'https://github.com/login/oauth/access_token';
	const USER_URL              = 'https://api.github.com/user';
	const EMAILS_URL            = 'https://api.github.com/user/emails';
	const APP_CALLBACK_SCHEME   = 'paxlivechat';
	const OAUTH_STATE_TTL       = 1800;
	const LOGIN_TICKET_TTL      = 600;
	const IOS_TICKET_TTL        = 180;

	/**
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( __CLASS__, 'maybe_seed_credentials' ), 0 );
		add_action( 'init', array( __CLASS__, 'maybe_finish_web_login' ), 0 );
	}

	/**
	 * Persist Client ID (public) and seed the server-side secret once if empty.
	 *
	 * @return void
	 */
	public static function maybe_seed_credentials(): void {
		$client_id = trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
		if ( $client_id === '' ) {
			add_option( self::OPTION_CLIENT_ID, self::PUBLIC_CLIENT_ID, '', false );
		}

		$existing = trim( (string) get_option( self::OPTION_CLIENT_SECRET, '' ) );
		if ( $existing !== '' ) {
			return;
		}

		$from_env = self::secret_from_environment();
		if ( $from_env !== '' ) {
			add_option( self::OPTION_CLIENT_SECRET, $from_env, '', false );
			return;
		}

		$bootstrap = self::bootstrap_secret();
		if ( $bootstrap !== '' ) {
			add_option( self::OPTION_CLIENT_SECRET, $bootstrap, '', false );
		}
	}

	/**
	 * @return string
	 */
	public static function client_id(): string {
		$id = trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
		return $id !== '' ? $id : self::PUBLIC_CLIENT_ID;
	}

	/**
	 * Server-side only. Never expose via REST or HTML.
	 *
	 * @return string
	 */
	public static function client_secret(): string {
		if ( defined( 'PAX_GITHUB_CLIENT_SECRET' ) && is_string( PAX_GITHUB_CLIENT_SECRET ) && PAX_GITHUB_CLIENT_SECRET !== '' ) {
			return trim( PAX_GITHUB_CLIENT_SECRET );
		}
		$from_env = self::secret_from_environment();
		if ( $from_env !== '' ) {
			return $from_env;
		}
		$option = trim( (string) get_option( self::OPTION_CLIENT_SECRET, '' ) );
		if ( $option !== '' ) {
			return $option;
		}
		return self::bootstrap_secret();
	}

	/**
	 * @return bool
	 */
	public static function is_configured(): bool {
		return self::client_id() !== '' && self::client_secret() !== '';
	}

	/**
	 * Exact Authorization callback URL registered on the GitHub OAuth App.
	 *
	 * @return string
	 */
	public static function callback_url(): string {
		$path = '/' . ltrim( rest_get_url_prefix(), '/' ) . '/pdx/v1/auth/github/callback';
		return untrailingslashit( home_url( $path, 'https' ) );
	}

	/**
	 * @return string
	 */
	public static function start_url(): string {
		return rest_url( 'pdx/v1/auth/github/start' );
	}

	/**
	 * @return string|WP_Error
	 */
	public static function begin_authorization( WP_REST_Request $request ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'github_unconfigured', 'Continue with GitHub is not configured on the server yet.' );
		}

		$platform      = self::normalize_platform( (string) $request->get_param( 'platform' ) );
		$device_label  = sanitize_text_field( (string) $request->get_param( 'device_label' ) );
		if ( $device_label === '' ) {
			$device_label = 'PAXDesign iOS';
		}
		$return_path   = sanitize_text_field( (string) $request->get_param( 'return_to' ) );
		$return_url    = self::sanitize_return_url( $return_path );
		$redirect_uri  = self::callback_url();
		$state         = self::create_oauth_state( $platform, $return_url, $redirect_uri, $device_label );

		$params = array(
			'client_id'     => self::client_id(),
			'redirect_uri'  => $redirect_uri,
			'scope'         => 'read:user user:email',
			'state'         => $state,
			'allow_signup'  => 'true',
		);

		return add_query_arg( $params, self::AUTHORIZE_URL );
	}

	/**
	 * @return string Redirect URL (HTTPS for web, custom scheme for iOS).
	 */
	public static function handle_callback( WP_REST_Request $request ): string {
		$error = sanitize_text_field( (string) $request->get_param( 'error' ) );
		if ( $error !== '' ) {
			$desc = sanitize_text_field( (string) $request->get_param( 'error_description' ) );
			$msg  = $desc !== '' ? $desc : 'GitHub sign-in was cancelled or denied.';
			self::log_failure( 'github_callback_denied', array( 'error' => $error ) );
			return self::error_redirect( $msg, 'web' );
		}

		$code  = trim( (string) $request->get_param( 'code' ) );
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );
		if ( $code === '' || $state === '' ) {
			self::log_failure( 'github_callback_missing_params', array( 'has_code' => $code !== '', 'has_state' => $state !== '' ) );
			return self::error_redirect( 'GitHub did not return a valid authorization response.', 'web' );
		}

		$state_data = self::consume_oauth_state( $state );
		if ( is_wp_error( $state_data ) ) {
			self::log_failure( 'github_state_invalid' );
			return self::error_redirect( $state_data->get_error_message(), 'web' );
		}

		$platform     = (string) ( $state_data['platform'] ?? 'web' );
		$return_url   = (string) ( $state_data['return_url'] ?? '' );
		$redirect_uri = (string) ( $state_data['redirect_uri'] ?? self::callback_url() );
		$device_label = (string) ( $state_data['device_label'] ?? 'PAXDesign iOS' );

		$tokens = self::exchange_authorization_code( $code, $redirect_uri );
		if ( is_wp_error( $tokens ) ) {
			self::log_failure( 'github_token_exchange', array( 'reason' => $tokens->get_error_code() ) );
			return self::error_redirect( $tokens->get_error_message(), $platform );
		}

		$access_token = (string) ( $tokens['access_token'] ?? '' );
		if ( $access_token === '' ) {
			self::log_failure( 'github_missing_access_token' );
			return self::error_redirect( 'GitHub did not return an access token.', $platform );
		}

		$profile = self::fetch_github_profile( $access_token );
		if ( is_wp_error( $profile ) ) {
			self::log_failure( 'github_profile', array( 'reason' => $profile->get_error_code() ) );
			return self::error_redirect( $profile->get_error_message(), $platform );
		}

		$user = self::resolve_user_from_profile( $profile );
		if ( is_wp_error( $user ) ) {
			self::log_failure( 'github_user_resolve', array( 'reason' => $user->get_error_code() ) );
			return self::error_redirect( $user->get_error_message(), $platform );
		}

		if ( $platform === 'ios' ) {
			$session = PAXdesign_Auth_Native::mobile_login_for_user( (int) $user->ID, $device_label, 'github' );
			if ( empty( $session['success'] ) ) {
				self::log_failure( 'github_ios_session', array( 'error' => (string) ( $session['error'] ?? '' ) ) );
				return self::error_redirect( (string) ( $session['message'] ?? 'Could not start a PAXDesign session.' ), 'ios' );
			}
			$ticket = self::store_ios_ticket( $session );
			return self::APP_CALLBACK_SCHEME . '://auth/github?ticket=' . rawurlencode( $ticket );
		}

		return self::web_complete_url_for_user( (int) $user->ID, $return_url );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function complete_ios_ticket( string $ticket ): array {
		$ticket = trim( $ticket );
		if ( $ticket === '' ) {
			return array(
				'success' => false,
				'error'   => 'github_invalid',
				'message' => 'GitHub sign-in session is invalid. Please try again.',
			);
		}

		$data = self::consume_ios_ticket( $ticket );
		if ( ! is_array( $data ) || empty( $data['success'] ) ) {
			self::log_failure( 'github_ios_ticket_invalid' );
			return array(
				'success' => false,
				'error'   => 'github_invalid',
				'message' => 'GitHub sign-in session expired. Please try again.',
			);
		}

		return $data;
	}

	/**
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

		nocache_headers();
		$redirect = self::complete_web_login_from_ticket( $ticket );
		wp_safe_redirect( self::safe_redirect_url( $redirect ) );
		exit;
	}

	/**
	 * @return string
	 */
	public static function complete_web_login( WP_REST_Request $request ): string {
		return self::complete_web_login_from_ticket( sanitize_text_field( (string) $request->get_param( 'ticket' ) ) );
	}

	/**
	 * @return bool
	 */
	public static function is_app_redirect( string $url ): bool {
		return strpos( $url, self::APP_CALLBACK_SCHEME . '://' ) === 0;
	}

	/**
	 * Custom-scheme redirects are rejected by wp_safe_redirect().
	 *
	 * @return void
	 */
	public static function redirect_app( string $url ): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Location: ' . $url, true, 302 );
		exit;
	}

	/**
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
	public static function error_redirect( string $message, string $platform = 'web' ): string {
		update_option(
			self::OPTION_LAST_ERROR,
			array(
				't'       => gmdate( 'c' ),
				'message' => $message,
			),
			false
		);

		if ( $platform === 'ios' ) {
			return self::APP_CALLBACK_SCHEME . '://auth/github?error=' . rawurlencode( $message );
		}

		return add_query_arg(
			array(
				'pdx_github' => 'error',
				'pdx_msg'    => $message,
			),
			PAXdesign_Auth_Page::page_url()
		);
	}

	/**
	 * @return string
	 */
	private static function complete_web_login_from_ticket( string $ticket ): string {
		if ( $ticket === '' ) {
			return self::error_redirect( 'GitHub sign-in session is invalid. Please try again.' );
		}

		$data = self::consume_login_ticket( $ticket );
		if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
			self::log_failure( 'github_complete_ticket_invalid' );
			return self::error_redirect( 'GitHub sign-in session expired. Please try again.' );
		}

		$result = PAXdesign_Auth_Native::web_login_for_user( (int) $data['user_id'] );
		if ( empty( $result['success'] ) ) {
			self::log_failure( 'github_web_session' );
			return self::error_redirect( (string) ( $result['message'] ?? 'Could not sign you in.' ) );
		}

		return (string) ( $data['return_url'] ?? ( PAXdesign_Auth_Page::page_url() . '#/overview' ) );
	}

	/**
	 * @return string
	 */
	private static function web_complete_url_for_user( int $user_id, string $return_url ): string {
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
	 * @param array<string, mixed> $session
	 */
	private static function store_ios_ticket( array $session ): string {
		$ticket = bin2hex( random_bytes( 16 ) );
		$key    = self::ios_ticket_key( $ticket );
		set_transient( $key, $session, self::IOS_TICKET_TTL );
		update_option( $key, $session, false );
		return $ticket;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function consume_ios_ticket( string $ticket ) {
		$key  = self::ios_ticket_key( $ticket );
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
	private static function ios_ticket_key( string $ticket ): string {
		return 'pax_github_ios_' . hash( 'sha256', $ticket );
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
	private static function create_oauth_state( string $platform, string $return_url, string $redirect_uri, string $device_label ): string {
		$state = bin2hex( random_bytes( 16 ) );
		$key   = 'pax_github_oauth_' . hash( 'sha256', $state );
		$data  = array(
			'platform'     => $platform,
			'return_url'   => $return_url,
			'redirect_uri' => $redirect_uri,
			'device_label' => $device_label,
			'created'      => time(),
		);
		set_transient( $key, $data, self::OAUTH_STATE_TTL );
		update_option( $key, $data, false );
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
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'github_state_invalid', 'Your GitHub sign-in session expired. Please try again.' );
		}
		return array(
			'platform'     => self::normalize_platform( (string) ( $data['platform'] ?? 'web' ) ),
			'return_url'   => (string) ( $data['return_url'] ?? '' ),
			'redirect_uri' => (string) ( $data['redirect_uri'] ?? self::callback_url() ),
			'device_label' => (string) ( $data['device_label'] ?? 'PAXDesign iOS' ),
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function exchange_authorization_code( string $code, string $redirect_uri ) {
		$secret = self::client_secret();
		if ( $secret === '' ) {
			return new WP_Error( 'github_unconfigured', 'Continue with GitHub is not configured on the server yet.' );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'PAXDesign-GitHubOAuth/' . ( defined( 'PAXDESIGN_BOOKING_VERSION' ) ? PAXDESIGN_BOOKING_VERSION : '1.0' ),
				),
				'body'    => array(
					'client_id'     => self::client_id(),
					'client_secret' => $secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'github_token_exchange', 'Could not contact GitHub to complete sign-in.' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$message = is_array( $body ) ? (string) ( $body['error_description'] ?? $body['error'] ?? '' ) : '';
			if ( $message === '' ) {
				$message = 'GitHub token exchange failed.';
			}
			return new WP_Error( 'github_token_exchange', $message );
		}

		return $body;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function fetch_github_profile( string $access_token ) {
		$user = self::github_api_get( self::USER_URL, $access_token );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$github_id = absint( $user['id'] ?? 0 );
		$login     = sanitize_user( (string) ( $user['login'] ?? '' ), true );
		if ( $github_id <= 0 || $login === '' ) {
			return new WP_Error( 'github_profile', 'GitHub account identifier is missing.' );
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
			if ( $candidate === '' || strpos( $candidate, 'users.noreply.github.com' ) !== false ) {
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
					'User-Agent'           => 'PAXDesign-GitHubOAuth/' . ( defined( 'PAXDESIGN_BOOKING_VERSION' ) ? PAXDESIGN_BOOKING_VERSION : '1.0' ),
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'github_profile', 'Could not load your GitHub profile.' );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'github_profile', 'Could not load your GitHub profile.' );
		}
		return $body;
	}

	/**
	 * @param array<string, mixed> $profile
	 * @return WP_User|WP_Error
	 */
	private static function resolve_user_from_profile( array $profile ) {
		$github_id = absint( $profile['id'] ?? 0 );
		$login     = sanitize_user( (string) ( $profile['login'] ?? '' ), true );
		$email     = sanitize_email( (string) ( $profile['email'] ?? '' ) );
		$name      = sanitize_text_field( (string) ( $profile['name'] ?? '' ) );

		if ( $github_id <= 0 ) {
			return new WP_Error( 'github_invalid', 'GitHub account identifier is missing.' );
		}

		$user = self::find_user_by_github_id( $github_id );
		if ( ! $user && $email !== '' ) {
			$by_email = get_user_by( 'email', $email );
			if ( $by_email instanceof WP_User ) {
				update_user_meta( $by_email->ID, self::META_GITHUB_ID, (string) $github_id );
				update_user_meta( $by_email->ID, self::META_GITHUB_LOGIN, $login );
				$user = $by_email;
			}
		}

		if ( ! $user ) {
			if ( $email === '' ) {
				$email = self::github_account_email_for_id( $github_id );
			}
			$created = self::create_customer_from_github( $github_id, $login, $email, $name );
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
	private static function create_customer_from_github( int $github_id, string $login, string $email, string $name ) {
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
					update_user_meta( $existing->ID, self::META_GITHUB_LOGIN, $login );
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
		update_user_meta( $user_id, self::META_GITHUB_LOGIN, $login );
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

		if ( isset( $return_path[0] ) && $return_path[0] === '/' ) {
			return esc_url_raw( home_url( $return_path ) );
		}

		return esc_url_raw( $default );
	}

	/**
	 * @return string
	 */
	private static function normalize_platform( string $platform ): string {
		$platform = strtolower( trim( $platform ) );
		return $platform === 'ios' ? 'ios' : 'web';
	}

	/**
	 * @return string
	 */
	private static function secret_from_environment(): string {
		$env = getenv( 'PAX_GITHUB_CLIENT_SECRET' );
		return is_string( $env ) ? trim( $env ) : '';
	}

	/**
	 * One-time server bootstrap so production login works after plugin deploy.
	 * Overridable via WP option, PAX_GITHUB_CLIENT_SECRET, or wp-config.
	 * Never returned by REST or printed in admin HTML after the first save.
	 *
	 * @return string
	 */
	private static function bootstrap_secret(): string {
		return '23161838e68470e36f9f6f38bf309d239fb988e5';
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function log_failure( string $reason, array $context = array() ): void {
		unset( $context['client_secret'], $context['access_token'], $context['code'] );
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'github_login_failed', array_merge( array( 'reason' => $reason ), $context ), 'warn' );
		}
	}
}
