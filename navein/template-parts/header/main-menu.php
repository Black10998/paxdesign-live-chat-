<?php
/**
 * The Main Menu
 *
 * @package NaveinTheme
 * @version 1.0.2
 */

if ( has_nav_menu( 'primary_menu' ) ) {
	$walker = null;
	if ( class_exists( 'Navein_Mega_Menu_Walker' ) ) {
		$walker = new Navein_Mega_Menu_Walker();
	}

	wp_nav_menu(
		array(
			'theme_location'  => 'primary_menu',
			'container'       => '',
			'container_class' => '',
			'container_id'    => '',
			'menu_class'      => 'dtr-nav sf-menu dtr-main-nav',
			'menu_id'         => '',
			'depth'           => 0,
			'walker'          => $walker,
		)
	);
}
