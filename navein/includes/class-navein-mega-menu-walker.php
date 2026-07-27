<?php
/**
 * Apple-style mega menu walker for the primary header navigation.
 *
 * Adds premium mega-panel classes and custom blue SVG icons for submenu items.
 * Desktop only visually; mobile SlickNav continues to use the same HTML tree.
 *
 * @package NaveinTheme
 * @version 1.0.2
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
		 * Return an Apple-like stroked SVG icon.
		 *
		 * @param string $key Icon key.
		 * @return string
		 */
		public static function get_svg_icon( $key ) {
			$icons = self::svg_library();
			return isset( $icons[ $key ] ) ? $icons[ $key ] : $icons['default'];
		}

		/**
		 * SVG icon library — SF Symbols–inspired strokes; color via currentColor.
		 *
		 * @return array<string,string>
		 */
		public static function svg_library() {
			$common = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false"';

			return array(
				'projects'   => '<svg ' . $common . '><rect x="3" y="3" width="7.5" height="7.5" rx="1.8"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.8"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.8"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.8"/></svg>',
				'visual'     => '<svg ' . $common . '><circle cx="12" cy="12" r="3.2"/><path d="M2.8 12S6.2 5.8 12 5.8 21.2 12 21.2 12 17.8 18.2 12 18.2 2.8 12 2.8 12z"/></svg>',
				'branding'   => '<svg ' . $common . '><path d="M12 3.2l2.1 5.2 5.6.5-4.3 3.7 1.4 5.5L12 15.4l-4.8 2.7 1.4-5.5-4.3-3.7 5.6-.5L12 3.2z"/></svg>',
				'strategy'   => '<svg ' . $common . '><circle cx="12" cy="12" r="8.5"/><path d="M12 7.2v4.3l2.8 2.1"/><circle cx="12" cy="12" r="1.2"/></svg>',
				'ux'         => '<svg ' . $common . '><circle cx="11" cy="11" r="6.2"/><path d="M16.2 16.2 21 21"/><path d="M8.4 11h5.2M11 8.4v5.2"/></svg>',
				'commerce'   => '<svg ' . $common . '><path d="M3.5 5h2.2l1.6 10.2h10.4l1.8-7.2H7.1"/><circle cx="9.2" cy="18.6" r="1.3"/><circle cx="16.4" cy="18.6" r="1.3"/></svg>',
				'concept'    => '<svg ' . $common . '><path d="M9.2 18.5h5.6"/><path d="M10.2 21h3.6"/><path d="M8.2 15.8c-2.2-1.4-3.6-3.6-3.6-6.1A5.4 5.4 0 0 1 12 4.4a5.4 5.4 0 0 1 7.4 5.3c0 2.5-1.4 4.7-3.6 6.1H8.2z"/></svg>',
				'systems'    => '<svg ' . $common . '><path d="M4 8.2 12 4.4l8 3.8-8 3.8-8-3.8z"/><path d="M4 12.4l8 3.8 8-3.8"/><path d="M4 16.6l8 3.8 8-3.8"/></svg>',
				'consulting' => '<svg ' . $common . '><path d="M5 6.2h14a1.8 1.8 0 0 1 1.8 1.8v6.2A1.8 1.8 0 0 1 19 16H9.2L5 19.4V8A1.8 1.8 0 0 1 6.8 6.2H5z"/><path d="M8.5 10.4h7M8.5 13h4.5"/></svg>',
				'app'        => '<svg ' . $common . '><rect x="7" y="2.8" width="10" height="18.4" rx="2.4"/><path d="M10.5 5.4h3"/><circle cx="12" cy="17.8" r="1"/></svg>',
				'software'   => '<svg ' . $common . '><path d="M8.2 8.2 4.8 12l3.4 3.8"/><path d="M15.8 8.2 19.2 12l-3.4 3.8"/><path d="M13.2 6.4l-2.4 11.2"/></svg>',
				'web'        => '<svg ' . $common . '><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.4 2.6 3.7 5.5 3.7 8.5S14.4 17.9 12 20.5C9.6 17.9 8.3 15 8.3 12S9.6 6.1 12 3.5z"/></svg>',
				'about'      => '<svg ' . $common . '><circle cx="12" cy="8" r="3.2"/><path d="M5.5 19.2c1.4-3.2 3.7-4.8 6.5-4.8s5.1 1.6 6.5 4.8"/></svg>',
				'privacy'    => '<svg ' . $common . '><path d="M12 3.4 4.8 6.6v5.2c0 4.5 3.1 7.6 7.2 8.8 4.1-1.2 7.2-4.3 7.2-8.8V6.6L12 3.4z"/><path d="M9.2 12.1l1.9 1.9 3.7-3.8"/></svg>',
				'docs'       => '<svg ' . $common . '><path d="M7.2 3.8h7.2L18.8 8.2v12a1.8 1.8 0 0 1-1.8 1.8H7.2A1.8 1.8 0 0 1 5.4 20.2V5.6A1.8 1.8 0 0 1 7.2 3.8z"/><path d="M14.2 3.8v4.6h4.6"/><path d="M8.8 13h6.4M8.8 16.4h4.4"/></svg>',
				'support'    => '<svg ' . $common . '><path d="M14.4 4.6 19.4 9.6l-7.2 7.2H7.2v-5l7.2-7.2z"/><path d="M12.6 6.4l3 3"/><path d="M4.6 19.4h5"/></svg>',
				'experts'    => '<svg ' . $common . '><circle cx="9" cy="8.2" r="2.6"/><circle cx="16.2" cy="9" r="2.2"/><path d="M3.8 18.8c1.1-2.8 3-4.2 5.2-4.2s4.1 1.4 5.2 4.2"/><path d="M14.2 14.8c1.5-.4 3-.1 4.4 1.4"/></svg>',
				'career'     => '<svg ' . $common . '><rect x="3.5" y="7.2" width="17" height="12.4" rx="2"/><path d="M9 7.2V5.8A1.8 1.8 0 0 1 10.8 4h2.4A1.8 1.8 0 0 1 15 5.8v1.4"/><path d="M3.5 12.2h17"/></svg>',
				'services'   => '<svg ' . $common . '><path d="M12 3.5 14 8l4.8.5-3.6 3.2 1.1 4.7L12 14.2 7.7 16.4l1.1-4.7-3.6-3.2L10 8l2-4.5z"/></svg>',
				'contact'    => '<svg ' . $common . '><rect x="3.5" y="5.5" width="17" height="13" rx="2.2"/><path d="m4.8 7.6 7.2 5.4 7.2-5.4"/></svg>',
				'default'    => '<svg ' . $common . '><circle cx="12" cy="12" r="8.5"/><path d="M12 8.2v4.2"/><circle cx="12" cy="15.8" r="0.9" fill="currentColor" stroke="none"/></svg>',
			);
		}
	}

endif;
