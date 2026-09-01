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

/**
 * Render a rich-text sermon field (notes, discussion guide, transcript).
 *
 * Sermon Shots returns these as Markdown, and staff often paste Markdown in
 * by hand, but older guides were written as HTML. Markdown is converted;
 * anything that already contains block-level HTML is passed through as-is.
 */
function ss_render_rich_text( $text ) {
    $text = (string) $text;
    if ( trim($text) === '' ) return '';

    // Already HTML (older guides, or pasted from a rich editor) — leave it be.
    if ( preg_match('/<(p|div|ul|ol|li|h[1-6]|blockquote|table|br)\b[^>]*>/i', $text) ) {
        return wp_kses_post( wpautop( $text ) );
    }

    return wp_kses_post( ss_markdown_to_html( $text ) );
}

/**
 * Inline Markdown: links, bold, italic, code.
 */
function ss_markdown_inline( $text ) {
    // [label](url) — before emphasis, so underscores in URLs survive.
    $text = preg_replace_callback(
        '/\[([^\]]+)\]\(([^)\s]+)\)/',
        function( $m ) {
            return '<a href="' . esc_url($m[2]) . '" rel="noopener">' . $m[1] . '</a>';
        },
        $text
    );
    $text = preg_replace('/`([^`]+)`/',                            '<code>$1</code>',   $text);
    $text = preg_replace('/\*\*(.+?)\*\*/s',                       '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/s',                           '<strong>$1</strong>', $text);
    // Single * or _ emphasis, but not mid-word (snake_case) or stray asterisks.
    $text = preg_replace('/(?<!\*)\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/', '<em>$1</em>',   $text);
    $text = preg_replace('/(?<![\w_])_(?!\s)([^_\n]+?)(?<!\s)_(?![\w_])/', '<em>$1</em>', $text);
    return $text;
}

/**
 * Block-level Markdown → HTML. Covers the subset Sermon Shots emits:
 * headings, ordered/unordered lists, blockquotes, rules, paragraphs.
 */
function ss_markdown_to_html( $text ) {
    $lines = explode( "\n", str_replace( ["\r\n", "\r"], "\n", $text ) );

    $html  = '';
    $list  = '';   // 'ul' | 'ol' while inside a list
    $para  = [];   // buffered paragraph lines
    $quote = [];   // buffered blockquote lines

    $close_list = function() use ( &$html, &$list ) {
        if ( $list ) { $html .= "</{$list}>"; $list = ''; }
    };
    $flush_para = function() use ( &$html, &$para ) {
        if ( $para ) {
            $html .= '<p>' . ss_markdown_inline( implode(' ', $para) ) . '</p>';
            $para = [];
        }
    };
    $flush_quote = function() use ( &$html, &$quote ) {
        if ( $quote ) {
            $html .= '<blockquote><p>' . ss_markdown_inline( implode(' ', $quote) ) . '</p></blockquote>';
            $quote = [];
        }
    };

    foreach ( $lines as $line ) {
        $trimmed = trim( $line );

        // Blank line ends any open paragraph or quote (but not a list —
        // Markdown allows a blank line between list items).
        if ( $trimmed === '' ) {
            $flush_para(); $flush_quote();
            continue;
        }

        // Heading: # → h2 … #### → h5, capped at h6.
        if ( preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m) ) {
            $flush_para(); $flush_quote(); $close_list();
            $tag   = 'h' . min( 6, strlen($m[1]) + 1 );
            $html .= "<{$tag}>" . ss_markdown_inline( trim($m[2], " #") ) . "</{$tag}>";
            continue;
        }

        // Horizontal rule
        if ( preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trimmed) ) {
            $flush_para(); $flush_quote(); $close_list();
            $html .= '<hr>';
            continue;
        }

        // Blockquote
        if ( preg_match('/^>\s?(.*)$/', $trimmed, $m) ) {
            $flush_para(); $close_list();
            $quote[] = $m[1];
            continue;
        }

        // Unordered list item
        if ( preg_match('/^[-*+]\s+(.*)$/', $trimmed, $m) ) {
            $flush_para(); $flush_quote();
            if ( $list !== 'ul' ) { $close_list(); $html .= '<ul>'; $list = 'ul'; }
            $html .= '<li>' . ss_markdown_inline( $m[1] ) . '</li>';
            continue;
        }

        // Ordered list item
        if ( preg_match('/^\d+[.)]\s+(.*)$/', $trimmed, $m) ) {
            $flush_para(); $flush_quote();
            if ( $list !== 'ol' ) { $close_list(); $html .= '<ol>'; $list = 'ol'; }
            $html .= '<li>' . ss_markdown_inline( $m[1] ) . '</li>';
            continue;
        }

        // Continuation of a list item wraps into that item; otherwise prose.
        $flush_quote();
        if ( $list ) {
            $html = preg_replace('/<\/li>$/', ' ' . ss_markdown_inline($trimmed) . '</li>', $html, 1);
            continue;
        }
        $para[] = $trimmed;
    }

    $flush_para(); $flush_quote(); $close_list();
    return $html;
}
