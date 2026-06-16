<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_ss_yt_sync_playlist', 'ss_yt_handle_sync' );

function ss_yt_handle_sync() {
    check_ajax_referer( 'ss_yt_sync', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error('Unauthorized');

    $series_id = absint( $_POST['series_id'] ?? 0 );
    $playlist  = sanitize_text_field( $_POST['playlist'] ?? '' );
    $api_key   = get_option('sermon_suite_yt_api_key', '');

    if ( ! $series_id ) wp_send_json_error('Missing series ID');
    if ( ! $playlist )  wp_send_json_error('Missing playlist');
    if ( ! $api_key )   wp_send_json_error('No YouTube API key configured. Add one under Sermons → Settings.');

    $playlist_id = ss_extract_playlist_id($playlist);
    if ( ! $playlist_id ) wp_send_json_error('Could not parse a playlist ID from: ' . $playlist);

    // Save playlist back to meta (in case it was freshly typed)
    update_post_meta( $series_id, '_ss_series_yt_playlist', $playlist );

    // Fetch all videos from the playlist (paginated)
    $videos = ss_fetch_playlist_videos($playlist_id, $api_key);
    if ( is_wp_error($videos) ) wp_send_json_error($videos->get_error_message());
    if ( empty($videos) )       wp_send_json_error('Playlist appears to be empty or private.');

    $log      = [];
    $created  = 0;
    $skipped  = 0;
    $errors   = 0;

    foreach ( $videos as $video ) {
        $video_id = $video['id'];
        $title    = $video['title'];
        $desc     = $video['description'];
        $pub_date = substr($video['publishedAt'], 0, 10); // YYYY-MM-DD

        // Check if a sermon with this video ID already exists
        $existing = get_posts([
            'post_type'      => 'ss_sermon',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [[
                'key'   => '_ss_yt_synced',
                'value' => $video_id,
            ]],
        ]);

        if ( ! empty($existing) ) {
            $log[] = "↩ Already synced: {$title}";
            $skipped++;
            continue;
        }

        // Create sermon draft
        $post_id = wp_insert_post([
            'post_type'    => 'ss_sermon',
            'post_title'   => wp_strip_all_tags($title),
            'post_content' => wp_kses_post($desc),
            'post_status'  => 'draft',
            'post_date'    => $pub_date . ' 00:00:00',
        ]);

        if ( is_wp_error($post_id) ) {
            $log[]  = "❌ Error creating: {$title} — " . $post_id->get_error_message();
            $errors++;
            continue;
        }

        // Set meta
        update_post_meta( $post_id, '_ss_yt_synced',    $video_id );
        update_post_meta( $post_id, '_ss_youtube_id',   $video_id );
        update_post_meta( $post_id, '_ss_series_id',    $series_id );
        update_post_meta( $post_id, '_ss_sermon_date',  $pub_date );

        // Assign speaker taxonomy from series if a default speaker is set
        $default_speaker = get_post_meta($series_id, '_ss_series_default_speaker', true);
        if ($default_speaker) {
            wp_set_post_terms($post_id, [$default_speaker], 'ss_speaker');
        }

        $log[]  = "✅ Created draft: <a href=\"" . get_edit_post_link($post_id) . "\" target=\"_blank\">{$title}</a> ({$pub_date})";
        $created++;
    }

    // Auto-number series order for newly created sermons that don't have one yet
    $all_series_sermons = get_posts([
        'post_type'      => 'ss_sermon',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'   => '_ss_series_id',
            'value' => $series_id,
        ]],
        'orderby'        => 'date',
        'order'          => 'ASC',
    ]);
    foreach ( $all_series_sermons as $i => $s ) {
        if ( ! get_post_meta($s->ID, '_ss_series_order', true) ) {
            update_post_meta( $s->ID, '_ss_series_order', $i + 1 );
        }
    }

    // Record sync timestamp
    update_post_meta( $series_id, '_ss_series_yt_last_sync', current_time('mysql') );

    $summary = "{$created} new sermon(s) created as drafts, {$skipped} already existed, {$errors} error(s).";
    if ($created > 0) {
        $summary .= ' <a href="' . admin_url('edit.php?post_type=ss_sermon&post_status=draft') . '">View drafts →</a>';
    }

    wp_send_json_success([
        'log'     => $log,
        'summary' => $summary,
        'created' => $created,
        'skipped' => $skipped,
        'errors'  => $errors,
    ]);
}

/**
 * Extract YouTube playlist ID from a URL or bare ID.
 */
function ss_extract_playlist_id( $value ) {
    $value = trim($value);
    // Already a bare playlist ID (PL...)
    if ( preg_match('/^(PL|UU|FL|RD)[a-zA-Z0-9_\-]{10,}$/', $value) ) {
        return $value;
    }
    // URL with list= param
    if ( preg_match('/[?&]list=([a-zA-Z0-9_\-]+)/', $value, $m) ) {
        return $m[1];
    }
    return false;
}

/**
 * Fetch all videos from a YouTube playlist via the Data API v3.
 * Returns array of [ id, title, description, publishedAt ] or WP_Error.
 */
function ss_fetch_playlist_videos( $playlist_id, $api_key ) {
    $videos    = [];
    $page_token = '';
    $max_pages  = 10; // Safety cap — 500 videos max
    $page       = 0;

    do {
        $url = add_query_arg([
            'part'       => 'snippet',
            'playlistId' => $playlist_id,
            'maxResults' => 50,
            'key'        => $api_key,
            'pageToken'  => $page_token,
        ], 'https://www.googleapis.com/youtube/v3/playlistItems');

        $response = wp_remote_get($url, ['timeout' => 15]);

        if ( is_wp_error($response) ) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode( wp_remote_retrieve_body($response), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP {$code}";
            return new WP_Error('yt_api_error', "YouTube API error: {$msg}");
        }

        foreach ( ($body['items'] ?? []) as $item ) {
            $snippet  = $item['snippet'] ?? [];
            $video_id = $snippet['resourceId']['videoId'] ?? '';
            if ( ! $video_id || $snippet['title'] === 'Private video' || $snippet['title'] === 'Deleted video' ) continue;
            $videos[] = [
                'id'          => $video_id,
                'title'       => $snippet['title']       ?? 'Untitled',
                'description' => $snippet['description'] ?? '',
                'publishedAt' => $snippet['publishedAt'] ?? '',
            ];
        }

        $page_token = $body['nextPageToken'] ?? '';
        $page++;

    } while ( $page_token && $page < $max_pages );

    return $videos;
}
