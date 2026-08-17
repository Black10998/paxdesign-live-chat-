<?php
/**
 * Public identity hardening.
 *
 * Stops unauthenticated visitors from enumerating WordPress accounts via
 * core REST users routes and author archives. Disables unused XML-RPC.
 * Privileged staff (list_users) and logged-in /users/me keep working.
 *
 * @package NaveinTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'paxdesign_current_user_can_list_users' ) ) {
	/**
	 * Whether the current request may see WordPress account listings.
	 */
	function paxdesign_current_user_can_list_users() {
		return function_exists( 'current_user_can' ) && current_user_can( 'list_users' );
	}
}

if ( ! function_exists( 'paxdesign_is_users_rest_route' ) ) {
	/**
	 * @param string $route REST route.
	 */
	function paxdesign_is_users_rest_route( $route ) {
		$route = (string) $route;
		if ( $route === '' ) {
			return false;
		}
		if ( strpos( $route, '/wp/v2/users' ) === 0 ) {
			return true;
		}
		return (bool) preg_match( '#(^|/)wp/v2/users(/|$)#', $route );
	}
}

if ( ! function_exists( 'paxdesign_is_users_me_rest_route' ) ) {
	/**
	 * @param string $route REST route.
	 */
	function paxdesign_is_users_me_rest_route( $route ) {
		return (bool) preg_match( '#/wp/v2/users/me(/|$)#', (string) $route );
	}
}

if ( ! function_exists( 'paxdesign_restrict_users_rest_endpoints' ) ) {
	/**
	 * Hide core users collection/detail routes from the public.
	 *
	 * @param array $endpoints Registered REST endpoints.
	 * @return array
	 */
	function paxdesign_restrict_users_rest_endpoints( $endpoints ) {
		if ( ! is_array( $endpoints ) ) {
			return $endpoints;
		}
		if ( paxdesign_current_user_can_list_users() ) {
			return $endpoints;
		}

		$logged_in = function_exists( 'is_user_logged_in' ) && is_user_logged_in();
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( ! paxdesign_is_users_rest_route( $route ) ) {
				continue;
			}
			if ( $logged_in && paxdesign_is_users_me_rest_route( $route ) ) {
				continue;
			}
			unset( $endpoints[ $route ] );
		}

		return $endpoints;
	}
}

if ( ! function_exists( 'paxdesign_block_public_users_rest_dispatch' ) ) {
	/**
	 * Belt-and-suspenders: reject leftover public users REST dispatches.
	 *
	 * @param mixed            $result  Dispatch result.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 * @return mixed
	 */
	function paxdesign_block_public_users_rest_dispatch( $result, $server, $request ) {
		unset( $server );
		if ( paxdesign_current_user_can_list_users() ) {
			return $result;
		}

		$route = '';
		if ( is_object( $request ) && method_exists( $request, 'get_route' ) ) {
			$route = (string) $request->get_route();
		}
		if ( $route === '' && isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$route = (string) wp_unslash( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! paxdesign_is_users_rest_route( $route ) ) {
			return $result;
		}
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && paxdesign_is_users_me_rest_route( $route ) ) {
			return $result;
		}

		return new WP_Error(
			'rest_user_cannot_view',
			'You are not allowed to list users.',
			array( 'status' => 401 )
		);
	}
}

if ( ! function_exists( 'paxdesign_is_public_author_probe' ) ) {
	/**
	 * Author archive or ?author=N probe that would reveal an account slug.
	 */
	function paxdesign_is_public_author_probe() {
		if ( is_admin() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		if ( paxdesign_current_user_can_list_users() ) {
			return false;
		}
		if ( function_exists( 'is_author' ) && is_author() ) {
			return true;
		}
		if ( isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'paxdesign_disable_author_canonical_redirect' ) ) {
	/**
	 * Stop redirect_canonical from leaking /author/{slug}/ in Location.
	 *
	 * @param string|false $redirect_url  Canonical URL.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	function paxdesign_disable_author_canonical_redirect( $redirect_url, $requested_url ) {
		unset( $requested_url );
		if ( paxdesign_is_public_author_probe() ) {
			return false;
		}
		return $redirect_url;
	}
}

if ( ! function_exists( 'paxdesign_redirect_public_author_archives' ) ) {
	/**
	 * Redirect public author probes to the homepage before canonical runs.
	 */
	function paxdesign_redirect_public_author_archives() {
		if ( ! paxdesign_is_public_author_probe() ) {
			return;
		}
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}

if ( ! function_exists( 'paxdesign_ignore_public_author_rest_query' ) ) {
	/**
	 * Ignore author filters on public content REST queries.
	 *
	 * @param array           $args    WP_Query args.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	function paxdesign_ignore_public_author_rest_query( $args, $request ) {
		unset( $request );
		if ( paxdesign_current_user_can_list_users() ) {
			return $args;
		}
		unset( $args['author'], $args['author__in'], $args['author__not_in'], $args['author_name'] );
		return $args;
	}
}

if ( ! function_exists( 'paxdesign_strip_public_author_rest_field' ) ) {
	/**
	 * Remove author user IDs from public post/page REST payloads.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param WP_Post          $post     Post.
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_REST_Response
	 */
	function paxdesign_strip_public_author_rest_field( $response, $post, $request ) {
		unset( $post, $request );
		if ( paxdesign_current_user_can_list_users() ) {
			return $response;
		}
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( is_array( $data ) ) {
			unset( $data['author'] );
			$response->set_data( $data );
		}
		if ( method_exists( $response, 'remove_link' ) ) {
			$response->remove_link( 'author' );
			$response->remove_link( 'https://api.w.org/author' );
		}
		return $response;
	}
}

if ( ! function_exists( 'paxdesign_register_public_author_rest_stripping' ) ) {
	/**
	 * Attach author stripping to every public REST post type.
	 */
	function paxdesign_register_public_author_rest_stripping() {
		if ( ! function_exists( 'get_post_types' ) ) {
			return;
		}
		$types = get_post_types( array( 'show_in_rest' => true ), 'names' );
		if ( ! is_array( $types ) ) {
			return;
		}
		foreach ( $types as $type ) {
			add_filter( 'rest_prepare_' . $type, 'paxdesign_strip_public_author_rest_field', 10, 3 );
			add_filter( 'rest_' . $type . '_query', 'paxdesign_ignore_public_author_rest_query', 10, 2 );
		}
	}
}

if ( ! function_exists( 'paxdesign_disable_user_sitemaps' ) ) {
	/**
	 * @param WP_Sitemaps_Provider|false $provider Provider.
	 * @param string                     $name     Provider name.
	 * @return WP_Sitemaps_Provider|false
	 */
	function paxdesign_disable_user_sitemaps( $provider, $name ) {
		if ( $name === 'users' ) {
			return false;
		}
		return $provider;
	}
}

if ( ! function_exists( 'paxdesign_disable_unused_xmlrpc' ) ) {
	/**
	 * XML-RPC is unused (iOS/chat use REST + application passwords).
	 */
	function paxdesign_disable_unused_xmlrpc() {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo 'XML-RPC is disabled.';
		exit;
	}
}

if ( ! function_exists( 'paxdesign_remove_xmlrpc_pingback_header' ) ) {
	/**
	 * @param array $headers Response headers.
	 * @return array
	 */
	function paxdesign_remove_xmlrpc_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
}

if ( ! function_exists( 'paxdesign_hide_pingback_bloginfo_url' ) ) {
	/**
	 * @param string $output Bloginfo URL.
	 * @param string $show   Field name.
	 * @return string
	 */
	function paxdesign_hide_pingback_bloginfo_url( $output, $show ) {
		if ( $show === 'pingback_url' ) {
			return '';
		}
		return $output;
	}
}

add_filter( 'rest_endpoints', 'paxdesign_restrict_users_rest_endpoints' );
add_filter( 'rest_pre_dispatch', 'paxdesign_block_public_users_rest_dispatch', 10, 3 );
add_filter( 'redirect_canonical', 'paxdesign_disable_author_canonical_redirect', 1, 2 );
add_action( 'template_redirect', 'paxdesign_redirect_public_author_archives', 1 );
add_action( 'rest_api_init', 'paxdesign_register_public_author_rest_stripping', 1 );
add_filter( 'wp_sitemaps_add_provider', 'paxdesign_disable_user_sitemaps', 10, 2 );
add_action( 'init', 'paxdesign_disable_unused_xmlrpc', 0 );
add_filter( 'xmlrpc_enabled', '__return_false', 99 );
add_filter( 'xmlrpc_methods', '__return_empty_array', 99 );
add_filter( 'pings_open', '__return_false', 99 );
add_filter( 'wp_headers', 'paxdesign_remove_xmlrpc_pingback_header' );
add_filter( 'bloginfo_url', 'paxdesign_hide_pingback_bloginfo_url', 10, 2 );

remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
