<?php
/**
 * GitHub release updater for the NTF Fence Estimator.
 *
 * Self-contained: no store, no license. WordPress checks the latest GitHub release of
 * SiteSearchBrAIn/Fence-Calculator, shows it on the Plugins and Updates screens, and installs it
 * with the normal one-click update (or `wp plugin update ntf-fence-estimator`). The per-plugin
 * "Enable auto-updates" link works too.
 *
 * Private repo: define NTF_FENCE_GH_TOKEN in wp-config.php with a read-only token (fine-grained,
 * Contents: Read on this one repo). The token is only ever sent to api.github.com. A public repo
 * needs no token.
 */
if (!defined('ABSPATH')) { exit; }

if (!defined('NTF_FENCE_GH_REPO')) { define('NTF_FENCE_GH_REPO', 'SiteSearchBrAIn/Fence-Calculator'); }

final class NTF_Fence_Updater {
    const SLUG      = 'ntf-fence-estimator';
    const CACHE_KEY = 'ntf_fence_gh_release';

    private static $file;
    private static $basename;

    public static function init($main_file) {
        self::$file     = $main_file;
        self::$basename = plugin_basename($main_file);

        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_download', array(__CLASS__, 'download_package'), 10, 3);
        add_filter('upgrader_source_selection', array(__CLASS__, 'fix_source'), 10, 4);
        add_action('upgrader_process_complete', array(__CLASS__, 'after_upgrade'), 10, 2);
        // Fresh check when an admin presses "Check again" and before WP-CLI reads update state.
        add_action('load-update-core.php', array(__CLASS__, 'maybe_bust'));
        add_action('load-plugins.php', array(__CLASS__, 'maybe_bust'));
        if (defined('WP_CLI') && WP_CLI) { add_action('init', array(__CLASS__, 'bust')); }
    }

    /* ---------- GitHub API ---------- */

    private static function headers($api = true) {
        $h = array('User-Agent' => 'NTF-Fence-Estimator/' . NTF_FENCE_VERSION . '; ' . home_url());
        if ($api) {
            $h['Accept'] = 'application/vnd.github+json';
            $h['X-GitHub-Api-Version'] = '2022-11-28';
            if (defined('NTF_FENCE_GH_TOKEN') && NTF_FENCE_GH_TOKEN) { $h['Authorization'] = 'Bearer ' . NTF_FENCE_GH_TOKEN; }
        }
        return $h;
    }

    /** Latest published release as array(version, zip, url, notes, published), or null. Cached 6h (1h after a failure). */
    public static function latest($force = false) {
        if (!$force) {
            $c = get_transient(self::CACHE_KEY);
            if (is_array($c)) { return $c; }
            if ($c === 'error') { return null; }
        }
        $res = wp_remote_get('https://api.github.com/repos/' . NTF_FENCE_GH_REPO . '/releases/latest', array('headers' => self::headers(), 'timeout' => 15));
        $rel = is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200 ? null : json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($rel) || empty($rel['tag_name']) || empty($rel['zipball_url']) || !empty($rel['draft']) || !empty($rel['prerelease'])) {
            set_transient(self::CACHE_KEY, 'error', HOUR_IN_SECONDS);
            return null;
        }
        $info = array(
            'version'   => ltrim((string) $rel['tag_name'], 'vV'),
            'zip'       => (string) $rel['zipball_url'],
            'url'       => (string) ($rel['html_url'] ?? 'https://github.com/' . NTF_FENCE_GH_REPO),
            'notes'     => (string) ($rel['body'] ?? ''),
            'published' => (string) ($rel['published_at'] ?? ''),
        );
        set_transient(self::CACHE_KEY, $info, 6 * HOUR_IN_SECONDS);
        return $info;
    }

    public static function bust() {
        delete_transient(self::CACHE_KEY);
    }

    public static function maybe_bust() {
        if (!empty($_GET['force-check'])) { self::bust(); }
    }

    /* ---------- Update list ---------- */

    public static function inject_update($transient) {
        if (!is_object($transient)) { return $transient; }
        $current = defined('NTF_FENCE_VERSION') ? NTF_FENCE_VERSION : '0.0.0';
        $rel     = self::latest();
        $item    = (object) array(
            'id'          => 'github.com/' . NTF_FENCE_GH_REPO,
            'slug'        => self::SLUG,
            'plugin'      => self::$basename,
            'new_version' => $current,
            'url'         => 'https://github.com/' . NTF_FENCE_GH_REPO,
            'package'     => '',
        );
        if ($rel && version_compare($current, $rel['version'], '<')) {
            $item->new_version = $rel['version'];
            $item->url         = $rel['url'];
            $item->package     = $rel['zip'];
            unset($transient->no_update[self::$basename]);
            $transient->response[self::$basename] = $item;
        } else {
            // Listing it under no_update is what makes WordPress offer the auto-updates toggle.
            unset($transient->response[self::$basename]);
            $transient->no_update[self::$basename] = $item;
        }
        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) { return $result; }
        $rel = self::latest();
        $ver = $rel ? $rel['version'] : (defined('NTF_FENCE_VERSION') ? NTF_FENCE_VERSION : '');
        return (object) array(
            'name'          => 'Privacy Fence Estimator (New Tampa Fence)',
            'slug'          => self::SLUG,
            'version'       => $ver,
            'author'        => 'Steve Scott SEO',
            'homepage'      => 'https://github.com/' . NTF_FENCE_GH_REPO,
            'last_updated'  => $rel ? $rel['published'] : '',
            'sections'      => array(
                'description' => '<p>Lead-gated fence cost calculator with JobNimbus lead push.</p>',
                'changelog'   => '<pre>' . esc_html($rel ? $rel['notes'] : 'See the releases on GitHub.') . '</pre>',
            ),
            'download_link' => $rel ? $rel['zip'] : '',
        );
    }

    /* ---------- Install ---------- */

    /**
     * Download the release zip ourselves. GitHub's zipball URL answers with a redirect to a signed
     * storage URL; the token must go to api.github.com only, never on to the storage host.
     */
    public static function download_package($reply, $package, $upgrader) {
        $prefix = 'https://api.github.com/repos/' . NTF_FENCE_GH_REPO . '/zipball/';
        if (!is_string($package) || strpos($package, $prefix) !== 0) { return $reply; }

        $tmp = wp_tempnam($package);
        if (!$tmp) { return new WP_Error('ntf_fence_tmp', 'Could not create a temporary file for the update.'); }

        $first = wp_remote_get($package, array('headers' => self::headers(), 'redirection' => 0, 'timeout' => 30));
        if (is_wp_error($first)) { @unlink($tmp); return $first; }
        $code = wp_remote_retrieve_response_code($first);

        if ($code >= 300 && $code < 400) {
            $loc = wp_remote_retrieve_header($first, 'location');
            if (!$loc) { @unlink($tmp); return new WP_Error('ntf_fence_redirect', 'GitHub redirected without a location.'); }
            $dl = wp_remote_get($loc, array('headers' => self::headers(false), 'timeout' => 300, 'stream' => true, 'filename' => $tmp));
            if (is_wp_error($dl)) { @unlink($tmp); return $dl; }
            $code = wp_remote_retrieve_response_code($dl);
        } elseif ($code === 200) {
            file_put_contents($tmp, wp_remote_retrieve_body($first));
        }

        if ($code !== 200 || !is_file($tmp) || filesize($tmp) < 1024) {
            @unlink($tmp);
            return new WP_Error('ntf_fence_download', 'Could not download the update from GitHub (HTTP ' . (int) $code . '). If the repo is private, check NTF_FENCE_GH_TOKEN.');
        }
        return $tmp;
    }

    /**
     * GitHub zips extract to a folder named <owner>-<repo>-<sha>. WordPress installs the extracted folder
     * under whatever name it has, so it must be renamed to the real plugin folder or the update would leave
     * a second copy and deactivate the plugin. Refuse anything that is not the plugin.
     */
    public static function fix_source($source, $remote_source, $upgrader, $hook_extra) {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== self::$basename) { return $source; }
        global $wp_filesystem;

        $main = trailingslashit($source) . basename(self::$basename);
        if (!$wp_filesystem->exists($main)) {
            return new WP_Error('ntf_fence_bad_package', 'The downloaded update does not contain ' . basename(self::$basename) . ', so it was not installed.');
        }
        $head = get_file_data($main, array('name' => 'Plugin Name', 'version' => 'Version'));
        if (empty($head['name']) || empty($head['version'])) {
            return new WP_Error('ntf_fence_bad_package', 'The downloaded update has no valid plugin header, so it was not installed.');
        }

        $desired = trailingslashit($remote_source) . self::SLUG . '/';
        if (untrailingslashit($source) === untrailingslashit($desired)) { return $source; }
        if (!$wp_filesystem->move(untrailingslashit($source), untrailingslashit($desired), true)) {
            return new WP_Error('ntf_fence_rename', 'Could not prepare the update folder.');
        }
        return $desired;
    }

    public static function after_upgrade($upgrader, $options) {
        if (($options['action'] ?? '') === 'update' && ($options['type'] ?? '') === 'plugin'
            && !empty($options['plugins']) && in_array(self::$basename, (array) $options['plugins'], true)) {
            self::bust();
        }
    }
}
