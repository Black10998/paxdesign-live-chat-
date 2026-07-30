<?php
/**
 * WP-CLI eval-file: simulate Apple web finish ticket for smoke testing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via: wp eval-file ...\n" );
	exit( 1 );
}

require_once WP_PLUGIN_DIR . '/paxdesign-booking/includes/auth/class-paxdesign-auth-apple.php';

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => array( 'ID' ),
	)
);
$user_id = ! empty( $admins[0]->ID ) ? (int) $admins[0]->ID : 0;
if ( $user_id <= 0 ) {
	fwrite( STDERR, "No administrator user found.\n" );
	exit( 1 );
}

$finish_url = PAXdesign_Auth_Apple::web_complete_url_for_user( $user_id, PAXdesign_Auth_Page::page_url() . '#/overview' );
echo "finish_url={$finish_url}\n";
