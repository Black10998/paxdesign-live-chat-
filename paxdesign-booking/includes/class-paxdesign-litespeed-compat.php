<?php
/**
 * LiteSpeed Cache compatibility — avoid getimagesize() warnings on broken or remote images.
 *
 * LiteSpeed "Add Missing Sizes" calls getimagesize() for every <img> without width/height.
 * Broken local AVIF files, hotlinked SVGs (tailus.io, Wikimedia), and blocked URLs spam
 * debug.log and can amplify outbound HTTP under cache warm / concurrent load.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_LiteSpeed_Compat {

    /** Legacy team photo served as text/plain — getimagesize() fails even at HTTP 200. */
    const LEGACY_BROKEN_TEAM_AVIF = '38319D43-77FD-42D8-91BA-69E23BE7879C-e1767119492655.avif';

    /** Host/path fragments known to 404 or 403 when LiteSpeed probes them. */
    const PROBLEMATIC_URI_FRAGMENTS = array(
        'tailus.io',
        'upload.wikimedia.org',
        'wikimedia.org/wikipedia',
        '38319D43-77FD-42D8-91BA-69E23BE7879C-e1767119492655.avif',
    );

    public static function init() {
        add_filter('litespeed_media_ignore_remote_missing_sizes', '__return_true');
        add_filter('litespeed_media_lazy_img_excludes', array(__CLASS__, 'lazy_img_excludes'));
    }

    /**
     * Exclude problematic images from LiteSpeed lazy-load / missing-size processing.
     *
     * @param array|string $excludes Existing excludes from LiteSpeed config.
     * @return array
     */
    public static function lazy_img_excludes($excludes) {
        if (!is_array($excludes)) {
            $excludes = $excludes !== '' ? array($excludes) : array();
        }

        foreach (self::PROBLEMATIC_URI_FRAGMENTS as $fragment) {
            $excludes[] = $fragment;
        }

        // SVG dimensions are not reliably detectable via getimagesize().
        $excludes[] = '.svg';

        return array_values(array_unique($excludes));
    }

    /**
     * Whether a URL matches a known problematic fragment (for repair scripts / logging).
     */
    public static function is_problematic_image_url($url) {
        $url = (string) $url;
        if ($url === '') {
            return false;
        }
        foreach (self::PROBLEMATIC_URI_FRAGMENTS as $fragment) {
            if (stripos($url, $fragment) !== false) {
                return true;
            }
        }
        return (bool) preg_match('/\.svg(?:\?|$)/i', $url);
    }
}

PAXdesign_LiteSpeed_Compat::init();
