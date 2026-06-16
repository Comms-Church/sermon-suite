<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Extract YouTube video ID from a URL or bare ID string.
 */
function ss_get_youtube_id( $value ) {
    if ( empty($value) ) return '';
    // Already a bare ID (11 chars, alphanumeric + _ -)
    if ( preg_match('/^[a-zA-Z0-9_\-]{11}$/', trim($value)) ) {
        return trim($value);
    }
    // youtu.be/ID
    if ( preg_match('/youtu\.be\/([a-zA-Z0-9_\-]{11})/', $value, $m) ) return $m[1];
    // youtube.com/watch?v=ID or /embed/ID
    if ( preg_match('/[?&\/](?:v=|embed\/)([a-zA-Z0-9_\-]{11})/', $value, $m) ) return $m[1];
    return '';
}

/**
 * Return YouTube thumbnail URL for a given video ID.
 */
function ss_youtube_thumb( $video_id, $size = 'hqdefault' ) {
    if ( ! $video_id ) return '';
    return "https://img.youtube.com/vi/{$video_id}/{$size}.jpg";
}

/**
 * Build a Bible Gateway URL for a scripture reference.
 */
function ss_bible_gateway_url( $ref, $version = 'NIV' ) {
    if ( ! $ref ) return '';
    return 'https://www.biblegateway.com/passage/?search=' . urlencode($ref) . '&version=' . $version;
}

/**
 * Get all sermons belonging to a series, sorted by series order then date.
 */
function ss_get_series_sermons( $series_id ) {
    $args = [
        'post_type'      => 'ss_sermon',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [
                'key'   => '_ss_series_id',
                'value' => $series_id,
            ],
        ],
        'meta_key' => '_ss_series_order',
        'orderby'  => 'meta_value_num',
        'order'    => 'ASC',
    ];
    return get_posts($args);
}

/**
 * Get all series ordered by start date descending.
 */
function ss_get_all_series( $args = [] ) {
    $defaults = [
        'post_type'      => 'ss_series',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => '_ss_series_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    ];
    return get_posts( wp_parse_args($args, $defaults) );
}

/**
 * Format a sermon date for display.
 */
function ss_format_sermon_date( $date_str ) {
    if ( ! $date_str || $date_str === '0000-00-00' ) return '';
    $ts = strtotime($date_str);
    if ( ! $ts ) return '';
    return date_i18n( get_option('date_format'), $ts );
}

/**
 * Get the display image URL for a series (falls back to featured image).
 */
function ss_get_series_image( $series_id, $size = 'lg' ) {
    $meta_key = $size === 'sm' ? '_ss_series_image_sm' : '_ss_series_image_lg';
    $url = get_post_meta( $series_id, $meta_key, true );
    if ( $url ) return esc_url($url);
    // Fallback to WP featured image
    if ( has_post_thumbnail($series_id) ) {
        $img = wp_get_attachment_image_src( get_post_thumbnail_id($series_id), 'large' );
        if ( $img ) return esc_url($img[0]);
    }
    return '';
}

/**
 * Get resources for a sermon post.
 */
function ss_get_sermon_resources( $sermon_id ) {
    $r = get_post_meta( $sermon_id, '_ss_resources', true );
    return is_array($r) ? $r : [];
}

/**
 * Icon for a resource type.
 */
function ss_resource_icon( $type ) {
    $icons = [
        'pdf'        => '📄',
        'devotional' => '📖',
        'notes'      => '📝',
        'link'       => '🔗',
    ];
    return $icons[$type] ?? '🔗';
}

/**
 * Returns the URL of the designated Sermons page.
 * Falls back to /sermons if no page is set.
 */
function sermon_suite_archive_url() {
    $page_id = (int) get_option('sermon_suite_page_id', 0);
    if ( $page_id ) {
        $url = get_permalink($page_id);
        if ( $url ) return $url;
    }
    // Fallback: look for a page with slug 'sermons'
    $page = get_page_by_path('sermons');
    if ( $page ) return get_permalink($page->ID);
    return home_url('/sermons/');
}
