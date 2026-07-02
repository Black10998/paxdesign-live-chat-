<?php
/**
 * GitHub Release Update Checker for PAXdesign Booking
 *
 * PERMANENT CORE FEATURE — DO NOT REMOVE OR REPLACE
 *
 * Enables one-click updates from WordPress admin via GitHub Releases.
 * Contract:
 *   - GitHub repo: Black10998/paxdesign-live-chat-
 *   - Release tag:  v{major.minor.patch} (e.g. v3.56.0)
 *   - Plugin ZIP:   paxdesign-booking-v{major.minor.patch}.zip
 *   - Plugin file:  paxdesign-booking/paxdesign-booking.php
 *
 * CI validates this contract via scripts/validate-release-contract.sh
 */

if (!defined('ABSPATH')) {
    exit;
}

class PAXdesign_Booking_Update_Checker {

    const GITHUB_REPO = 'Black10998/paxdesign-live-chat-';
    const SLUG        = 'paxdesign-booking/paxdesign-booking.php';
    const CACHE_KEY   = 'paxdesign_booking_update_info';
    const CACHE_TTL   = 3600;
    const ZIP_PREFIX  = 'paxdesign-booking-v';

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'check_for_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_download', array(__CLASS__, 'authorize_package_download'), 10, 4);
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);
    }

    public static function clear_cache($upgrader, $options) {
        if (
            !isset($options['action'], $options['type'])
            || $options['action'] !== 'update'
            || $options['type'] !== 'plugin'
        ) {
            return;
        }

        if (isset($options['plugins']) && is_array($options['plugins'])) {
            if (!in_array(self::SLUG, $options['plugins'], true)) {
                return;
            }
        }

        self::clear_update_cache();
    }

    public static function clear_update_cache() {
        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
    }

    private static function installed_version($transient) {
        if (!empty($transient->checked[self::SLUG])) {
            return (string) $transient->checked[self::SLUG];
        }

        return PAXDESIGN_BOOKING_VERSION;
    }

    private static function github_headers() {
        $headers = array(
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'PAXdesign-Booking-Updater/' . PAXDESIGN_BOOKING_VERSION,
        );

        $token = '';
        if (defined('PAXDESIGN_GITHUB_TOKEN') && PAXDESIGN_GITHUB_TOKEN) {
            $token = PAXDESIGN_GITHUB_TOKEN;
        } else {
            $token = get_option('paxdesign_github_token', '');
        }

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private static function find_plugin_zip_url(array $assets, $version) {
        if (empty($assets) || !is_array($assets)) {
            return '';
        }

        $expected = self::ZIP_PREFIX . $version . '.zip';

        foreach ($assets as $asset) {
            if (!empty($asset['name']) && $asset['name'] === $expected) {
                return !empty($asset['browser_download_url']) ? $asset['browser_download_url'] : '';
            }
        }

        foreach ($assets as $asset) {
            if (
                !empty($asset['name'])
                && preg_match('/^' . preg_quote(self::ZIP_PREFIX, '/') . '[0-9]+\.[0-9]+\.[0-9]+\.zip$/', $asset['name'])
            ) {
                return !empty($asset['browser_download_url']) ? $asset['browser_download_url'] : '';
            }
        }

        return '';
    }

    private static function fetch_release() {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
            array(
                'timeout' => 15,
                'headers' => self::github_headers(),
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['tag_name'])) {
            return null;
        }

        $version = ltrim($data['tag_name'], 'v');
        $zip_url = self::find_plugin_zip_url(!empty($data['assets']) ? $data['assets'] : array(), $version);
        $sha256  = '';

        if (empty($zip_url)) {
            return null;
        }

        if (!empty($data['body']) && preg_match('/SHA256:\s*([a-f0-9]{64})/i', $data['body'], $m)) {
            $sha256 = strtolower($m[1]);
        }

        $release = array(
            'version' => $version,
            'url'     => !empty($data['html_url']) ? $data['html_url'] : 'https://github.com/' . self::GITHUB_REPO . '/releases',
            'zip'     => $zip_url,
            'sha256'  => $sha256,
            'notes'   => !empty($data['body']) ? $data['body'] : '',
        );

        set_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
        return $release;
    }

    private static function build_update_object(array $release) {
        return (object) array(
            'slug'        => 'paxdesign-booking',
            'plugin'      => self::SLUG,
            'new_version' => $release['version'],
            'url'         => $release['url'],
            'package'     => $release['zip'],
            'tested'      => get_bloginfo('version'),
        );
    }

    public static function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = self::fetch_release();
        if (!$release || empty($release['version']) || empty($release['zip'])) {
            return $transient;
        }

        $current = self::installed_version($transient);
        $plugin  = self::build_update_object($release);

        if (version_compare($current, $release['version'], '<')) {
            $transient->response[self::SLUG] = $plugin;
        } else {
            $transient->no_update[self::SLUG] = $plugin;
        }

        return $transient;
    }

    /**
     * Attach GitHub auth headers when WordPress downloads the protected package.
     */
    public static function authorize_package_download($reply, $package, $upgrader, $hook_extra) {
        if (empty($package) || strpos($package, 'github.com') === false) {
            return $reply;
        }

        $token = defined('PAXDESIGN_GITHUB_TOKEN') && PAXDESIGN_GITHUB_TOKEN
            ? PAXDESIGN_GITHUB_TOKEN
            : get_option('paxdesign_github_token', '');

        if (!$token) {
            return $reply;
        }

        add_filter('http_request_args', function ($args, $url) use ($package, $token) {
            if ($url === $package || strpos($url, 'github.com') !== false) {
                $args['headers']['Authorization'] = 'Bearer ' . $token;
                $args['headers']['Accept'] = 'application/octet-stream';
            }
            return $args;
        }, 10, 2);

        return $reply;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'paxdesign-booking') {
            return $result;
        }

        $release = self::fetch_release();
        if (!$release) {
            return $result;
        }

        return (object) array(
            'name'          => 'PAXdesign Booking System',
            'slug'          => 'paxdesign-booking',
            'version'       => $release['version'],
            'author'        => '<a href="https://paxdesign.at">PAXdesign</a>',
            'homepage'      => 'https://paxdesign.at',
            'download_link' => $release['zip'],
            'sections'      => array(
                'description' => 'Professional booking system with unified dark design language.',
                'changelog'   => $release['notes'],
            ),
        );
    }
}
