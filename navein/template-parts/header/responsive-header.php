<?php
/**
 * The template for displaying responsive header
 *
 * @package NaveinTheme
 * @version 1.0.0
 */
?>
<div id="dtr-responsive-header">
    <div class="container">
        <?php if (navein_get_theme_option('navein_resp_logo_type', 'navein_resp_main_logo') == 'navein_resp_main_logo') {
            get_template_part('/template-parts/header/logo');
        } else {
            get_template_part('/template-parts/header/logo-alt');
        }
        ?>
        <button id="dtr-menu-button" class="dtr-hamburger" type="button" aria-label="<?php esc_attr_e('Menu Button', 'navein'); ?>" aria-expanded="false">
			<span class="dtr-amnav-burger" aria-hidden="true">
				<span class="dtr-amnav-burger__row">
					<span class="dtr-amnav-burger__dot"></span>
					<span class="dtr-amnav-burger__dot"></span>
				</span>
				<span class="dtr-amnav-burger__row dtr-amnav-burger__row--bottom">
					<span class="dtr-amnav-burger__dot"></span>
					<span class="dtr-amnav-burger__dot"></span>
				</span>
				<span class="dtr-amnav-burger__col">
					<span class="dtr-amnav-burger__dot"></span>
					<span class="dtr-amnav-burger__dot dtr-amnav-burger__dot--mid-v"></span>
					<span class="dtr-amnav-burger__dot"></span>
				</span>
				<span class="dtr-amnav-burger__rail">
					<span class="dtr-amnav-burger__dot"></span>
					<span class="dtr-amnav-burger__dot dtr-amnav-burger__dot--mid-h"></span>
					<span class="dtr-amnav-burger__dot"></span>
				</span>
			</span>
		</button>
    </div>
    <div class="dtr-responsive-header-menu"></div>
</div>