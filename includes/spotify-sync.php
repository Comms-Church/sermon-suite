<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Sermon Suite — Spotify show sync + artwork.
 *
 * The audio counterpart to yt-sync.php. Pulls a podcast show's episodes from
 * the Spotify Web API and creates a draft sermon for each new one, linked to a
 * series exactly the way the YouTube playlist sync does.
 *
 * Auth: the Web API needs a registered app (Client ID + Secret, stored under
 * Sermons -> Settings) and uses the client-credentials flow — no user login, no
 * redirect URI. Episode artwork uses the public oEmbed endpoint instead, which
 * needs no credentials at all, so thumbnails work even without an app.
 */

class Sermon_Suite_Spotify_API {

    const OPT_ID      = 'sermon_suite_spotify_client_id';
    const OPT_SECRET  = 'sermon_suite_spotify_client_secret';
    const TOKEN_TRANS = 'ss_spotify_token';
    const API_BASE    = 'https://api.spotify.com/v1';

    public static function has_credentials() {
        return trim( (string) get_option( self::OPT_ID, '' ) ) !== ''
            && trim( (string) get_option( self::OPT_SECRET, '' ) ) !== '';
    }

    /**
     * Client-credentials token, cached until just before it expires.
     */
    public static function get_token() {
        $cached = get_transient( self::TOKEN_TRANS );
        if ( $cached ) return $cached;

        $id     = trim( (string) get_option( self::OPT_ID, '' ) );
        $secret = trim( (string) get_option( self::OPT_SECRET, '' ) );
        if ( ! $id || ! $secret ) {
            return new WP_Error( 'spotify_no_creds', 'No Spotify Client ID/Secret saved. Add them under Sermons → Settings.' );
        }

        $resp = wp_remote_post( 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $id . ':' . $secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => [ 'grant_type' => 'client_credentials' ],
            'timeout' => 20,
        ] );
        if ( is_wp_error( $resp ) ) return $resp;

        $code = wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( $code === 400 || $code === 401 ) {
            return new WP_Error( 'spotify_auth', 'Spotify rejected the Client ID/Secret. Check them under Sermons → Settings.' );
        }
        if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
            return new WP_Error( 'spotify_token', 'Could not get a Spotify access token (HTTP ' . $code . ').' );
        }

        $ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
        set_transient( self::TOKEN_TRANS, $body['access_token'], $ttl );
        return $body['access_token'];
    }

    /**
     * Every episode of a show, oldest first, following pagination.
     *
     * Filterable so episodes can come from somewhere else entirely (an RSS
     * feed, a fixture in the test suite) without touching the sync itself.
     * Return an array from the filter to bypass the HTTP call.
     */
    public static function get_show_episodes( $show_id ) {
        $stub = apply_filters( 'ss_spotify_episodes', null, $show_id );
        if ( is_array( $stub ) ) return $stub;

        $token = self::get_token();
        if ( is_wp_error( $token ) ) return $token;

        $market   = apply_filters( 'ss_spotify_market', 'US' );
        $episodes = [];
        $offset   = 0;

        // Hard page cap so a runaway feed can't loop forever.
        for ( $page = 0; $page < 20; $page++ ) {
            $url = self::API_BASE . '/shows/' . rawurlencode( $show_id ) . '/episodes'
                 . '?limit=50&offset=' . $offset . '&market=' . rawurlencode( $market );

            $resp = wp_remote_get( $url, [
                'headers' => [ 'Authorization' => 'Bearer ' . $token, 'accept' => 'application/json' ],
                'timeout' => 25,
            ] );
            if ( is_wp_error( $resp ) ) return $resp;

            $code = wp_remote_retrieve_response_code( $resp );
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );

            if ( $code === 404 ) {
                return new WP_Error( 'spotify_404', 'Spotify could not find that show. Check the show link, and that it is available in the ' . $market . ' market.' );
            }
            if ( $code === 401 || $code === 403 ) {
                delete_transient( self::TOKEN_TRANS );
                return new WP_Error( 'spotify_auth', 'Spotify rejected the request (HTTP ' . $code . '). Check the Client ID/Secret under Sermons → Settings.' );
            }
            if ( $code < 200 || $code >= 300 ) {
                return new WP_Error( 'spotify_http', 'Spotify API error (HTTP ' . $code . ').' );
            }

            $items = $body['items'] ?? [];
            foreach ( $items as $item ) {
                if ( empty( $item['id'] ) ) continue;
                $episodes[] = $item;
            }

            if ( empty( $body['next'] ) || count( $items ) === 0 ) break;
            $offset += 50;
        }

        // Spotify returns newest first; sermons read better oldest first, which
        // also makes the series-order numbering below come out chronological.
        return array_reverse( $episodes );
    }

    /**
     * Episode/show artwork via the public oEmbed endpoint — no credentials.
     * Cached hard, including misses, so page rendering never waits on Spotify
     * more than once per item.
     */
    public static function get_thumbnail( $ref ) {
        if ( ! is_array( $ref ) || empty( $ref['id'] ) ) return '';
        $key    = 'ss_sp_thumb_' . substr( md5( $ref['type'] . $ref['id'] ), 0, 20 );
        $cached = get_transient( $key );
        if ( $cached !== false ) return $cached === 'none' ? '' : $cached;

        $url  = 'https://open.spotify.com/oembed?url=' . rawurlencode( ss_spotify_public_url( $ref ) );
        $resp = wp_remote_get( $url, [ 'timeout' => 3 ] );

        $thumb = '';
        if ( ! is_wp_error( $resp ) && wp_remote_retrieve_response_code( $resp ) === 200 ) {
            $body  = json_decode( wp_remote_retrieve_body( $resp ), true );
            $thumb = esc_url_raw( $body['thumbnail_url'] ?? '' );
        }

        // A miss is cached briefly so a flaky lookup retries soon, a hit for a
        // week — episode art effectively never changes.
        set_transient( $key, $thumb ?: 'none', $thumb ? WEEK_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
        return $thumb;
    }
}

/**
 * Pick a card-sized image from a Web API images[] list (usually 640/300/64).
 */
function ss_spotify_pick_image( $images ) {
    if ( ! is_array( $images ) || ! $images ) return '';
    foreach ( $images as $img ) {
        if ( (int) ( $img['width'] ?? 0 ) === 300 && ! empty( $img['url'] ) ) return $img['url'];
    }
    return $images[0]['url'] ?? '';
}

/**
 * Artwork for a sermon's Spotify episode, or '' if it has none.
 * Mirrors ss_youtube_thumb() so callers can fall back the same way.
 *
 * This runs while a page renders, once per card, so it must never be slow:
 *   1. Artwork stored on the post (saved by the show sync, or by an earlier
 *      lookup) is used directly — no network at all.
 *   2. Otherwise a cached oEmbed lookup, capped at a few cold fetches per
 *      page load. A series of fifty synced-before-3.0.1 episodes then fills in
 *      its artwork over a handful of views instead of making one visitor wait
 *      for fifty sequential requests (or time out if Spotify is down).
 * Anything beyond the cap falls back to the series image for that view.
 */
function ss_spotify_thumb( $sermon_id ) {
    $ref = ss_get_spotify_ref( get_post_meta( $sermon_id, '_ss_spotify_url', true ) );
    if ( ! $ref ) return '';

    // Stored artwork, valid only for the episode it was fetched for — if the
    // link is changed to a different episode, it is looked up afresh.
    $stored = get_post_meta( $sermon_id, '_ss_spotify_image', true );
    if ( $stored && get_post_meta( $sermon_id, '_ss_spotify_image_src', true ) === $ref['id'] ) {
        return $stored;
    }

    static $cold_fetches = 0;
    $budget = (int) apply_filters( 'ss_spotify_thumb_fetch_budget', 3 );
    $key    = 'ss_sp_thumb_' . substr( md5( $ref['type'] . $ref['id'] ), 0, 20 );
    if ( get_transient( $key ) === false ) {
        if ( $cold_fetches >= $budget ) return '';
        $cold_fetches++;
    }

    $thumb = Sermon_Suite_Spotify_API::get_thumbnail( $ref );
    if ( $thumb ) {
        update_post_meta( $sermon_id, '_ss_spotify_image',     $thumb );
        update_post_meta( $sermon_id, '_ss_spotify_image_src', $ref['id'] );
    }
    return $thumb;
}

// ── Feed sync ─────────────────────────────────────────────────────────────────
//
// A church's Spotify show is one feed of every sermon, week after week, across
// every series — unlike a YouTube playlist, which usually is one series. So
// the show is set once, site-wide (Sermons → Settings), every episode comes in
// as a draft sermon with no series, and sermons are sorted into series
// afterwards from the All Sermons list (see admin/sermon-list.php).

const SS_SPOTIFY_OPT_SHOW = 'sermon_suite_spotify_show';
const SS_SPOTIFY_OPT_AUTO = 'sermon_suite_spotify_auto_sync';
const SS_SPOTIFY_OPT_LAST = 'sermon_suite_spotify_last_sync';
const SS_SPOTIFY_CRON     = 'ss_spotify_daily_sync';

/**
 * Import every episode of the show that isn't already a sermon.
 *
 * Returns [ 'created', 'skipped', 'errors', 'log' ] or a WP_Error. Episodes
 * are matched on their Spotify id, so re-running only adds what's new and
 * never touches a sermon someone has since edited or filed into a series.
 */
function ss_spotify_sync_feed( $show ) {
    $ref = ss_get_spotify_ref( $show );
    if ( ! $ref ) {
        return new WP_Error( 'spotify_bad_show', 'Could not read a Spotify show from: ' . $show );
    }
    if ( $ref['type'] !== 'show' ) {
        return new WP_Error( 'spotify_not_show', 'That looks like a single episode. Paste the show link (open.spotify.com/show/…) to sync the whole podcast.' );
    }

    $episodes = Sermon_Suite_Spotify_API::get_show_episodes( $ref['id'] );
    if ( is_wp_error( $episodes ) ) return $episodes;
    if ( empty( $episodes ) ) {
        return new WP_Error( 'spotify_empty', 'That show has no episodes Spotify will return.' );
    }

    $log = []; $created = 0; $skipped = 0; $errors = 0;

    foreach ( $episodes as $ep ) {
        $ep_id = $ep['id'] ?? '';
        if ( ! $ep_id ) continue;
        $title      = wp_strip_all_tags( $ep['name'] ?? '' );
        $title_html = esc_html( $title );
        $desc       = $ep['description'] ?? '';
        $pub_date   = substr( (string) ( $ep['release_date'] ?? '' ), 0, 10 );
        if ( ! $title ) continue;

        $existing = get_posts([
            'post_type'      => 'ss_sermon',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [[ 'key' => '_ss_spotify_synced', 'value' => $ep_id ]],
        ]);
        if ( $existing ) {
            $skipped++;
            continue;
        }

        $post_id = wp_insert_post([
            'post_type'    => 'ss_sermon',
            'post_title'   => $title,
            'post_content' => wp_kses_post( $desc ),
            'post_status'  => 'draft',
            'post_date'    => $pub_date ? $pub_date . ' 00:00:00' : current_time( 'mysql' ),
        ], true );
        if ( is_wp_error( $post_id ) ) {
            $log[] = "❌ Error creating: {$title_html} — " . esc_html( $post_id->get_error_message() );
            $errors++;
            continue;
        }

        update_post_meta( $post_id, '_ss_spotify_synced', $ep_id );
        update_post_meta( $post_id, '_ss_spotify_url',    'https://open.spotify.com/episode/' . $ep_id );
        if ( $pub_date ) update_post_meta( $post_id, '_ss_sermon_date', $pub_date );
        // The API response already carries the artwork; storing it means
        // synced sermons never need a lookup while a page renders.
        $image = ss_spotify_pick_image( $ep['images'] ?? [] );
        if ( $image ) {
            update_post_meta( $post_id, '_ss_spotify_image',     esc_url_raw( $image ) );
            update_post_meta( $post_id, '_ss_spotify_image_src', $ep_id );
        }

        $log[] = "✅ Created draft: <a href=\"" . esc_url( get_edit_post_link( $post_id ) ) . "\" target=\"_blank\">{$title_html}</a>"
               . ( $pub_date ? ' (' . esc_html( $pub_date ) . ')' : '' );
        $created++;
    }

    update_option( SS_SPOTIFY_OPT_LAST, current_time( 'mysql' ), false );

    return compact( 'created', 'skipped', 'errors', 'log' );
}

/** Sync Now, from the settings screen. */
add_action( 'wp_ajax_ss_spotify_sync_feed', 'ss_spotify_ajax_sync_feed' );
function ss_spotify_ajax_sync_feed() {
    check_ajax_referer( 'ss_spotify_sync', 'nonce' );
    // A site-wide setting that creates sermons in bulk: administrators only,
    // matching the settings screen it lives on.
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $show = sanitize_text_field( wp_unslash( $_POST['show'] ?? '' ) );
    if ( ! $show ) wp_send_json_error( 'Paste your Spotify show link first.' );
    // Save what was typed, so Sync Now works without a separate Save.
    update_option( SS_SPOTIFY_OPT_SHOW, $show );

    $result = ss_spotify_sync_feed( $show );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

    $summary = "{$result['created']} new sermon(s) imported as drafts, {$result['skipped']} already imported"
             . ( $result['errors'] ? ", {$result['errors']} error(s)" : '' ) . '.';
    if ( $result['created'] > 0 ) {
        $summary .= ' <a href="' . esc_url( admin_url( 'edit.php?post_type=ss_sermon&ss_series_filter=none' ) ) . '">Sort them into series →</a>';
    }
    wp_send_json_success( $result + [ 'summary' => $summary ] );
}

// ── Optional daily check ──────────────────────────────────────────────────────
// Off by default. When on, new episodes arrive as drafts on their own.

add_action( SS_SPOTIFY_CRON, 'ss_spotify_run_scheduled_sync' );
function ss_spotify_run_scheduled_sync() {
    $show = get_option( SS_SPOTIFY_OPT_SHOW, '' );
    if ( ! $show || get_option( SS_SPOTIFY_OPT_AUTO ) !== '1' ) return;
    ss_spotify_sync_feed( $show ); // failures are simply retried tomorrow
}

/** Keep the schedule in step with the setting. Called when settings save. */
function ss_spotify_update_schedule() {
    $want = get_option( SS_SPOTIFY_OPT_AUTO ) === '1' && get_option( SS_SPOTIFY_OPT_SHOW, '' ) !== '';
    $next = wp_next_scheduled( SS_SPOTIFY_CRON );
    if ( $want && ! $next ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', SS_SPOTIFY_CRON );
    } elseif ( ! $want && $next ) {
        wp_clear_scheduled_hook( SS_SPOTIFY_CRON );
    }
}

/**
 * One-time carry-over from 3.0.x, where the show link lived on a series: if
 * no site-wide show is set yet, adopt the one already entered on a series.
 */
add_action( 'admin_init', 'ss_spotify_migrate_series_show' );
function ss_spotify_migrate_series_show() {
    if ( get_option( SS_SPOTIFY_OPT_SHOW, '' ) !== '' || get_option( 'ss_spotify_show_migrated' ) ) return;
    update_option( 'ss_spotify_show_migrated', '1', false );
    $series = get_posts([
        'post_type'      => 'ss_series',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [[ 'key' => '_ss_series_spotify_show', 'value' => '', 'compare' => '!=' ]],
    ]);
    if ( $series ) {
        update_option( SS_SPOTIFY_OPT_SHOW, get_post_meta( $series[0], '_ss_series_spotify_show', true ) );
    }
}
