<?php
/**
 * Sermon Suite — Self-hosted updater via GitHub Releases.
 *
 * Checks the GitHub Releases API for the configured repo once per day.
 * When a newer tagged release exists, WordPress shows the update in the
 * normal Plugins screen and Dashboard → Updates, and installs the zip
 * attached to that release.
 *
 * Based on the lightweight self-hosted update pattern popularized by
 * Misha Rudrastyh and Austin Ginder (anchor.host), adapted to read the
 * GitHub Releases API directly so there is no separate manifest file to
 * maintain.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Sermon_Suite_Updater {

    /** GitHub owner/repo that hosts the releases. */
    const GITHUB_REPO = 'Comms-Church/sermon-suite';

    /** Plugin folder + main file, e.g. "sermon-suite/sermon-suite.php". */
    private $plugin_basename;

    /** Plugin folder slug, e.g. "sermon-suite". */
    private $plugin_slug;

    /** Current installed version (from the plugin header constant). */
    private $version;

    /** Transient key for caching the GitHub API response. */
    private $cache_key = 'sermon_suite_updater';

    /** Whether to use the cache (set false while developing). */
    private $cache_allowed = true;

    public function __construct() {
        $this->plugin_basename = plugin_basename( SERMON_SUITE_DIR . 'sermon-suite.php' );
        $this->plugin_slug     = dirname( $this->plugin_basename );
        $this->version         = SERMON_SUITE_VERSION;

        add_filter( 'plugins_api',                   [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ] );
        add_action( 'upgrader_process_complete',     [ $this, 'purge_cache' ], 10, 2 );

        // "Check for updates" link on the Plugins screen row.
        add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );
        add_action( 'admin_init',      [ $this, 'maybe_force_check' ] );
    }

    /**
     * Fetch the latest release from the GitHub API (cached for a day).
     *
     * @return object|false Normalized release data or false on failure.
     */
    private function get_remote() {
        $cached = get_transient( $this->cache_key );
        if ( false !== $cached && $this->cache_allowed ) {
            return $cached;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/releases/latest',
            self::GITHUB_REPO
        );

        $response = wp_remote_get( $url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Sermon-Suite-Updater',
            ],
        ] );

        if ( is_wp_error( $response )
            || 200 !== wp_remote_retrieve_response_code( $response )
            || empty( wp_remote_retrieve_body( $response ) ) ) {
            // Cache the failure briefly so we don't hammer the API.
            set_transient( $this->cache_key, false, HOUR_IN_SECONDS );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $body->tag_name ) ) {
            set_transient( $this->cache_key, false, HOUR_IN_SECONDS );
            return false;
        }

        $data = $this->normalize_release( $body );
        set_transient( $this->cache_key, $data, DAY_IN_SECONDS );
        return $data;
    }

    /**
     * Convert a GitHub release object into the fields we need.
     */
    private function normalize_release( $release ) {
        // Strip a leading "v" from tags like "v1.3.0".
        $version = ltrim( $release->tag_name, 'vV' );

        // Prefer an attached .zip asset; fall back to the auto-generated
        // source zip if no asset was uploaded.
        $package = '';
        if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( ! empty( $asset->browser_download_url )
                    && substr( $asset->name, -4 ) === '.zip' ) {
                    $package = $asset->browser_download_url;
                    break;
                }
            }
        }
        if ( ! $package && ! empty( $release->zipball_url ) ) {
            $package = $release->zipball_url;
        }

        $data = new stdClass();
        $data->version      = $version;
        $data->package      = $package;
        $data->changelog    = ! empty( $release->body ) ? $release->body : 'No changelog provided.';
        $data->published_at = ! empty( $release->published_at ) ? $release->published_at : '';
        $data->html_url     = ! empty( $release->html_url ) ? $release->html_url : '';
        return $data;
    }

    /**
     * Populate the "View details" modal on the Plugins screen.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
            return $result;
        }

        $remote = $this->get_remote();
        if ( ! $remote ) {
            return $result;
        }

        $info                = new stdClass();
        $info->name          = 'Sermon Suite';
        $info->slug          = $this->plugin_slug;
        $info->version       = $remote->version;
        $info->author        = '<a href="https://comms.church">Comms.Church</a>';
        $info->homepage      = 'https://comms.church';
        $info->download_link = $remote->package;
        $info->trunk         = $remote->package;
        $info->requires      = '6.0';
        $info->requires_php  = '7.4';
        $info->last_updated  = $remote->published_at;
        $info->sections      = [
            'description' => 'A modern sermon library for WordPress — organized by series, with a REST API, YouTube embedding, topic and campus filtering, scripture references, and downloadable resources.',
            'changelog'   => $this->format_changelog( $remote->changelog ),
        ];

        return $info;
    }

    /**
     * Turn the release notes (Markdown-ish) into simple HTML for the modal.
     */
    private function format_changelog( $text ) {
        $text    = wp_kses_post( $text );
        $lines   = preg_split( '/\r\n|\r|\n/', $text );
        $html    = '';
        $in_list = false;
        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            if ( $trimmed === '' ) continue;
            if ( preg_match( '/^[-*]\s+(.*)/', $trimmed, $m ) ) {
                if ( ! $in_list ) { $html .= '<ul>'; $in_list = true; }
                $html .= '<li>' . esc_html( $m[1] ) . '</li>';
            } elseif ( preg_match( '/^#+\s*(.*)/', $trimmed, $m ) ) {
                if ( $in_list ) { $html .= '</ul>'; $in_list = false; }
                $html .= '<h4>' . esc_html( $m[1] ) . '</h4>';
            } else {
                if ( $in_list ) { $html .= '</ul>'; $in_list = false; }
                $html .= '<p>' . esc_html( $trimmed ) . '</p>';
            }
        }
        if ( $in_list ) $html .= '</ul>';
        return $html;
    }

    /**
     * Inject our update into the list WordPress checks against.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = $this->get_remote();
        if ( ! $remote || empty( $remote->package ) ) {
            return $transient;
        }

        if ( version_compare( $this->version, $remote->version, '<' ) ) {
            $item              = new stdClass();
            $item->slug        = $this->plugin_slug;
            $item->plugin      = $this->plugin_basename;
            $item->new_version = $remote->version;
            $item->package     = $remote->package;
            $item->url         = 'https://comms.church';
            $item->tested      = '6.8';
            $transient->response[ $this->plugin_basename ] = $item;
        } else {
            // No update — record that we checked so WP shows "up to date".
            $item              = new stdClass();
            $item->slug        = $this->plugin_slug;
            $item->plugin      = $this->plugin_basename;
            $item->new_version = $this->version;
            $item->url         = 'https://comms.church';
            $transient->no_update[ $this->plugin_basename ] = $item;
        }

        return $transient;
    }

    /**
     * Clear the cached API response after an update completes.
     */
    public function purge_cache( $upgrader, $options ) {
        if ( 'update' === ( $options['action'] ?? '' )
            && 'plugin' === ( $options['type'] ?? '' ) ) {
            delete_transient( $this->cache_key );
        }
    }

    /**
     * Add a "Check for updates" link under the plugin on the Plugins screen.
     */
    public function row_meta( $links, $file ) {
        if ( $file === $this->plugin_basename ) {
            $url = wp_nonce_url(
                add_query_arg(
                    [ 'sermon_suite_force_check' => '1' ],
                    admin_url( 'plugins.php' )
                ),
                'sermon_suite_force_check'
            );
            $links[] = '<a href="' . esc_url( $url ) . '">Check for updates</a>';
        }
        return $links;
    }

    /**
     * Handle the "Check for updates" link — clear cache and re-check.
     */
    public function maybe_force_check() {
        if ( empty( $_GET['sermon_suite_force_check'] ) ) {
            return;
        }
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        check_admin_referer( 'sermon_suite_force_check' );

        delete_transient( $this->cache_key );
        delete_site_transient( 'update_plugins' );

        wp_safe_redirect( admin_url( 'plugins.php' ) );
        exit;
    }
}

// Boot the updater.
add_action( 'init', function() {
    new Sermon_Suite_Updater();
} );
