<?php
/**
 * Plugin Name: Sermon Suite
 * Plugin URI:  https://comms.church
 * Description: A modern sermon library organized by series. Includes REST API, YouTube embed, topic filtering, scripture references, and downloadable resources. Built-in CSV importer for Series Engine migration.
 * Version:     1.3.0
 * Author:      Comms.Church
 * License:     GPL-2.0+
 * Text Domain: sermon-suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SERMON_SUITE_VERSION', '1.3.0' );
define( 'SERMON_SUITE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SERMON_SUITE_URL',     plugin_dir_url( __FILE__ ) );
define( 'SERMON_SUITE_PREFIX',  'sermon_suite' );

// Core includes
require_once SERMON_SUITE_DIR . 'includes/post-types.php';
require_once SERMON_SUITE_DIR . 'includes/taxonomies.php';
require_once SERMON_SUITE_DIR . 'includes/meta-fields.php';
require_once SERMON_SUITE_DIR . 'includes/shortcodes.php';
require_once SERMON_SUITE_DIR . 'includes/template-loader.php';
require_once SERMON_SUITE_DIR . 'includes/helpers.php';
require_once SERMON_SUITE_DIR . 'includes/yt-sync.php';
require_once SERMON_SUITE_DIR . 'includes/bible-data.php';
require_once SERMON_SUITE_DIR . 'api/rest-api.php';
if ( is_admin() ) {
    require_once SERMON_SUITE_DIR . 'includes/updater.php';
}
require_once SERMON_SUITE_DIR . 'admin/admin-pages.php';
require_once SERMON_SUITE_DIR . 'admin/custom-editor.php';
require_once SERMON_SUITE_DIR . 'admin/importer.php';
require_once SERMON_SUITE_DIR . 'admin/shortcode-generator.php';
require_once SERMON_SUITE_DIR . 'blocks/blocks.php';

// Activation / deactivation
register_activation_hook( __FILE__,   'sermon_suite_activate' );
register_deactivation_hook( __FILE__, 'sermon_suite_deactivate' );

function sermon_suite_activate() {
    sermon_suite_register_post_types();
    sermon_suite_register_taxonomies();
    flush_rewrite_rules();
}

function sermon_suite_deactivate() {
    flush_rewrite_rules();
}

// ── Brand color helper ─────────────────────────────────────────────────────────
function sermon_suite_get_brand_colors() {
    return [
        'accent'       => get_option('sermon_suite_color_accent',       '#2563eb'),
        'accent_light' => get_option('sermon_suite_color_accent_light', '#eff6ff'),
        'button_text'  => get_option('sermon_suite_color_button_text',  '#ffffff'),
        'text'         => get_option('sermon_suite_color_text',         '#1a1a1a'),
        'text_muted'   => get_option('sermon_suite_color_text_muted',   '#666666'),
        'bg'           => get_option('sermon_suite_color_bg',           '#ffffff'),
        'bg_alt'       => get_option('sermon_suite_color_bg_alt',       '#f5f5f5'),
    ];
}

// Output CSS variables driven by saved brand colors
add_action( 'wp_head', 'sermon_suite_output_brand_css', 20 );
function sermon_suite_output_brand_css() {
    $c = sermon_suite_get_brand_colors();
    echo "\n<style id=\"sermon-suite-brand-colors\">\n";
    echo ":root {\n";
    echo "  --gcc-accent:       " . esc_attr($c['accent'])       . ";\n";
    echo "  --gcc-accent-light: " . esc_attr($c['accent_light']) . ";\n";
    echo "  --gcc-button-text:  " . esc_attr($c['button_text'])  . ";\n";
    echo "  --gcc-text:         " . esc_attr($c['text'])         . ";\n";
    echo "  --gcc-text-muted:   " . esc_attr($c['text_muted'])   . ";\n";
    echo "  --gcc-bg:           " . esc_attr($c['bg'])           . ";\n";
    echo "  --gcc-bg-alt:       " . esc_attr($c['bg_alt'])       . ";\n";
    echo "}\n</style>\n";
}

// Enqueue public assets
add_action( 'wp_enqueue_scripts', 'sermon_suite_enqueue_public' );
function sermon_suite_enqueue_public() {
    wp_enqueue_style(
        'sermon-suite-public',
        SERMON_SUITE_URL . 'public/css/sermons.css',
        [],
        SERMON_SUITE_VERSION
    );
    wp_enqueue_script(
        'sermon-suite-public',
        SERMON_SUITE_URL . 'public/js/sermons.js',
        [ 'jquery' ],
        SERMON_SUITE_VERSION,
        true
    );
    wp_localize_script( 'sermon-suite-public', 'sermonSuite', [
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'restUrl'  => rest_url( 'sermon-suite/v1/' ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'siteUrl'  => get_site_url(),
    ]);
}

// Enqueue admin assets
add_action( 'admin_enqueue_scripts', 'sermon_suite_enqueue_admin' );
function sermon_suite_enqueue_admin( $hook ) {
    // Load on all Sermon Suite pages — identified by hook prefix or explicit list
    $is_ss_page = (
        strpos( $hook, 'sermon-suite' ) !== false ||
        strpos( $hook, 'ss_sermon'  ) !== false ||
        strpos( $hook, 'ss_series'  ) !== false ||
        in_array( $hook, [
            'toplevel_page_sermon-suite',
            'sermon-suite_page_sermon-suite-import',
            'sermon-suite_page_sermon-suite-settings',
            'sermon-suite_page_sermon-suite-api-docs',
            'sermon-suite_page_ss-add-sermon',
            'sermon-suite_page_ss-edit-sermon',
            'sermon-suite_page_ss-add-series',
            'sermon-suite_page_ss-edit-series',
        ])
    );

    if ( ! $is_ss_page ) return;

    wp_enqueue_style(
        'sermon-suite-admin',
        SERMON_SUITE_URL . 'public/css/admin.css',
        [],
        SERMON_SUITE_VERSION
    );
    wp_enqueue_script(
        'sermon-suite-admin',
        SERMON_SUITE_URL . 'public/js/admin.js',
        [ 'jquery' ],
        SERMON_SUITE_VERSION,
        true
    );
    wp_localize_script( 'sermon-suite-admin', 'sermonSuiteAdmin', [
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'restUrl'    => rest_url( 'sermon-suite/v1/' ),
        'nonce'      => wp_create_nonce( 'sermon_suite_admin' ),
        'restNonce'  => wp_create_nonce( 'wp_rest' ),
    ]);
}
