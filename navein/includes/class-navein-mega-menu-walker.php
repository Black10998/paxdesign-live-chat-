<?php
/**
 * Apple-style mega menu walker for the primary header navigation.
 *
 * Adds premium mega-panel classes and custom blue SVG icons for submenu items.
 * Desktop only visually; mobile SlickNav continues to use the same HTML tree.
 *
 * @package NaveinTheme
 * @version 1.0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Navein_Mega_Menu_Walker' ) ) :

	/**
	 * Walker that upgrades first-level dropdowns into mega menus.
	 */
	class Navein_Mega_Menu_Walker extends Walker_Nav_Menu {

		/**
		 * Start the submenu list.
		 *
		 * @param string   $output Used to append additional content.
		 * @param int      $depth  Depth of menu item.
		 * @param stdClass $args   Menu arguments.
		 */
		public function start_lvl( &$output, $depth = 0, $args = null ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$indent = str_repeat( $t, $depth );
			$classes = ( 0 === (int) $depth ) ? 'sub-menu dtr-mega-panel' : 'sub-menu';
			$output .= "{$n}{$indent}<ul class=\"{$classes}\">{$n}";
		}

		/**
		 * Start a menu element.
		 *
		 * @param string   $output Used to append additional content.
		 * @param WP_Post  $item   Menu item data object.
		 * @param int      $depth  Depth of menu item.
		 * @param stdClass $args   Menu arguments.
		 * @param int      $id     Current item ID.
		 */
		public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$indent = ( $depth ) ? str_repeat( $t, $depth ) : '';

			$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
			$classes[] = 'menu-item-' . $item->ID;

			$has_children = in_array( 'menu-item-has-children', $classes, true );

			if ( 0 === (int) $depth && $has_children ) {
				$classes[] = 'dtr-has-mega';
			}

			if ( $depth >= 1 ) {
				$classes[] = 'dtr-mega-item';
			}

			$icon_key  = self::resolve_icon_key( $item );
			$classes[] = 'dtr-mega-icon--' . sanitize_html_class( $icon_key );

			$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

			$class_names = implode( ' ', array_filter( array_map( 'sanitize_html_class', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) ) ) );
			$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

			$id_attr = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
			$id_attr = $id_attr ? ' id="' . esc_attr( $id_attr ) . '"' : '';

			$output .= $indent . '<li' . $id_attr . $class_names . '>';

			$atts           = array();
			$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
			$atts['target'] = ! empty( $item->target ) ? $item->target : '';
			if ( '_blank' === $atts['target'] && empty( $item->xfn ) ) {
				$atts['rel'] = 'noopener';
			} else {
				$atts['rel'] = $item->xfn;
			}
			$atts['href']         = ! empty( $item->url ) ? $item->url : '';
			$atts['aria-current'] = $item->current ? 'page' : '';

			if ( 0 === (int) $depth && $has_children ) {
				$atts['aria-haspopup'] = 'true';
				$atts['aria-expanded'] = 'false';
			}

			$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

			$attributes = '';
			foreach ( $atts as $attr => $value ) {
				if ( is_scalar( $value ) && '' !== $value && false !== $value ) {
					$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
					$attributes .= ' ' . $attr . '="' . $value . '"';
				}
			}

			$title = apply_filters( 'the_title', $item->title, $item->ID );
			$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

			$item_output  = isset( $args->before ) ? $args->before : '';
			$item_output .= '<a' . $attributes . '>';
			$item_output .= isset( $args->link_before ) ? $args->link_before : '';

			if ( $depth >= 1 ) {
				$meta         = self::get_item_meta( $item, $icon_key );
				$item_output .= '<span class="dtr-mega-icon" aria-hidden="true">' . self::get_svg_icon( $icon_key ) . '</span>';
				$item_output .= '<span class="dtr-mega-copy">';
				$item_output .= '<span class="dtr-mega-title">' . esc_html( $title ) . '</span>';
				if ( ! empty( $meta['desc'] ) ) {
					$item_output .= '<span class="dtr-mega-desc">' . esc_html( $meta['desc'] ) . '</span>';
				}
				$item_output .= '</span>';
			} else {
				$item_output .= esc_html( $title );
			}

			$item_output .= isset( $args->link_after ) ? $args->link_after : '';
			$item_output .= '</a>';
			$item_output .= isset( $args->after ) ? $args->after : '';

			$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
		}

		/**
		 * Resolve an icon key from the menu item title / URL.
		 *
		 * @param WP_Post $item Menu item.
		 * @return string
		 */
		public static function resolve_icon_key( $item ) {
			$haystack = strtolower(
				trim(
					wp_strip_all_tags(
						(string) $item->title . ' ' . (string) $item->url . ' ' . (string) $item->post_name
					)
				)
			);
			$haystack = remove_accents( $haystack );

			$map = array(
				'projekte'         => 'projects',
				'referenzen'       => 'projects',
				'visuelles'        => 'visual',
				'visual'           => 'visual',
				'marken'           => 'branding',
				'branding'         => 'branding',
				'art-direction'    => 'strategy',
				'strategie'        => 'strategy',
				'strategy'         => 'strategy',
				'ux'               => 'ux',
				'forschung'        => 'ux',
				'research'         => 'ux',
				'e-commerce'       => 'commerce',
				'ecommerce'        => 'commerce',
				'konzept'          => 'concept',
				'product'          => 'concept',
				'produktdesign'    => 'concept',
				'advanced-website' => 'systems',
				'website-systems'  => 'systems',
				'systems'          => 'systems',
				'consulting'       => 'consulting',
				'it-consulting'    => 'consulting',
				'app-entwicklung'  => 'app',
				'app'              => 'app',
				'software'         => 'software',
				'webentwicklung'   => 'web',
				'webdesign'        => 'web',
				'ueber-uns'        => 'about',
				'uber-uns'         => 'about',
				'about'            => 'about',
				'datenschutz'      => 'privacy',
				'privacy'          => 'privacy',
				'dokumentation'    => 'docs',
				'documentation'    => 'docs',
				'wartung'          => 'support',
				'support'          => 'support',
				'experten'         => 'experts',
				'experts'          => 'experts',
				'karriere'         => 'career',
				'career'           => 'career',
				'leistungen'       => 'services',
				'services'         => 'services',
				'kontakt'          => 'contact',
				'contact'          => 'contact',
				'hire'             => 'contact',
			);

			foreach ( $map as $needle => $key ) {
				if ( false !== strpos( $haystack, $needle ) ) {
					return $key;
				}
			}

			return 'default';
		}

		/**
		 * Optional short descriptions for known items.
		 *
		 * @param WP_Post $item     Menu item.
		 * @param string  $icon_key Icon key.
		 * @return array{desc:string}
		 */
		public static function get_item_meta( $item, $icon_key ) {
			$descriptions = array(
				'projects'   => 'Ausgewählte Arbeiten & Cases',
				'visual'     => 'Visuelle Systeme & Design',
				'branding'   => 'Markenauftritt digital denken',
				'strategy'   => 'Richtung, Story & Kreativität',
				'ux'         => 'Nutzerforschung & Insights',
				'commerce'   => 'Shops mit Conversion-Fokus',
				'concept'    => 'Konzept & Produktstrategie',
				'systems'    => 'Skalierbare Website-Systeme',
				'consulting' => 'Technische Beratung & Planung',
				'app'        => 'iOS, Android & Cross-Platform',
				'software'   => 'Individuelle Softwarelösungen',
				'web'        => 'Moderne Webentwicklung',
				'about'      => 'Team, Werte & Geschichte',
				'privacy'    => 'Datenschutz & Compliance',
				'docs'       => 'Guides & Service-Infos',
				'support'    => 'Wartung, Updates & Hilfe',
				'experts'    => 'Menschen hinter PAXdesign',
				'career'     => 'Offene Rollen & Kultur',
				'services'   => 'Leistungen im Überblick',
				'contact'    => 'Direkter Draht zum Team',
				'default'    => '',
			);

			$desc = isset( $descriptions[ $icon_key ] ) ? $descriptions[ $icon_key ] : '';

			return apply_filters(
				'navein_mega_menu_item_meta',
				array(
					'desc' => $desc,
				),
				$item,
				$icon_key
			);
		}

		/**
		 * Return an Apple-like solid SF Symbol–style SVG icon.
		 *
		 * @param string $key Icon key.
		 * @return string
		 */
		public static function get_svg_icon( $key ) {
			$icons = self::svg_library();
			return isset( $icons[ $key ] ) ? $icons[ $key ] : $icons['default'];
		}

		/**
		 * SVG icon library — solid / polished SF Symbols style; color via currentColor.
		 *
		 * @return array<string,string>
		 */
		public static function svg_library() {
			$common = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false" aria-hidden="true"';

			return array(
				'projects'   => '<svg ' . $common . '><path d="M4.2 3.4h6.1c.7 0 1.3.6 1.3 1.3v6.1c0 .7-.6 1.3-1.3 1.3H4.2c-.7 0-1.3-.6-1.3-1.3V4.7c0-.7.6-1.3 1.3-1.3zm9.5 0h6.1c.7 0 1.3.6 1.3 1.3v6.1c0 .7-.6 1.3-1.3 1.3h-6.1c-.7 0-1.3-.6-1.3-1.3V4.7c0-.7.6-1.3 1.3-1.3zM4.2 12.9h6.1c.7 0 1.3.6 1.3 1.3v6.1c0 .7-.6 1.3-1.3 1.3H4.2c-.7 0-1.3-.6-1.3-1.3v-6.1c0-.7.6-1.3 1.3-1.3zm9.5 0h6.1c.7 0 1.3.6 1.3 1.3v6.1c0 .7-.6 1.3-1.3 1.3h-6.1c-.7 0-1.3-.6-1.3-1.3v-6.1c0-.7.6-1.3 1.3-1.3z"/></svg>',
				'visual'     => '<svg ' . $common . '><path d="M12 5.2c4.6 0 8.4 3.2 9.6 6.1a1.2 1.2 0 0 1 0 1.4C20.4 15.6 16.6 18.8 12 18.8S3.6 15.6 2.4 12.7a1.2 1.2 0 0 1 0-1.4C3.6 8.4 7.4 5.2 12 5.2zm0 2.4a4.4 4.4 0 1 0 0 8.8 4.4 4.4 0 0 0 0-8.8zm0 2.2a2.2 2.2 0 1 1 0 4.4 2.2 2.2 0 0 1 0-4.4z"/></svg>',
				'branding'   => '<svg ' . $common . '><path d="M12 2.6l2.35 5.55 6.05.55-4.6 3.95 1.45 5.85L12 15.95 6.75 18.5l1.45-5.85-4.6-3.95 6.05-.55L12 2.6z"/></svg>',
				'strategy'   => '<svg ' . $common . '><path d="M12 2.4a9.6 9.6 0 1 1 0 19.2 9.6 9.6 0 0 1 0-19.2zm0 2.2a7.4 7.4 0 1 0 0 14.8 7.4 7.4 0 0 0 0-14.8zm.9 3.1v3.55l2.55 1.85a1.05 1.05 0 1 1-1.2 1.72l-3.05-2.22A1.05 1.05 0 0 1 10.7 10.7V7.7a1.05 1.05 0 0 1 2.1 0z"/></svg>',
				'ux'         => '<svg ' . $common . '><path d="M11 3.4a7.6 7.6 0 0 1 5.95 12.3l3.55 3.55a1.15 1.15 0 0 1-1.63 1.63l-3.55-3.55A7.6 7.6 0 1 1 11 3.4zm0 2.3a5.3 5.3 0 1 0 0 10.6 5.3 5.3 0 0 0 0-10.6zm-.95 2.55h1.9v1.75H13.7v1.9h-1.75V13.4h-1.9v-1.75H8.3v-1.9h1.75V8.25z"/></svg>',
				'commerce'   => '<svg ' . $common . '><path d="M3.2 4.2h2.45c.5 0 .94.34 1.07.82L7.3 7.4h12.35c.9 0 1.55.85 1.3 1.7l-1.7 5.75a1.9 1.9 0 0 1-1.82 1.35H8.55a1.9 1.9 0 0 1-1.85-1.45L4.55 5.9H3.2a1.15 1.15 0 1 1 0-2.3zm5.75 14.1a1.85 1.85 0 1 1 0 3.7 1.85 1.85 0 0 1 0-3.7zm8.1 0a1.85 1.85 0 1 1 0 3.7 1.85 1.85 0 0 1 0-3.7z"/></svg>',
				'concept'    => '<svg ' . $common . '><path d="M12 2.6a6.7 6.7 0 0 1 3.85 12.15c-.55.4-.95.95-1.1 1.6h-5.5c-.15-.65-.55-1.2-1.1-1.6A6.7 6.7 0 0 1 12 2.6zM9.55 17.85h4.9c.45 0 .8.35.8.8v.35c0 .9-.55 1.7-1.4 2.05-.2.55-.7.95-1.35.95h-1c-.65 0-1.15-.4-1.35-.95-.85-.35-1.4-1.15-1.4-2.05v-.35c0-.45.35-.8.8-.8z"/></svg>',
				'systems'    => '<svg ' . $common . '><path d="M12.05 3.1 20.4 7.1a1.1 1.1 0 0 1 0 1.95l-8.35 4a1.15 1.15 0 0 1-1 0l-8.35-4a1.1 1.1 0 0 1 0-1.95l8.35-4a1.15 1.15 0 0 1 1 0zm8.35 8.15-1.7.8-6.15 2.95a1.7 1.7 0 0 1-1.5 0L4.9 12.05l-1.7-.8a1.05 1.05 0 0 0-.95 1.85l8.35 4a1.7 1.7 0 0 0 1.5 0l8.35-4a1.05 1.05 0 0 0-.95-1.85zm0 3.85-1.7.8-6.15 2.95a1.7 1.7 0 0 1-1.5 0L4.9 15.9l-1.7-.8a1.05 1.05 0 0 0-.95 1.85l8.35 4a1.7 1.7 0 0 0 1.5 0l8.35-4a1.05 1.05 0 0 0-.95-1.85z"/></svg>',
				'consulting' => '<svg ' . $common . '><path d="M5.4 4.2h13.2A2.4 2.4 0 0 1 21 6.6v7.1a2.4 2.4 0 0 1-2.4 2.4h-7.55L6.2 19.7a1 1 0 0 1-1.6-.85v-2.75A2.4 2.4 0 0 1 3 13.7V6.6A2.4 2.4 0 0 1 5.4 4.2zm2.3 4.7a1 1 0 0 0 0 2h8.6a1 1 0 1 0 0-2H7.7zm0 3.4a1 1 0 0 0 0 2h5.4a1 1 0 1 0 0-2H7.7z"/></svg>',
				'app'        => '<svg ' . $common . '><path d="M8.4 1.8h7.2A2.7 2.7 0 0 1 18.3 4.5v15a2.7 2.7 0 0 1-2.7 2.7H8.4A2.7 2.7 0 0 1 5.7 19.5v-15A2.7 2.7 0 0 1 8.4 1.8zm2.1 1.9a.85.85 0 0 0 0 1.7h3a.85.85 0 0 0 0-1.7h-3zM12 17.35a1.35 1.35 0 1 0 0 2.7 1.35 1.35 0 0 0 0-2.7z"/></svg>',
				'software'   => '<svg ' . $common . '><path d="M8.85 7.35a1.15 1.15 0 0 1 0 1.63L6.1 11.75l2.75 2.77a1.15 1.15 0 1 1-1.63 1.63l-3.55-3.58a1.15 1.15 0 0 1 0-1.63l3.55-3.58a1.15 1.15 0 0 1 1.63 0zm6.3 0a1.15 1.15 0 0 1 1.63 0l3.55 3.58a1.15 1.15 0 0 1 0 1.63l-3.55 3.58a1.15 1.15 0 1 1-1.63-1.63l2.75-2.77-2.75-2.77a1.15 1.15 0 0 1 0-1.63zM13.85 5.9a1.15 1.15 0 0 1 .88 1.37l-2.7 10.7a1.15 1.15 0 1 1-2.25-.57l2.7-10.7a1.15 1.15 0 0 1 1.37-.88z"/></svg>',
				'web'        => '<svg ' . $common . '><path d="M12 2.3a9.7 9.7 0 1 1 0 19.4 9.7 9.7 0 0 1 0-19.4zm0 2.15c-1.55 1.85-2.45 4.2-2.55 6.7h5.1c-.1-2.5-1-4.85-2.55-6.7zm-4.7 8.85c.2 2.35 1.15 4.55 2.65 6.3a7.55 7.55 0 0 1 0-12.6c-1.5 1.75-2.45 3.95-2.65 6.3zm11.6 0c-.2 2.35-1.15 4.55-2.65 6.3a7.55 7.55 0 0 0 0-12.6c1.5 1.75 2.45 3.95 2.65 6.3zm-4.35 0c-.1 2.5-1 4.85-2.55 6.7-1.55-1.85-2.45-4.2-2.55-6.7h5.1z"/></svg>',
				'about'      => '<svg ' . $common . '><path d="M12 3.2a4.35 4.35 0 1 1 0 8.7 4.35 4.35 0 0 1 0-8.7zM6.1 18.95c1.55-3.05 3.95-4.55 5.9-4.55s4.35 1.5 5.9 4.55c.45.9-.2 1.95-1.2 1.95H7.3c-1 0-1.65-1.05-1.2-1.95z"/></svg>',
				'privacy'    => '<svg ' . $common . '><path d="M12 2.55 4.55 5.7v5.55c0 4.85 3.25 8.2 7.45 9.45 4.2-1.25 7.45-4.6 7.45-9.45V5.7L12 2.55zm-.05 11.85-2.55-2.55a1.05 1.05 0 0 1 1.48-1.48l1.1 1.1 2.85-2.85a1.05 1.05 0 0 1 1.48 1.48l-3.6 3.6a1.05 1.05 0 0 1-1.48 0z" fill-rule="evenodd"/></svg>',
				'docs'       => '<svg ' . $common . '><path d="M7.1 2.7h6.55L18.9 7.95V19.7A2.1 2.1 0 0 1 16.8 21.8H7.1A2.1 2.1 0 0 1 5 19.7V4.8A2.1 2.1 0 0 1 7.1 2.7zm6.2 1.9v3.55c0 .55.45 1 1 1h3.55l-4.55-4.55zM8.55 12.2h6.9a.95.95 0 1 1 0 1.9h-6.9a.95.95 0 1 1 0-1.9zm0 3.5h4.7a.95.95 0 1 1 0 1.9h-4.7a.95.95 0 1 1 0-1.9z"/></svg>',
				'support'    => '<svg ' . $common . '><path d="M14.85 3.35a1.7 1.7 0 0 1 2.4 0l3.4 3.4a1.7 1.7 0 0 1 0 2.4l-7.55 7.55a1.7 1.7 0 0 1-1.05.5l-4.35.4a1.4 1.4 0 0 1-1.5-1.5l.4-4.35c.05-.4.25-.75.5-1.05l7.55-7.55zM4.4 18.35h5.1a1.15 1.15 0 1 1 0 2.3H4.4a1.15 1.15 0 1 1 0-2.3z"/></svg>',
				'experts'    => '<svg ' . $common . '><path d="M9.1 4.4a3.55 3.55 0 1 1 0 7.1 3.55 3.55 0 0 1 0-7.1zm7.35 1.15a2.95 2.95 0 1 1 0 5.9 2.95 2.95 0 0 1 0-5.9zM3.95 19.4c1.35-2.85 3.45-4.25 5.15-4.25s3.8 1.4 5.15 4.25c.4.85-.2 1.85-1.15 1.85H5.1c-.95 0-1.55-1-.15-1.85-.35 0-.7.05-1 .15zm10.55-2.55c1.35-.45 2.85-.2 4.25 1.2.55.55.35 1.5-.4 1.75-.55.2-1.15.3-1.8.3h-1.45c-.35-.85-.9-1.6-1.6-2.2v-1.05z"/></svg>',
				'career'     => '<svg ' . $common . '><path d="M9.2 4.35h5.6c.85 0 1.55.7 1.55 1.55v1.2h2.75A2.3 2.3 0 0 1 21.4 9.4v9.05A2.3 2.3 0 0 1 19.1 20.75H4.9A2.3 2.3 0 0 1 2.6 18.45V9.4A2.3 2.3 0 0 1 4.9 7.1h2.75V5.9c0-.85.7-1.55 1.55-1.55zm1.55 2.75h2.5V7.1h-2.5v-.95zM3.95 12.05h16.1v-1.9c0-.4-.3-.7-.7-.7H4.65c-.4 0-.7.3-.7.7v1.9z"/></svg>',
				'services'   => '<svg ' . $common . '><path d="M12 2.5l2.2 5.2 5.65.5-4.3 3.7 1.35 5.5L12 14.85 7.1 17.4l1.35-5.5-4.3-3.7 5.65-.5L12 2.5z"/></svg>',
				'contact'    => '<svg ' . $common . '><path d="M4.6 4.45h14.8A2.35 2.35 0 0 1 21.75 6.8v10.4a2.35 2.35 0 0 1-2.35 2.35H4.6A2.35 2.35 0 0 1 2.25 17.2V6.8A2.35 2.35 0 0 1 4.6 4.45zm.55 2.35 6.15 4.55c.4.3.95.3 1.35 0l6.15-4.55H5.15zm14.25 1.85-5.55 4.1a3.1 3.1 0 0 1-3.7 0l-5.55-4.1V17.2c0 .2.15.35.35.35h14.1c.2 0 .35-.15.35-.35V8.65z"/></svg>',
				'default'    => '<svg ' . $common . '><path d="M12 2.4a9.6 9.6 0 1 1 0 19.2 9.6 9.6 0 0 1 0-19.2zm0 2.2a7.4 7.4 0 1 0 .05 14.8A7.4 7.4 0 0 0 12 4.6zm0 10.75a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm0-7.85c.7 0 1.25.55 1.25 1.25v4.2a1.25 1.25 0 1 1-2.5 0v-4.2c0-.7.55-1.25 1.25-1.25z"/></svg>',
			);
		}
	}

endif;
