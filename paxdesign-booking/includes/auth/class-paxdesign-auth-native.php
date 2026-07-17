<?php
/**
 * PDX_Auth — WordPress-integrated authentication, email verification, and session security.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAXdesign_Auth_Native {

	const META_VERIFIED       = 'pdx_email_verified';
	const META_VERIFY_TOKEN   = 'pdx_verify_token';
	const META_VERIFY_CODE    = 'pdx_verify_code';
	const META_VERIFY_EXPIRES = 'pdx_verify_expires';
	const META_RESET_TOKEN    = 'pdx_reset_token';
	const META_RESET_EXPIRES  = 'pdx_reset_expires';
	const META_FAILED_LOGINS  = 'pdx_failed_logins';
	const META_LOCKED_UNTIL   = 'pdx_locked_until';

	const VERIFY_TTL_HOURS = 24;
	const RESET_TTL_HOURS  = 1;

	/** WordPress role slug for PaxDesign customer accounts (no dashboard access). */
	const CUSTOMER_ROLE = 'pdx_customer';

	/** Roles that must never be downgraded to customer. */
	private const PRESERVED_ROLES = [ 'administrator' ];

	/** Modules accessible without login (free tier). */
	public static function public_modules(): array {
		return apply_filters( 'pdx_public_modules', [ 'trust', 'create', 'workspace' ] );
	}

	public static function register_hooks(): void {
		add_action( 'init', [ self::class, 'ensure_customer_role' ], 5 );
		add_action( 'init', [ self::class, 'bootstrap_mobile_auth_basic' ], 1 );
		add_filter( 'determine_current_user', [ self::class, 'map_basic_auth_email_to_login' ], 19 );
		add_action( 'init', [ self::class, 'handle_email_verify_link' ] );
		add_filter( 'authenticate', [ self::class, 'block_unverified_login' ], 30, 3 );
		add_action( 'wp_ajax_pdx_rest_nonce', [ self::class, 'ajax_rest_nonce' ] );
		add_action( 'wp_ajax_nopriv_pdx_rest_nonce', [ self::class, 'ajax_rest_nonce' ] );
		add_filter( 'show_admin_bar', [ self::class, 'hide_admin_bar_for_customers' ] );
		add_action( 'admin_init', [ self::class, 'block_wp_admin_for_customers' ], 1 );
		add_action( 'login_init', [ self::class, 'redirect_logged_in_customers_from_wp_login' ] );
		add_filter( 'login_redirect', [ self::class, 'customer_login_redirect' ], 10, 3 );
		add_action( 'wp_head', [ self::class, 'admin_bar_hide_css_fallback' ], 100 );
	}

	/** Whether the user is a real WordPress site administrator. */
	public static function is_site_admin( ?int $user_id = null ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		return user_can( $user_id, manage_options );
	}

	/** Role slug used for newly registered PaxDesign customers. */
	public static function customer_role(): string {
		if ( class_exists( 'WooCommerce' ) && get_role( 'customer' ) ) {
			return 'customer';
		}
		return self::CUSTOMER_ROLE;
	}

	public static function ensure_customer_role(): void {
		if ( get_role( self::CUSTOMER_ROLE ) ) {
			return;
		}
		add_role(
			self::CUSTOMER_ROLE,
			__( 'PaxDesign Customer', 'paxdesign-booking' ),
			[ 'read' => true ]
		);
	}

	/** @param bool $show Default admin-bar visibility. */
	public static function hide_admin_bar_for_customers( $show ): bool {
		if ( self::is_site_admin() ) {
			return (bool) $show;
		}
		if ( is_user_logged_in() ) {
			return false;
		}
		return (bool) $show;
	}

	public static function admin_bar_hide_css_fallback(): void {
		if ( ! is_user_logged_in() || self::is_site_admin() ) {
			return;
		}
		echo "<style id=\"pdx-hide-wp-admin-bar\">#wpadminbar{display:none!important}html{margin-top:0!important}body.admin-bar{margin-top:0!important}@media screen and (max-width:782px){html{margin-top:0!important}body.admin-bar{margin-top:0!important}}</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
	}

	public static function block_wp_admin_for_customers(): void {
		if ( self::is_site_admin() || ! is_user_logged_in() ) {
			return;
		}
		if ( wp_doing_ajax() ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'pdx_account', '1', home_url( '/' ) ) );
		exit;
	}

	public static function redirect_logged_in_customers_from_wp_login(): void {
		if ( ! is_user_logged_in() || self::is_site_admin() ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'pdx_account', '1', home_url( '/' ) ) );
		exit;
	}

	/**
	 * @param string           $redirect_to Default redirect URL.
	 * @param string           $requested   Requested redirect URL.
	 * @param WP_User|WP_Error $user        Authenticated user.
	 */
	public static function customer_login_redirect( $redirect_to, $requested, $user ): string {
		unset( $requested );
		if ( $user instanceof WP_User && ! user_can( $user, manage_options ) ) {
			return add_query_arg( 'pdx_account', '1', home_url( '/' ) );
		}
		return (string) $redirect_to;
	}

	private static function assign_customer_role( int $user_id ): void {
		if ( self::is_site_admin( $user_id ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$roles = (array) $user->roles;
		if ( array_intersect( self::PRESERVED_ROLES, $roles ) ) {
			return;
		}
		$user->set_role( self::customer_role() );
		update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
	}

	/** Fresh wp_rest nonce for the current cookie session (no REST nonce required). */
	public static function ajax_rest_nonce(): void {
		nocache_headers();
		wp_send_json_success( self::session_payload() );
	}

	/** @return array{nonce:string,user:array} */
	public static function session_payload(): array {
		return [
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'user'  => self::user_payload(),
		];
	}

	public static function is_email_verified( int $user_id ): bool {
		if ( ! $user_id ) {
			return false;
		}
		if ( user_can( $user_id, manage_options ) ) {
			return true;
		}
		return (bool) get_user_meta( $user_id, self::META_VERIFIED, true );
	}

	public static function user_payload( ?int $user_id = null ): array {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return [
				'logged_in'    => false,
				'verified'     => false,
				'display_name' => '',
				'email'        => '',
			];
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [ 'logged_in' => false, 'verified' => false ];
		}
		return [
			'logged_in'    => true,
			'id'           => $user_id,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'verified'     => self::is_email_verified( $user_id ),
			'is_admin'     => user_can( $user_id, manage_options ),
		];
	}

	public static function module_requires_auth( string $module_id ): bool {
		return ! in_array( $module_id, self::public_modules(), true );
	}

	public static function can_access_module( string $module_id ): bool {
		if ( ! self::module_requires_auth( $module_id ) ) {
			return true;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return self::is_email_verified( get_current_user_id() );
	}

	/**
	 * @return array{success:bool,message?:string,user?:array,error?:string,code?:string}
	 */
	public static function register( string $email, string $password, string $name ): array {
		$email = sanitize_email( $email );
		$name  = sanitize_text_field( $name );

		if ( ! is_email( $email ) ) {
			return [ 'success' => false, 'error' => 'invalid_email', 'message' => 'Please enter a valid email address.' ];
		}
		if ( strlen( $password ) < 8 ) {
			return [ 'success' => false, 'error' => 'weak_password', 'message' => 'Password must be at least 8 characters.' ];
		}
		if ( '' === $name ) {
			return [ 'success' => false, 'error' => 'missing_name', 'message' => 'Please enter your name.' ];
		}
		if ( email_exists( $email ) ) {
			return [ 'success' => false, 'error' => 'email_exists', 'message' => 'An account with this email already exists.' ];
		}

		$username = self::generate_username( $email );
		$user_id  = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return [ 'success' => false, 'error' => 'register_failed', 'message' => $user_id->get_error_message() ];
		}

		wp_update_user( [
			'ID'           => $user_id,
			'display_name' => $name,
			'first_name'   => $name,
		] );

		self::assign_customer_role( $user_id );

		update_user_meta( $user_id, PAXdesign_Customers::META_ACCOUNT_STATUS, PAXdesign_Customers::STATUS_PENDING );
		update_user_meta( $user_id, self::META_VERIFIED, 0 );
		self::send_verification_email( $user_id );

		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'user_registered', [ 'user_id' => $user_id, 'email' => $email ] ); }
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'registration_success', [ 'user_id' => $user_id ] );
		}

		return [
			'success' => true,
			'message' => 'Account created. Please check your email to verify your address.',
			'user'    => self::user_payload( $user_id ),
		];
	}

	/**
	 * @return array{success:bool,message?:string,user?:array,error?:string,code?:string}
	 */
	public static function login( string $email, string $password, bool $remember = true ): array {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return [ 'success' => false, 'error' => 'invalid_email', 'message' => 'Please enter a valid email address.' ];
		}

		$lock = self::check_brute_force( $email );
		if ( ! $lock['allowed'] ) {
			return [
				'success'     => false,
				'error'       => 'locked',
				'message'     => 'Too many failed attempts. Try again in ' . $lock['retry_after'] . ' seconds.',
				'retry_after' => $lock['retry_after'],
			];
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			self::record_failed_login( $email );
			return [ 'success' => false, 'error' => 'invalid_credentials', 'message' => 'Invalid email or password.' ];
		}

		if ( get_user_meta( $user->ID, self::META_LOCKED_UNTIL, true ) > time() ) {
			$remaining = (int) get_user_meta( $user->ID, self::META_LOCKED_UNTIL, true ) - time();
			return [
				'success'     => false,
				'error'       => 'locked',
				'message'     => 'Account temporarily locked. Try again in ' . max( 1, $remaining ) . ' seconds.',
				'retry_after' => max( 1, $remaining ),
			];
		}

		if ( ! PAXdesign_Customers::is_login_allowed( $user->ID ) ) {
			return [
				'success' => false,
				'error'   => 'suspended',
				'message' => 'Your account has been suspended. Please contact support.',
			];
		}

		$signed = wp_signon( [
			'user_login'    => $user->user_login,
			'user_password' => $password,
			'remember'      => $remember,
		], is_ssl() );

		if ( is_wp_error( $signed ) ) {
			self::record_failed_login( $email, $user->ID );
			return [ 'success' => false, 'error' => 'invalid_credentials', 'message' => 'Invalid email or password.' ];
		}

		self::clear_failed_logins( $email, $user->ID );
		wp_set_current_user( $signed->ID );
		wp_set_auth_cookie( $signed->ID, $remember, is_ssl() );
		self::assign_customer_role( $signed->ID );
		PAXdesign_Customers::record_login( $signed->ID );

		do_action( 'pdx_user_logged_in', $signed->ID );

		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'user_login', [ 'user_id' => $signed->ID ] ); }
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'web_login_success', [ 'user_id' => $signed->ID ] );
		}

		return array_merge(
			[
				'success' => true,
				'message' => 'Logged in successfully.',
				'user'    => self::user_payload( $signed->ID ),
			],
			self::session_payload()
		);
	}

	public static function logout(): array {
		$user_id = get_current_user_id();
		wp_logout();
		if ( $user_id ) {
			if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'user_logout', [ 'user_id' => $user_id ] ); }
		}
		return array_merge(
			[ 'success' => true, 'message' => 'Logged out.' ],
			self::session_payload()
		);
	}

	/**
	 * @return array{success:bool,message?:string,error?:string}
	 */
	public static function forgot_password( string $email ): array {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return [ 'success' => true, 'message' => 'If that email exists, a reset link has been sent.' ];
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return [ 'success' => true, 'message' => 'If that email exists, a reset link has been sent.' ];
		}

		$token   = bin2hex( random_bytes( 32 ) );
		$expires = time() + ( self::RESET_TTL_HOURS * HOUR_IN_SECONDS );

		update_user_meta( $user->ID, self::META_RESET_TOKEN, hash( 'sha256', $token ) );
		update_user_meta( $user->ID, self::META_RESET_EXPIRES, $expires );

		$link = add_query_arg( [
			'pdx_reset' => '1',
			'token'     => $token,
			'uid'       => $user->ID,
		], home_url( '/' ) );

		$subject = sprintf( '[%s] Reset your password', get_bloginfo( 'name' ) );
		$body    = self::email_template(
			'Reset your password',
			'Click the button below to set a new password. This link expires in ' . self::RESET_TTL_HOURS . ' hour.',
			$link,
			'Reset Password'
		);

		wp_mail( $user->user_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'password_reset_requested', [ 'user_id' => $user->ID ] ); }

		return [ 'success' => true, 'message' => 'If that email exists, a reset link has been sent.' ];
	}

	/**
	 * @return array{success:bool,message?:string,error?:string}
	 */
	public static function reset_password( string $token, int $user_id, string $password ): array {
		if ( strlen( $password ) < 8 ) {
			return [ 'success' => false, 'error' => 'weak_password', 'message' => 'Password must be at least 8 characters.' ];
		}

		$stored  = (string) get_user_meta( $user_id, self::META_RESET_TOKEN, true );
		$expires = (int) get_user_meta( $user_id, self::META_RESET_EXPIRES, true );

		if ( ! $stored || ! $expires || $expires < time() ) {
			return [ 'success' => false, 'error' => 'expired', 'message' => 'Reset link has expired. Request a new one.' ];
		}
		if ( ! hash_equals( $stored, hash( 'sha256', $token ) ) ) {
			return [ 'success' => false, 'error' => 'invalid_token', 'message' => 'Invalid reset link.' ];
		}

		wp_set_password( $password, $user_id );
		delete_user_meta( $user_id, self::META_RESET_TOKEN );
		delete_user_meta( $user_id, self::META_RESET_EXPIRES );

		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'password_reset', [ 'user_id' => $user_id ] ); }

		return [ 'success' => true, 'message' => 'Password updated. You can now log in.' ];
	}

	/**
	 * @return array{success:bool,message?:string,error?:string}
	 */
	public static function resend_verification( ?int $user_id = null ): array {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id ) {
			return [ 'success' => false, 'error' => 'not_logged_in', 'message' => 'You must be logged in.' ];
		}
		if ( self::is_email_verified( $user_id ) ) {
			return [ 'success' => true, 'message' => 'Your email is already verified.' ];
		}

		self::send_verification_email( $user_id );
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'verification_resent', [ 'user_id' => $user_id ] );
		}
		return [
			'success'          => true,
			'message'          => 'Verification email sent.',
			'expires_in_hours' => self::VERIFY_TTL_HOURS,
		];
	}

	/**
	 * Public resend by email (mobile registration flow).
	 *
	 * @return array{success:bool,message?:string,error?:string,expires_in_hours?:int}
	 */
	public static function resend_verification_by_email( string $email ): array {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return [
				'success' => true,
				'message' => 'If an account exists, a verification email was sent.',
			];
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return [
				'success' => true,
				'message' => 'If an account exists, a verification email was sent.',
			];
		}
		if ( self::is_email_verified( (int) $user->ID ) ) {
			return [ 'success' => true, 'message' => 'Your email is already verified.' ];
		}

		self::send_verification_email( (int) $user->ID );
		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'verification_resent', [ 'user_id' => (int) $user->ID ] ); }
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'verification_resent', [ 'user_id' => (int) $user->ID, 'via' => 'email' ] );
		}

		return [
			'success'          => true,
			'message'          => 'Verification email sent.',
			'expires_in_hours' => self::VERIFY_TTL_HOURS,
		];
	}

	public static function verify_email( int $user_id, string $token = '', string $code = '' ): array {
		if ( $code !== '' ) {
			return self::verify_email_by_code( $user_id, $code );
		}

		$stored  = (string) get_user_meta( $user_id, self::META_VERIFY_TOKEN, true );
		$expires = (int) get_user_meta( $user_id, self::META_VERIFY_EXPIRES, true );

		if ( ! $stored || ! $expires || $expires < time() ) {
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'verification_failed', [ 'user_id' => $user_id, 'reason' => 'expired' ], 'warn' );
			}
			return [ 'success' => false, 'error' => 'expired', 'message' => 'Verification link has expired. Request a new code.' ];
		}
		if ( ! hash_equals( $stored, hash( 'sha256', $token ) ) ) {
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'verification_failed', [ 'user_id' => $user_id, 'reason' => 'invalid_token' ], 'warn' );
			}
			return [ 'success' => false, 'error' => 'invalid_token', 'message' => 'Invalid verification link or code.' ];
		}

		return self::mark_email_verified( $user_id );
	}

	/**
	 * Verify by email + short code (mobile manual entry).
	 *
	 * @return array{success:bool,message?:string,error?:string}
	 */
	public static function verify_by_email_and_code( string $email, string $code ): array {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return [ 'success' => false, 'error' => 'invalid_email', 'message' => 'Please enter a valid email address.' ];
		}
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return [ 'success' => false, 'error' => 'invalid_code', 'message' => 'Invalid verification code.' ];
		}
		return self::verify_email_by_code( (int) $user->ID, $code );
	}

	/**
	 * Mobile sign-in: validate account password, mint Application Password server-side.
	 *
	 * @return array<string, mixed>
	 */
	public static function mobile_login( string $login, string $password, string $device_label = '' ): array {
		$login = trim( $login );
		if ( $login === '' || $password === '' ) {
			return [ 'success' => false, 'error' => 'missing_credentials', 'message' => 'Please enter your email and password.' ];
		}

		$user = self::resolve_user_by_login_or_email( $login );
		if ( ! $user ) {
			self::record_failed_login( $login );
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'mobile_login_failed', [ 'reason' => 'invalid_credentials' ], 'warn' );
			}
			return [ 'success' => false, 'error' => 'invalid_credentials', 'message' => 'Invalid email or password.' ];
		}

		$lock = self::check_brute_force( $user->user_email );
		if ( ! $lock['allowed'] ) {
			return [
				'success'     => false,
				'error'       => 'locked',
				'message'     => 'Too many failed attempts. Try again in ' . $lock['retry_after'] . ' seconds.',
				'retry_after' => $lock['retry_after'],
			];
		}

		if ( get_user_meta( $user->ID, self::META_LOCKED_UNTIL, true ) > time() ) {
			$remaining = (int) get_user_meta( $user->ID, self::META_LOCKED_UNTIL, true ) - time();
			return [
				'success'     => false,
				'error'       => 'locked',
				'message'     => 'Account temporarily locked. Try again in ' . max( 1, $remaining ) . ' seconds.',
				'retry_after' => max( 1, $remaining ),
			];
		}

		if ( ! PAXdesign_Customers::is_login_allowed( $user->ID ) ) {
			return [
				'success' => false,
				'error'   => 'suspended',
				'message' => 'Your account has been suspended. Please contact support.',
			];
		}

		$signed = wp_authenticate( $user->user_login, $password );
		if ( is_wp_error( $signed ) ) {
			self::record_failed_login( $user->user_email, (int) $user->ID );
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'mobile_login_failed', [ 'user_id' => (int) $user->ID, 'reason' => 'invalid_credentials' ], 'warn' );
			}
			return [ 'success' => false, 'error' => 'invalid_credentials', 'message' => 'Invalid email or password.' ];
		}

		$session_mode = self::resolve_mobile_session_mode( (int) $signed->ID );
		if ( $session_mode === 'customer' && ! self::is_email_verified( (int) $signed->ID ) ) {
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'mobile_login_failed', [ 'user_id' => (int) $signed->ID, 'reason' => 'email_unverified' ], 'warn' );
			}
			return [
				'success' => false,
				'error'   => 'email_unverified',
				'message' => 'Please verify your email before signing in.',
			];
		}

		self::clear_failed_logins( $user->user_email, (int) $signed->ID );

		$app = self::create_mobile_application_password( (int) $signed->ID, $device_label );
		if ( is_wp_error( $app ) ) {
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'mobile_login_failed', [ 'user_id' => (int) $signed->ID, 'reason' => 'app_password_failed' ], 'error' );
			}
			return [
				'success' => false,
				'error'   => 'session_failed',
				'message' => 'Could not start a secure session. Please try again.',
			];
		}

		if ( $session_mode === 'customer' ) {
			PAXdesign_Customers::record_login( (int) $signed->ID );
		}

		$role = self::resolve_portal_role( (int) $signed->ID );
		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'mobile_login', [ 'user_id' => (int) $signed->ID, 'session_mode' => $session_mode, 'role' => $role ] ); }
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event(
				'mobile_login_success',
				[ 'user_id' => (int) $signed->ID, 'session_mode' => $session_mode, 'role' => $role ]
			);
		}

		return [
			'success'           => true,
			'message'           => 'Signed in successfully.',
			'session_mode'      => $session_mode,
			'username'          => $signed->user_login,
			'app_password'      => $app['password'],
			'app_password_uuid' => $app['uuid'],
			'user'              => self::user_payload( (int) $signed->ID ),
			'role'              => $role,
		];
	}

	/**
	 * Revoke a mobile Application Password (logout).
	 *
	 * @return array{success:bool,message?:string}
	 */
	public static function mobile_logout( int $user_id, string $uuid ): array {
		if ( $user_id <= 0 || $uuid === '' ) {
			return [ 'success' => false, 'message' => 'Invalid session.' ];
		}
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
		}
		$deleted = WP_Application_Passwords::delete_application_password( $user_id, $uuid );
		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'mobile_logout', [ 'user_id' => $user_id, 'revoked' => (bool) $deleted ] ); }
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'mobile_logout', [ 'user_id' => $user_id, 'revoked' => (bool) $deleted ] );
		}
		return [ 'success' => true, 'message' => 'Logged out.' ];
	}

	/** @return 'staff'|'customer' */
	public static function resolve_mobile_session_mode( int $user_id ): string {
		if ( class_exists( 'PAXdesign_Live_Chat_Permissions' ) && PAXdesign_Live_Chat_Permissions::has_live_chat_access( $user_id ) ) {
			return 'staff';
		}
		return 'customer';
	}

	public static function resolve_portal_role( int $user_id ): string {
		if ( class_exists( 'PAXdesign_Customer_Auth' ) ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user instanceof WP_User ) {
				return PAXdesign_Customer_Auth::resolve_portal_role( $user );
			}
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return 'administrator';
		}
		return 'customer';
	}

	public static function bootstrap_mobile_auth_basic(): void {
		if ( ! self::is_mobile_auth_rest_request() ) {
			return;
		}
		if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			return;
		}
		$header = '';
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = (string) $_SERVER['HTTP_AUTHORIZATION'];
		} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
		}
		if ( $header === '' || stripos( $header, 'basic ' ) !== 0 ) {
			return;
		}
		$decoded = base64_decode( substr( $header, 6 ), true );
		if ( $decoded === false || strpos( $decoded, ':' ) === false ) {
			return;
		}
		list( $user, $pass ) = explode( ':', $decoded, 2 );
		$_SERVER['PHP_AUTH_USER'] = $user;
		$_SERVER['PHP_AUTH_PW']   = $pass;
	}

	/**
	 * Map email → user_login for Application Password Basic Auth.
	 *
	 * @param int|false $user_id
	 * @return int|false
	 */
	public static function map_basic_auth_email_to_login( $user_id ) {
		if ( $user_id || ! self::is_mobile_auth_rest_request() ) {
			return $user_id;
		}
		if ( empty( $_SERVER['PHP_AUTH_USER'] ) ) {
			return $user_id;
		}
		$login = sanitize_text_field( wp_unslash( (string) $_SERVER['PHP_AUTH_USER'] ) );
		if ( $login === '' || ! is_email( $login ) ) {
			return $user_id;
		}
		$by_email = get_user_by( 'email', $login );
		if ( $by_email instanceof WP_User ) {
			$_SERVER['PHP_AUTH_USER'] = $by_email->user_login;
		}
		return $user_id;
	}

	private static function is_mobile_auth_rest_request(): bool {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( $uri !== '' && strpos( $uri, '/wp-json/pdx/v1/auth/mobile-logout' ) !== false ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$route = '';
			if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
				$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
			}
			return $route === '/pdx/v1/auth/mobile-logout';
		}
		return false;
	}

	/**
	 * @return WP_User|null
	 */
	private static function resolve_user_by_login_or_email( string $login ) {
		$login = sanitize_text_field( $login );
		if ( $login === '' ) {
			return null;
		}
		if ( is_email( $login ) ) {
			$user = get_user_by( 'email', sanitize_email( $login ) );
			return $user instanceof WP_User ? $user : null;
		}
		$user = get_user_by( 'login', $login );
		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * @return array{password:string,uuid:string}|WP_Error
	 */
	private static function create_mobile_application_password( int $user_id, string $device_label = '' ) {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';
		}
		$label = $device_label !== '' ? sanitize_text_field( $device_label ) : 'PAXDesign iOS';
		$created = WP_Application_Passwords::create_new_application_password(
			$user_id,
			[ 'name' => $label ]
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		list( $password, $item ) = $created;
		return [
			'password' => (string) $password,
			'uuid'     => isset( $item['uuid'] ) ? (string) $item['uuid'] : '',
		];
	}

	/**
	 * @return array{success:bool,message?:string,error?:string}
	 */
	private static function verify_email_by_code( int $user_id, string $code ): array {
		$code = preg_replace( '/\D/', '', $code );
		if ( strlen( $code ) !== 6 ) {
			return [ 'success' => false, 'error' => 'invalid_code', 'message' => 'Enter the 6-digit verification code from your email.' ];
		}

		$stored  = (string) get_user_meta( $user_id, self::META_VERIFY_CODE, true );
		$expires = (int) get_user_meta( $user_id, self::META_VERIFY_EXPIRES, true );

		if ( ! $stored || ! $expires || $expires < time() ) {
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'verification_failed', [ 'user_id' => $user_id, 'reason' => 'expired' ], 'warn' );
			}
			return [ 'success' => false, 'error' => 'expired', 'message' => 'Verification code has expired. Request a new one.' ];
		}
		if ( ! hash_equals( $stored, hash( 'sha256', $code ) ) ) {
			if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
				PAXdesign_Auth_Log::event( 'verification_failed', [ 'user_id' => $user_id, 'reason' => 'invalid_code' ], 'warn' );
			}
			return [ 'success' => false, 'error' => 'invalid_code', 'message' => 'Invalid verification code.' ];
		}

		return self::mark_email_verified( $user_id );
	}

	/**
	 * @return array{success:bool,message?:string}
	 */
	private static function mark_email_verified( int $user_id ): array {
		update_user_meta( $user_id, self::META_VERIFIED, 1 );
		delete_user_meta( $user_id, self::META_VERIFY_TOKEN );
		delete_user_meta( $user_id, self::META_VERIFY_CODE );
		delete_user_meta( $user_id, self::META_VERIFY_EXPIRES );

		if ( PAXdesign_Customers::STATUS_PENDING === PAXdesign_Customers::account_status( $user_id ) ) {
			PAXdesign_Customers::set_account_status( $user_id, PAXdesign_Customers::STATUS_ACTIVE );
		}

		if (class_exists('PDX_Audit')) { PDX_Audit::log( 'auth', 'email_verified', [ 'user_id' => $user_id ] ); }
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'verification_success', [ 'user_id' => $user_id ] );
		}

		return [ 'success' => true, 'message' => 'Email verified successfully.' ];
	}

	public static function handle_email_verify_link(): void {
		if ( empty( $_GET['pdx_verify'] ) || empty( $_GET['token'] ) || empty( $_GET['uid'] ) ) {
			return;
		}

		$user_id = (int) $_GET['uid'];
		$token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		$result  = self::verify_email( $user_id, $token );

		$redirect = add_query_arg( [
			'pdx_auth'  => $result['success'] ? 'verified' : 'verify_failed',
			'pdx_msg'   => rawurlencode( $result['message'] ?? '' ),
		], home_url( '/' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function block_unverified_login( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
			return $user;
		}
		if ( user_can( $user, manage_options ) ) {
			return $user;
		}
		return $user;
	}

	public static function auth_rate_limit( string $action ): ?array {
		$limits = [
			'login'    => [ 'capacity' => 5,  'refill' => 0.0056, 'cost' => 1 ], // ~5 per 15 min
			'register' => [ 'capacity' => 3,  'refill' => 0.00083, 'cost' => 1 ], // ~3 per hour
			'forgot'   => [ 'capacity' => 3,  'refill' => 0.00083, 'cost' => 1 ],
			'resend'   => [ 'capacity' => 2,  'refill' => 0.00028, 'cost' => 1 ], // ~2 per 2 hours
		];
		$config = $limits[ $action ] ?? $limits['login'];
		$key    = 'auth:' . $action . ':ip:' . md5( sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
		if ( class_exists( 'PDX_RateLimit' ) ) {
			return PDX_RateLimit::check( $key, $config['capacity'], $config['refill'], $config['cost'] );
		}
		$bucket = md5( $key . '|' . gmdate( 'Y-m-d-H-i' ) );
		$transient_key = 'pax_auth_rl_' . $bucket;
		$count = (int) get_transient( $transient_key );
		if ( $count >= (int) $config['capacity'] ) {
			return [ 'allowed' => false, 'retry_after' => 60, 'remaining' => 0 ];
		}
		set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );
		return [ 'allowed' => true, 'retry_after' => 0, 'remaining' => max( 0, (int) $config['capacity'] - $count - 1 ) ];
	}

	private static function send_verification_email( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$token   = bin2hex( random_bytes( 32 ) );
		$code    = sprintf( '%06d', random_int( 0, 999999 ) );
		$expires = time() + ( self::VERIFY_TTL_HOURS * HOUR_IN_SECONDS );

		update_user_meta( $user_id, self::META_VERIFY_TOKEN, hash( 'sha256', $token ) );
		update_user_meta( $user_id, self::META_VERIFY_CODE, hash( 'sha256', $code ) );
		update_user_meta( $user_id, self::META_VERIFY_EXPIRES, $expires );

		$web_link = add_query_arg( [
			'pdx_verify' => '1',
			'token'      => $token,
			'uid'        => $user_id,
		], home_url( '/' ) );

		$app_link = add_query_arg( [
			'uid'   => $user_id,
			'token' => $token,
		], 'paxlivechat://verify' );

		$subject = sprintf( '[%s] Verify your email', get_bloginfo( 'name' ) );
		$body    = self::email_template(
			'Verify your email',
			'Welcome! Confirm your email to unlock full access to PAXDesign. Your verification code expires in ' . self::VERIFY_TTL_HOURS . ' hours.',
			$web_link,
			'Verify Email',
			$code,
			$app_link
		);

		wp_mail( $user->user_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
		if ( class_exists( 'PAXdesign_Auth_Log' ) ) {
			PAXdesign_Auth_Log::event( 'verification_email_sent', [ 'user_id' => $user_id ] );
		}
	}

	private static function email_template( string $title, string $text, string $link, string $button, string $code = '', string $app_link = '' ): string {
		$site = esc_html( get_bloginfo( 'name' ) );
		$code_block = '';
		if ( $code !== '' ) {
			$code_block = '<tr><td style="color:#ffe0a6;font-size:22px;font-weight:700;letter-spacing:6px;text-align:center;padding:16px 0 8px">Verification Code: ' . esc_html( $code ) . '</td></tr>' .
				'<tr><td style="color:#888;font-size:12px;text-align:center;padding-bottom:16px">You can copy this code and enter it in the app if the link does not open.</td></tr>';
		}
		$app_button = '';
		if ( $app_link !== '' ) {
			$app_button = '<tr><td align="center" style="padding-top:12px"><a href="' . esc_url( $app_link ) . '" style="display:inline-block;padding:10px 24px;background:#0a0a0a;border:1px solid #555;color:#ccc;text-decoration:none;font-size:13px">Open in App</a></td></tr>';
		}
		return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#000;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif">' .
			'<table width="100%" cellpadding="0" cellspacing="0" style="background:#000;padding:40px 20px"><tr><td align="center">' .
			'<table width="480" cellpadding="0" cellspacing="0" style="border:1px solid #ffe0a6;border-radius:8px;padding:32px;background:#111">' .
			'<tr><td style="color:#ffe0a6;font-size:20px;font-weight:700;letter-spacing:4px;text-transform:uppercase;text-align:center;padding-bottom:24px">' . esc_html( $title ) . '</td></tr>' .
			'<tr><td style="color:#aaa;font-size:14px;line-height:1.6;padding-bottom:24px">' . esc_html( $text ) . '</td></tr>' .
			'<tr><td align="center"><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:14px 32px;background:#1a1a1a;border:1px solid #ffe0a6;color:#ffe0a6;text-decoration:none;font-weight:600">' . esc_html( $button ) . '</a></td></tr>' .
			$app_button .
			$code_block .
			'<tr><td style="color:#555;font-size:11px;padding-top:24px;text-align:center">' . $site . '</td></tr>' .
			'</table></td></tr></table></body></html>';
	}

	private static function generate_username( string $email ): string {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( ! username_exists( $base ) ) {
			return $base;
		}
		for ( $i = 1; $i <= 100; $i++ ) {
			$candidate = $base . $i;
			if ( ! username_exists( $candidate ) ) {
				return $candidate;
			}
		}
		return $base . wp_generate_password( 4, false );
	}

	/** @return array{allowed:bool,retry_after:int} */
	private static function check_brute_force( string $email ): array {
		$ip_key = 'pdx_bf_ip_' . md5( sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$ip_fails = (int) get_transient( $ip_key );
		if ( $ip_fails >= 10 ) {
			return [ 'allowed' => false, 'retry_after' => 1800 ];
		}
		return [ 'allowed' => true, 'retry_after' => 0 ];
	}

	private static function record_failed_login( string $email, int $user_id = 0 ): void {
		$ip_key = 'pdx_bf_ip_' . md5( sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, 30 * MINUTE_IN_SECONDS );

		if ( $user_id ) {
			$fails = (int) get_user_meta( $user_id, self::META_FAILED_LOGINS, true ) + 1;
			update_user_meta( $user_id, self::META_FAILED_LOGINS, $fails );
			if ( $fails >= 5 ) {
				update_user_meta( $user_id, self::META_LOCKED_UNTIL, time() + 15 * MINUTE_IN_SECONDS );
			}
		}
	}

	private static function clear_failed_logins( string $email, int $user_id ): void {
		delete_transient( 'pdx_bf_ip_' . md5( sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		delete_user_meta( $user_id, self::META_FAILED_LOGINS );
		delete_user_meta( $user_id, self::META_LOCKED_UNTIL );
	}
}
