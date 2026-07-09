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
    const CACHE_TTL   = 1800;
    const ZIP_PREFIX  = 'paxdesign-booking-v';

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'check_for_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_download', array(__CLASS__, 'authorize_package_download'), 10, 4);
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);
        add_action('load-update-core.php', array(__CLASS__, 'clear_update_cache'));
        add_action('load-plugins.php', array(__CLASS__, 'maybe_clear_stale_cache'));
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

    /**
     * Bust stale release cache when the installed version has caught up to the cached release.
     * Prevents serving outdated GitHub metadata after a new tag is published.
     */
    public static function maybe_clear_stale_cache() {
        if (!current_user_can('update_plugins')) {
            return;
        }

        $cached = get_transient(self::CACHE_KEY);
        if ($cached === false || empty($cached['version'])) {
            return;
        }

        $installed = self::read_installed_version();
        if ($installed === '') {
            return;
        }

        if (version_compare($installed, $cached['version'], '>=')) {
            delete_transient(self::CACHE_KEY);
        }
    }

    private static function read_installed_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . self::SLUG;
        if (!file_exists($plugin_file)) {
            return defined('PAXDESIGN_BOOKING_VERSION') ? (string) PAXDESIGN_BOOKING_VERSION : '';
        }

        $data = get_plugin_data($plugin_file, false, false);
        if (!empty($data['Version'])) {
            return (string) $data['Version'];
        }

        return defined('PAXDESIGN_BOOKING_VERSION') ? (string) PAXDESIGN_BOOKING_VERSION : '';
    }

    private static function installed_version($transient) {
        if (!empty($transient->checked[self::SLUG])) {
            return (string) $transient->checked[self::SLUG];
        }

        return self::read_installed_version();
    }

    private static function github_token() {
        if (defined('PAXDESIGN_GITHUB_TOKEN') && PAXDESIGN_GITHUB_TOKEN) {
            return (string) PAXDESIGN_GITHUB_TOKEN;
        }

        $stored = get_option('paxdesign_github_token', '');
        return is_string($stored) ? trim($stored) : '';
    }

    private static function github_headers($for_binary = false) {
        $headers = array(
            'Accept'     => $for_binary ? 'application/octet-stream' : 'application/vnd.github+json',
            'User-Agent' => 'PAXdesign-Booking-Updater/' . PAXDESIGN_BOOKING_VERSION,
        );

        $token = self::github_token();
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * @param array<int, array<string, mixed>> $assets
     * @param string                            $version
     * @return array{package: string, browser_package: string, asset_id: int}|null
     */
    private static function find_plugin_zip_asset(array $assets, $version) {
        if (empty($assets) || !is_array($assets)) {
            return null;
        }

        $expected = self::ZIP_PREFIX . $version . '.zip';
        $fallback = null;

        foreach ($assets as $asset) {
            if (empty($asset['name']) || !is_array($asset)) {
                continue;
            }

            $name = (string) $asset['name'];
            if ($name !== $expected && !preg_match('/^' . preg_quote(self::ZIP_PREFIX, '/') . '[0-9]+\.[0-9]+\.[0-9]+\.zip$/', $name)) {
                continue;
            }

            $asset_id = !empty($asset['id']) ? (int) $asset['id'] : 0;
            $api_url  = $asset_id > 0
                ? 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/assets/' . $asset_id
                : (!empty($asset['url']) ? (string) $asset['url'] : '');
            $browser  = !empty($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';

            if ($api_url === '' && $browser === '') {
                continue;
            }

            $entry = array(
                'package'         => $api_url !== '' ? $api_url : $browser,
                'browser_package' => $browser,
                'asset_id'        => $asset_id,
            );

            if ($name === $expected) {
                return $entry;
            }

            if ($fallback === null) {
                $fallback = $entry;
            }
        }

        return $fallback;
    }

    private static function should_refresh_cached_release($cached, $installed_version) {
        if ($cached === false || !is_array($cached) || empty($cached['version'])) {
            return true;
        }

        if ($installed_version !== '' && version_compare($installed_version, $cached['version'], '>=')) {
            return true;
        }

        return false;
    }

    private static function fetch_release($installed_version = '') {
        $cached = get_transient(self::CACHE_KEY);
        if (!self::should_refresh_cached_release($cached, $installed_version)) {
            return $cached;
        }

        delete_transient(self::CACHE_KEY);

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
            array(
                'timeout' => 15,
                'headers' => self::github_headers(false),
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return is_array($cached) ? $cached : null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['tag_name'])) {
            return is_array($cached) ? $cached : null;
        }

        $version = ltrim((string) $data['tag_name'], 'vV');
        $asset   = self::find_plugin_zip_asset(!empty($data['assets']) ? $data['assets'] : array(), $version);

        if ($asset === null || empty($asset['package'])) {
            return is_array($cached) ? $cached : null;
        }

        $sha256 = '';
        if (!empty($data['body']) && preg_match('/SHA256:\s*([a-f0-9]{64})/i', $data['body'], $m)) {
            $sha256 = strtolower($m[1]);
        }

        $release = array(
            'version'         => $version,
            'tag'             => (string) $data['tag_name'],
            'url'             => !empty($data['html_url']) ? $data['html_url'] : 'https://github.com/' . self::GITHUB_REPO . '/releases',
            'zip'             => $asset['package'],
            'browser_zip'     => !empty($asset['browser_package']) ? $asset['browser_package'] : '',
            'asset_id'        => !empty($asset['asset_id']) ? (int) $asset['asset_id'] : 0,
            'sha256'          => $sha256,
            'notes'           => !empty($data['body']) ? $data['body'] : '',
        );

        set_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
        return $release;
    }

    private static function build_update_object(array $release) {
        return (object) array(
            'id'          => !empty($release['asset_id']) ? (int) $release['asset_id'] : 0,
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

        $current = self::installed_version($transient);
        $release = self::fetch_release($current);
        if (!$release || empty($release['version']) || empty($release['zip'])) {
            return $transient;
        }

        if (version_compare($current, $release['version'], '<')) {
            $transient->response[self::SLUG] = self::build_update_object($release);
        }

        return $transient;
    }

    /**
     * Download the GitHub release ZIP ourselves so WordPress always receives a valid binary.
     *
     * @param string $package
     * @return string|WP_Error Absolute path to a temp ZIP file.
     */
    private static function download_github_release_zip($package) {
        $token = self::github_token();
        $urls  = array($package);

        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached['browser_zip'])) {
            $urls[] = (string) $cached['browser_zip'];
        }

        $last_error = null;

        foreach (array_unique(array_filter($urls)) as $url) {
            $is_api_asset = strpos($url, 'api.github.com') !== false
                && strpos($url, '/releases/assets/') !== false;

            if ($is_api_asset && $token === '') {
                $last_error = new WP_Error(
                    'paxdesign_github_auth',
                    'GitHub token required to download private release assets. Configure paxdesign_github_token or PAXDESIGN_GITHUB_TOKEN.'
                );
                continue;
            }

            $headers = array(
                'User-Agent' => 'PAXdesign-Booking-Updater/' . PAXDESIGN_BOOKING_VERSION,
            );
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
            if ($is_api_asset) {
                $headers['Accept'] = 'application/octet-stream';
            }

            $response = wp_remote_get($url, array(
                'timeout'     => 300,
                'redirection' => 5,
                'headers'     => $headers,
                'stream'      => false,
            ));

            if (is_wp_error($response)) {
                $last_error = $response;
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code < 200 || $code >= 300) {
                $last_error = new WP_Error(
                    'paxdesign_github_http',
                    sprintf('GitHub download failed (HTTP %d).', $code)
                );
                continue;
            }

            if (!self::looks_like_zip($body)) {
                $snippet = substr(ltrim($body), 0, 120);
                if ($snippet !== '' && ($snippet[0] === '{' || $snippet[0] === '<')) {
                    $last_error = new WP_Error(
                        'paxdesign_github_not_zip',
                        'GitHub returned metadata/HTML instead of a ZIP file. Check the GitHub token and release asset URL.'
                    );
                } else {
                    $last_error = new WP_Error(
                        'paxdesign_github_not_zip',
                        'Downloaded file is not a valid ZIP archive.'
                    );
                }
                continue;
            }

            if (is_array($cached) && !empty($cached['sha256'])) {
                $hash = hash('sha256', $body);
                if (!hash_equals((string) $cached['sha256'], $hash)) {
                    $last_error = new WP_Error(
                        'paxdesign_github_checksum',
                        'Downloaded ZIP checksum does not match the published release.'
                    );
                    continue;
                }
            }

            if (!function_exists('wp_tempnam')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $temp_file = wp_tempnam(self::ZIP_PREFIX);
            if (!$temp_file) {
                return new WP_Error('paxdesign_temp_file', 'Could not create a temporary file for the update download.');
            }

            $written = file_put_contents($temp_file, $body);
            if ($written === false || $written < 1024) {
                @unlink($temp_file);
                return new WP_Error('paxdesign_write_failed', 'Could not write the downloaded plugin ZIP to disk.');
            }

            return $temp_file;
        }

        return $last_error instanceof WP_Error
            ? $last_error
            : new WP_Error('paxdesign_github_download', 'Could not download the plugin update package from GitHub.');
    }

    /**
     * @param string $body
     */
    private static function looks_like_zip($body) {
        if (!is_string($body) || strlen($body) < 4) {
            return false;
        }

        // ZIP local file header (PK\x03\x04) or empty archive (PK\x05\x06).
        return strncmp($body, "PK\x03\x04", 4) === 0 || strncmp($body, "PK\x05\x06", 4) === 0;
    }

    /**
     * @param mixed $package
     * @param mixed $hook_extra
     */
    private static function is_our_plugin_package($package, $hook_extra) {
        if (!empty($hook_extra['plugin']) && $hook_extra['plugin'] === self::SLUG) {
            return true;
        }

        if (!is_string($package) || $package === '') {
            return false;
        }

        return strpos($package, self::GITHUB_REPO) !== false
            || strpos($package, self::ZIP_PREFIX) !== false;
    }

    /**
     * Intercept GitHub package downloads and return a validated local ZIP path.
     *
     * @param mixed $reply
     * @param mixed $package
     * @param mixed $upgrader
     * @param mixed $hook_extra
     * @return mixed
     */
    public static function authorize_package_download($reply, $package, $upgrader, $hook_extra) {
        if (!is_string($package) || $package === '') {
            return $reply;
        }

        if (!self::is_our_plugin_package($package, $hook_extra)) {
            return $reply;
        }

        $is_github_package = (
            strpos($package, 'github.com') !== false
            || strpos($package, 'api.github.com') !== false
        );

        if (!$is_github_package) {
            return $reply;
        }

        $downloaded = self::download_github_release_zip($package);
        if (is_wp_error($downloaded)) {
            return $downloaded;
        }

        return $downloaded;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'paxdesign-booking') {
            return $result;
        }

        $release = self::fetch_release(self::read_installed_version());
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
