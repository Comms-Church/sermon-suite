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
        $resp = wp_remote_get( $url, [ 'timeout' => 8 ] );

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
 * Artwork for a sermon's Spotify episode, or '' if it has none.
 * Mirrors ss_youtube_thumb() so callers can fall back the same way.
 */
function ss_spotify_thumb( $sermon_id ) {
    $ref = ss_get_spotify_ref( get_post_meta( $sermon_id, '_ss_spotify_url', true ) );
    if ( ! $ref ) return '';
    return Sermon_Suite_Spotify_API::get_thumbnail( $ref );
}

// ── Show sync (AJAX) ──────────────────────────────────────────────────────────
add_action( 'wp_ajax_ss_spotify_sync_show', 'ss_spotify_handle_sync' );

function ss_spotify_handle_sync() {
    check_ajax_referer( 'ss_spotify_sync', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

    $series_id = absint( $_POST['series_id'] ?? 0 );
    $show      = sanitize_text_field( $_POST['show'] ?? '' );

    if ( ! $series_id ) wp_send_json_error( 'Missing series ID' );
    if ( ! $show )      wp_send_json_error( 'Missing show link' );

    $ref = ss_get_spotify_ref( $show );
    if ( ! $ref ) {
        wp_send_json_error( 'Could not read a Spotify show from: ' . $show );
    }
    if ( $ref['type'] !== 'show' ) {
        wp_send_json_error( 'That looks like a single episode. Paste the show link (open.spotify.com/show/…) to sync a whole podcast.' );
    }

    update_post_meta( $series_id, '_ss_series_spotify_show', $show );

    $episodes = Sermon_Suite_Spotify_API::get_show_episodes( $ref['id'] );
    if ( is_wp_error( $episodes ) ) wp_send_json_error( $episodes->get_error_message() );
    if ( empty( $episodes ) )       wp_send_json_error( 'That show has no episodes Spotify will return.' );

    $log = []; $created = 0; $skipped = 0; $errors = 0;

    foreach ( $episodes as $ep ) {
        $ep_id = $ep['id'] ?? '';
        if ( ! $ep_id ) continue;
        $title    = wp_strip_all_tags( $ep['name'] ?? '' );
        $desc     = $ep['description'] ?? '';
        $pub_date = substr( (string) ( $ep['release_date'] ?? '' ), 0, 10 );
        if ( ! $title ) continue;

        // Same dedupe shape as the YouTube sync: the source id is recorded on
        // the post, so re-syncing adds only what's new and never touches a
        // sermon someone has since edited.
        $existing = get_posts([
            'post_type'      => 'ss_sermon',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [[ 'key' => '_ss_spotify_synced', 'value' => $ep_id ]],
        ]);
        if ( ! empty( $existing ) ) {
            $log[] = "↩ Already synced: {$title}";
            $skipped++;
            continue;
        }

        $post_id = wp_insert_post([
            'post_type'    => 'ss_sermon',
            'post_title'   => $title,
            'post_content' => wp_kses_post( $desc ),
            'post_status'  => 'draft',
            'post_date'    => $pub_date ? $pub_date . ' 00:00:00' : current_time( 'mysql' ),
        ]);
        if ( is_wp_error( $post_id ) ) {
            $log[] = "❌ Error creating: {$title} — " . $post_id->get_error_message();
            $errors++;
            continue;
        }

        update_post_meta( $post_id, '_ss_spotify_synced', $ep_id );
        update_post_meta( $post_id, '_ss_spotify_url',    'https://open.spotify.com/episode/' . $ep_id );
        update_post_meta( $post_id, '_ss_series_id',      $series_id );
        if ( $pub_date ) update_post_meta( $post_id, '_ss_sermon_date', $pub_date );

        $default_speaker = get_post_meta( $series_id, '_ss_series_default_speaker', true );
        if ( $default_speaker ) wp_set_post_terms( $post_id, [ $default_speaker ], 'ss_speaker' );

        $log[] = "✅ Created draft: <a href=\"" . get_edit_post_link( $post_id ) . "\" target=\"_blank\">{$title}</a>" . ( $pub_date ? " ({$pub_date})" : '' );
        $created++;
    }

    // Number the series chronologically, leaving any existing order alone.
    $all = get_posts([
        'post_type'      => 'ss_sermon',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [[ 'key' => '_ss_series_id', 'value' => $series_id ]],
        'orderby'        => 'date',
        'order'          => 'ASC',
    ]);
    foreach ( $all as $i => $s ) {
        if ( ! get_post_meta( $s->ID, '_ss_series_order', true ) ) {
            update_post_meta( $s->ID, '_ss_series_order', $i + 1 );
        }
    }

    update_post_meta( $series_id, '_ss_series_spotify_last_sync', current_time( 'mysql' ) );

    $summary = "{$created} new sermon(s) created as drafts, {$skipped} already existed, {$errors} error(s).";
    if ( $created > 0 ) {
        $summary .= ' <a href="' . admin_url( 'edit.php?post_type=ss_sermon&post_status=draft' ) . '">View drafts →</a>';
    }

    wp_send_json_success([
        'log'     => $log,
        'summary' => $summary,
        'created' => $created,
        'skipped' => $skipped,
        'errors'  => $errors,
    ]);
}
