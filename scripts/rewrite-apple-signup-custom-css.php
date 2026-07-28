<?php
/**
 * Rewrite Customizer Additional CSS Sign Up override to Apple black pill.
 *
 * Usage: wp eval-file rewrite-apple-signup-custom-css.php [/path/to/backup-dir]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$backup_dir = '';
if ( isset( $args ) && is_array( $args ) && ! empty( $args[0] ) ) {
	$backup_dir = (string) $args[0];
} elseif ( ! empty( $argv[1] ) ) {
	$backup_dir = (string) $argv[1];
}

if ( ! function_exists( 'wp_get_custom_css' ) || ! function_exists( 'wp_update_custom_css_post' ) ) {
	fwrite( STDERR, "custom css APIs unavailable\n" );
	return;
}

$css = (string) wp_get_custom_css();
if ( $css === '' || false === strpos( $css, 'pdx-auth-signup-btn' ) ) {
	fwrite( STDOUT, "No Customizer Sign Up override found to rewrite\n" );
	return;
}

if ( $backup_dir !== '' ) {
	if ( ! is_dir( $backup_dir ) ) {
		wp_mkdir_p( $backup_dir );
	}
	file_put_contents( trailingslashit( $backup_dir ) . 'custom-css-before.txt', $css );
}

$replacement = <<<'CSS'
/* Apple Sign Up pill */
.pdx-auth-signup-btn {
    background: #000 !important;
    background-color: #000 !important;
    background-image: none !important;
    color: #fff !important;
    border: 0 !important;
    box-shadow: none !important;
    border-radius: 980px !important;
}
CSS;

$pattern = '/\/\*[^*]*Sign Up[^*]*\*\/\s*\.pdx-auth-signup-btn\s*\{[^}]*\}/iu';
$new_css = preg_replace( $pattern, $replacement, $css, 1, $count );
if ( ! is_string( $new_css ) || (int) $count < 1 ) {
	$new_css = rtrim( $css ) . "\n\n" . $replacement . "\n";
	$count   = 0;
}

if ( $backup_dir !== '' ) {
	file_put_contents( trailingslashit( $backup_dir ) . 'custom-css-after.txt', $new_css );
}

$result = wp_update_custom_css_post( $new_css );
if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'Failed updating custom CSS: ' . $result->get_error_message() . "\n" );
	return;
}

fwrite( STDOUT, "Updated Customizer CSS for Apple Sign Up button (matches={$count})\n" );
