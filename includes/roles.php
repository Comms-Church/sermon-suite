<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Roles and capabilities for the publish API.
 *
 * The publish endpoint is gated on a dedicated capability rather than
 * manage_options, so automation can run as a locked-down user instead of an
 * administrator. The CSV import routes stay admin-only.
 */

define( 'SS_API_ROLE',         'sermon_api_bot' );
define( 'SS_API_CAP',          'publish_via_sermon_api' );
// Bump when the capability set below changes, so existing sites pick it up.
define( 'SS_API_ROLE_VERSION', '1' );

/**
 * Create the role and grant the capability.
 *
 * Idempotent: safe to call on every activation, and safe to call again after
 * the capability set changes.
 */
function sermon_suite_install_api_role() {
    $caps = [
        'read'      => true,
        SS_API_CAP  => true,
    ];

    $role = get_role( SS_API_ROLE );
    if ( ! $role ) {
        add_role( SS_API_ROLE, 'Sermon API Bot', $caps );
    } else {
        // Role already exists (an earlier version, or hand-edited) — make sure
        // it carries the current capabilities without disturbing the rest.
        foreach ( $caps as $cap => $grant ) {
            $role->add_cap( $cap, $grant );
        }
    }

    // Administrators keep the capability too, so an admin Application Password
    // that already works against this endpoint does not break on update, and
    // so a human can exercise the endpoint while testing.
    $admin = get_role( 'administrator' );
    if ( $admin ) $admin->add_cap( SS_API_CAP );

    update_option( 'sermon_suite_api_role_version', SS_API_ROLE_VERSION );
}

/**
 * Activation covers fresh installs. Updates do NOT re-run activation hooks, so
 * the version-gated check on init is what actually reaches existing sites when
 * they update the plugin.
 */
add_action( 'init', 'sermon_suite_maybe_install_api_role' );
function sermon_suite_maybe_install_api_role() {
    if ( get_option( 'sermon_suite_api_role_version' ) === SS_API_ROLE_VERSION ) return;
    sermon_suite_install_api_role();
}
