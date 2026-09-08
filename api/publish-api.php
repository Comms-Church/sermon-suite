<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * POST /sermon-suite/v1/sermons/publish
 *
 * Publishes a single sermon from an external automation — the one-at-a-time
 * counterpart to the CSV importer. Gated on the publish_via_sermon_api
 * capability (see includes/roles.php) rather than manage_options, so the
 * automation can authenticate as a locked-down Sermon API Bot user instead of
 * an administrator. A WordPress Application Password over HTTP Basic is all
 * that is needed — no extra secret. The CSV import routes remain admin-only.
 *
 * Storage conventions deliberately match admin/importer.php:
 *   - series is a POST TYPE (ss_series) linked by the _ss_series_id meta,
 *     not a taxonomy
 *   - YouTube is stored as the bare 11-char id in _ss_youtube_id
 *   - scripture writes _ss_scripture_ref + a Bible Gateway _ss_scripture_url
 *     built from the sermon_suite_bible_version option
 *   - resources are [label, url, type] rows in _ss_resources
 *
 * Calling it twice for the same sermon updates in place rather than creating a
 * duplicate, and only touches fields present in the payload — an omitted key
 * leaves whatever the church has since edited alone. post_content is the one
 * exception: it is written on create only, never overwritten on update, since
 * the sermon body is the field most likely to be edited in the admin. Supplying
 * a new date re-dates the post so it reshuffles into place.
 */

add_action( 'rest_api_init', 'sermon_suite_register_publish_route' );

function sermon_suite_register_publish_route() {
    register_rest_route( 'sermon-suite/v1', '/sermons/publish', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'ss_rest_publish_sermon',
        'permission_callback' => function() { return current_user_can( SS_API_CAP ); },
    ]);
}

/**
 * Find an existing sermon by title, the same get_posts() shape the importer
 * uses for skip_existing, disambiguating on _ss_sermon_date when more than one
 * sermon shares a title.
 */
function ss_find_existing_sermon( $title, $date = '' ) {
    $matches = get_posts([
        'post_type'      => 'ss_sermon',
        'title'          => $title,
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ]);
    if ( ! $matches ) return null;
    if ( count($matches) === 1 ) return $matches[0];

    if ( $date ) {
        foreach ( $matches as $m ) {
            if ( get_post_meta($m->ID, '_ss_sermon_date', true) === $date ) return $m;
        }
    }
    return $matches[0];
}

function ss_rest_publish_sermon( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( ! is_array($params) ) $params = $request->get_params();

    // Returns null for a key that is absent or blank, so "not supplied" and
    // "supplied empty" both mean leave-it-alone rather than wipe-it.
    $field = function( $key ) use ( $params ) {
        if ( ! isset($params[$key]) ) return null;
        $v = is_string($params[$key]) ? trim($params[$key]) : $params[$key];
        return ( $v === '' || $v === null ) ? null : $v;
    };

    $title = $field('sermon_title');
    if ( ! $title ) {
        return new WP_Error('missing_title', 'sermon_title is required.', ['status' => 400]);
    }

    $date = $field('date');
    if ( $date && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ) {
        return new WP_Error('bad_date', 'date must be formatted YYYY-MM-DD.', ['status' => 400]);
    }

    $long  = $field('long_description');
    $short = $field('short_description');

    $existing = ss_find_existing_sermon( $title, $date ?: '' );

    if ( $existing ) {
        $status  = 'updated';
        $post_id = $existing->ID;

        // post_content is written once, at creation. A re-run of the automation
        // must not overwrite a body the church has edited by hand since.
        $update = [ 'ID' => $post_id ];
        if ( $short !== null ) $update['post_excerpt'] = sanitize_textarea_field($short);

        // A new date re-dates the sermon so it reshuffles into place: post_date
        // drives WordPress ordering, _ss_sermon_date (set below) drives the
        // plugin's own display and series ordering. post_date_gmt and edit_date
        // are passed explicitly rather than left to core's inference — on WP 7
        // post_date alone is enough, but this plugin supports 6.0+, and the
        // explicit GMT value is correct regardless of the site's timezone.
        if ( $date ) {
            $update['post_date']     = $date . ' 00:00:00';
            $update['post_date_gmt'] = get_gmt_from_date( $date . ' 00:00:00' );
            $update['edit_date']     = true;
        }

        if ( count($update) > 1 ) {
            $res = wp_update_post($update, true);
            if ( is_wp_error($res) ) return $res;
        }
    } else {
        $status  = 'created';
        $post_id = wp_insert_post([
            'post_type'    => 'ss_sermon',
            'post_title'   => $title,
            'post_content' => $long  !== null ? wp_kses_post($long) : '',
            'post_excerpt' => $short !== null ? sanitize_textarea_field($short) : '',
            'post_status'  => 'publish',
            'post_date'    => $date ? $date . ' 00:00:00' : current_time('mysql'),
        ], true);
        if ( is_wp_error($post_id) ) return $post_id;
    }

    if ( $date ) update_post_meta($post_id, '_ss_sermon_date', $date);

    // YouTube — stored as the bare video id, extracted the same way the
    // importer and the admin editor do.
    if ( $youtube = $field('youtube_url') ) {
        $yt_id = ss_get_youtube_id($youtube);
        if ( $yt_id ) update_post_meta($post_id, '_ss_youtube_id', $yt_id);
    }

    // Series: an ss_series POST, referenced by id. Created if it doesn't exist
    // yet, matching how the importer creates series before linking sermons.
    if ( $series_name = $field('series_name') ) {
        $series = get_posts([
            'post_type'      => 'ss_series',
            'title'          => $series_name,
            'posts_per_page' => 1,
            'post_status'    => 'any',
        ]);
        $series_id = $series ? $series[0]->ID : wp_insert_post([
            'post_type'   => 'ss_series',
            'post_title'  => $series_name,
            'post_status' => 'publish',
        ], true);
        if ( $series_id && ! is_wp_error($series_id) ) {
            update_post_meta($post_id, '_ss_series_id', (int)$series_id);
        }
    }

    // Speaker and campus are both non-hierarchical taxonomies on ss_sermon;
    // passing a name creates the term if it is new.
    if ( $speaker = $field('speaker_name') ) wp_set_post_terms($post_id, [$speaker], 'ss_speaker');
    if ( $campus  = $field('campus') )       wp_set_post_terms($post_id, [$campus],  'ss_campus');

    // Scripture — identical to the importer's scm mapping.
    if ( $scripture = $field('scripture_reference') ) {
        update_post_meta($post_id, '_ss_scripture_ref', $scripture);
        $ver = get_option('sermon_suite_bible_version', 'NIV');
        update_post_meta(
            $post_id,
            '_ss_scripture_url',
            'https://www.biblegateway.com/passage/?search=' . urlencode($scripture) . '&version=' . $ver
        );
        $book = preg_replace('/\s*\d.*/', '', $scripture);
        if ( $book ) wp_set_post_terms($post_id, [$book], 'ss_scripture_book', true);
    }

    if ( $teaser = $field('social_teaser') ) {
        update_post_meta($post_id, '_ss_social_teaser', $teaser);
    }

    // Discussion guide joins _ss_resources in the importer's row shape, typed
    // by the same rules it applies to Series Engine file rows. Matching on url
    // keeps a repeat call from stacking duplicates.
    if ( $guide_url = $field('discussion_guide_url') ) {
        $resources = ss_get_sermon_resources($post_id);
        $already   = false;
        foreach ( $resources as $r ) {
            if ( isset($r['url']) && $r['url'] === $guide_url ) { $already = true; break; }
        }
        if ( ! $already ) {
            $type = 'link';
            if ( strpos($guide_url, '.pdf') !== false )          $type = 'pdf';
            elseif ( strpos($guide_url, 'sermonsend') !== false ) $type = 'devotional';
            $resources[] = [
                'label' => 'Discussion Guide',
                'url'   => esc_url_raw($guide_url),
                'type'  => $type,
            ];
            update_post_meta($post_id, '_ss_resources', $resources);
        }
    }

    return rest_ensure_response([
        'post_id'   => (int) $post_id,
        'status'    => $status,
        'edit_link' => get_edit_post_link($post_id, 'raw'),
    ]);
}
