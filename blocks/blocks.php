<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'ss_register_gutenberg_blocks' );

function ss_register_gutenberg_blocks() {
    if ( ! function_exists( 'register_block_type' ) ) return;

    // Shared sermon data for the block editor
    $series_list = get_posts([
        'post_type'      => 'ss_series',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
    $sermon_list = get_posts([
        'post_type'      => 'ss_sermon',
        'posts_per_page' => 50,
        'post_status'    => 'publish',
        'meta_key'       => '_ss_sermon_date',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    ]);

    // Register editor script
    wp_register_script(
        'sermon-suite-blocks',
        SERMON_SUITE_URL . 'blocks/blocks.js',
        [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ],
        SERMON_SUITE_VERSION,
        true
    );

    $category_terms = get_terms(['taxonomy'=>'ss_series_category','hide_empty'=>false,'orderby'=>'name']);
    $categories_for_js = is_wp_error($category_terms) ? [] : array_map(fn($t) => [
        'value' => $t->slug,
        'label' => $t->name . ($t->count ? ' (' . $t->count . ')' : ''),
    ], $category_terms);

    wp_localize_script( 'sermon-suite-blocks', 'sermonSuiteBlocks', [
        'series' => array_map( fn($s) => [
            'value' => (string)$s->ID,
            'label' => $s->post_title,
        ], $series_list ),
        'sermons' => array_map( fn($s) => [
            'value' => (string)$s->ID,
            'label' => get_the_title($s) . ' (' . ss_format_sermon_date(get_post_meta($s->ID,'_ss_sermon_date',true)) . ')',
        ], $sermon_list ),
        'categories' => $categories_for_js,
        'icon' => SERMON_SUITE_URL . 'blocks/icon.svg',
    ]);

    // Register editor style
    wp_register_style(
        'sermon-suite-blocks-editor',
        SERMON_SUITE_URL . 'blocks/editor.css',
        [ 'wp-edit-blocks' ],
        SERMON_SUITE_VERSION
    );

    // ── Block: Sermon Archive ──────────────────────────────────────────────────
    register_block_type( 'sermon-suite/archive', [
        'editor_script'   => 'sermon-suite-blocks',
        'editor_style'    => 'sermon-suite-blocks-editor',
        'render_callback' => 'ss_block_render_archive',
        'attributes'      => [
            'layout'           => [ 'type' => 'string',  'default' => 'grid' ],
            'columns'          => [ 'type' => 'integer', 'default' => 3 ],
            'showFilter'       => [ 'type' => 'boolean', 'default' => true ],
            'featuredFirst'    => [ 'type' => 'boolean', 'default' => true ],
            'count'            => [ 'type' => 'integer', 'default' => -1 ],
            'sermonsPerSeries' => [ 'type' => 'integer', 'default' => 5 ],
            'category'         => [ 'type' => 'string',  'default' => '' ],
        ],
    ]);

    // ── Block: Latest Hero ─────────────────────────────────────────────────────
    register_block_type( 'sermon-suite/hero', [
        'editor_script'   => 'sermon-suite-blocks',
        'editor_style'    => 'sermon-suite-blocks-editor',
        'render_callback' => 'ss_block_render_hero',
        'attributes'      => [
            'label' => [ 'type' => 'string', 'default' => 'Latest Message' ],
        ],
    ]);

    // ── Block: Series Grid ─────────────────────────────────────────────────────
    register_block_type( 'sermon-suite/series-grid', [
        'editor_script'   => 'sermon-suite-blocks',
        'editor_style'    => 'sermon-suite-blocks-editor',
        'render_callback' => 'ss_block_render_series_grid',
        'attributes'      => [
            'columns'  => [ 'type' => 'integer', 'default' => 3 ],
            'count'    => [ 'type' => 'integer', 'default' => -1 ],
            'featured' => [ 'type' => 'boolean', 'default' => false ],
            'category' => [ 'type' => 'string',  'default' => '' ],
        ],
    ]);

    // ── Block: Sermon Player ───────────────────────────────────────────────────
    register_block_type( 'sermon-suite/player', [
        'editor_script'   => 'sermon-suite-blocks',
        'editor_style'    => 'sermon-suite-blocks-editor',
        'render_callback' => 'ss_block_render_player',
        'attributes'      => [
            'sermonId' => [ 'type' => 'integer', 'default' => 0 ],
        ],
    ]);

    // ── Block: Related Sermons ─────────────────────────────────────────────────
    register_block_type( 'sermon-suite/related', [
        'editor_script'   => 'sermon-suite-blocks',
        'editor_style'    => 'sermon-suite-blocks-editor',
        'render_callback' => 'ss_block_render_related',
        'attributes'      => [
            'sermonId' => [ 'type' => 'integer', 'default' => 0 ],
            'count'    => [ 'type' => 'integer', 'default' => 4 ],
        ],
    ]);

    register_block_type( 'sermon-suite/topics', [
        'editor_script'   => 'sermon-suite-blocks',
        'editor_style'    => 'sermon-suite-blocks-editor',
        'render_callback' => 'ss_block_render_topics',
        'attributes'      => [
            'columns'   => [ 'type' => 'integer', 'default' => 4 ],
            'minCount'  => [ 'type' => 'integer', 'default' => 1 ],
            'showCount' => [ 'type' => 'boolean', 'default' => true ],
            'orderby'   => [ 'type' => 'string',  'default' => 'count' ],
        ],
    ]);
}

// ── Block render callbacks (delegate to shortcodes) ───────────────────────────

function ss_block_render_archive( $attrs ) {
    $layout  = $attrs['layout']           ?? 'grid';
    $cols    = $attrs['columns']          ?? 3;
    $filter  = ($attrs['showFilter']      ?? true)  ? 'true' : 'false';
    $feat    = ($attrs['featuredFirst']   ?? true)  ? 'true' : 'false';
    $count   = $attrs['count']            ?? -1;
    $sps      = $attrs['sermonsPerSeries'] ?? 5;
    $category = esc_attr($attrs['category'] ?? '');
    return do_shortcode( "[ss_sermon_archive layout=\"{$layout}\" columns=\"{$cols}\" show_filter=\"{$filter}\" featured_first=\"{$feat}\" count=\"{$count}\" sermons_per_series=\"{$sps}\" category=\"{$category}\"]" );
}

function ss_block_render_hero( $attrs ) {
    $label = esc_attr($attrs['label'] ?? 'Latest Message');
    return do_shortcode( "[ss_latest_hero label=\"{$label}\"]" );
}

function ss_block_render_series_grid( $attrs ) {
    $cols  = $attrs['columns']  ?? 3;
    $count = $attrs['count']    ?? -1;
    $feat     = ($attrs['featured'] ?? false) ? 'true' : 'false';
    $category = esc_attr($attrs['category'] ?? '');
    return do_shortcode( "[ss_series_grid columns=\"{$cols}\" count=\"{$count}\" featured=\"{$feat}\" category=\"{$category}\"]"  );
}

function ss_block_render_player( $attrs ) {
    $id = (int)($attrs['sermonId'] ?? 0);
    if ( ! $id ) return '<p style="padding:16px;background:#f8f9fa;border-radius:6px;color:#666;">Select a sermon in the block settings →</p>';
    return do_shortcode( "[ss_sermon_player id=\"{$id}\"]" );
}

function ss_block_render_related( $attrs ) {
    $id    = (int)($attrs['sermonId'] ?? 0);
    $count = (int)($attrs['count']    ?? 4);
    if ( ! $id ) return '<p style="padding:16px;background:#f8f9fa;border-radius:6px;color:#666;">Select a sermon in the block settings →</p>';
    return do_shortcode( "[ss_related_sermons id=\"{$id}\" count=\"{$count}\"]" );
}

function ss_block_render_topics( $attrs ) {
    $cols       = (int)($attrs['columns']  ?? 4);
    $min        = (int)($attrs['minCount'] ?? 1);
    $show_count = ($attrs['showCount'] ?? true) ? 'true' : 'false';
    $orderby    = in_array(($attrs['orderby'] ?? 'count'), ['count','name'], true) ? $attrs['orderby'] : 'count';
    return do_shortcode( "[ss_topics columns=\"{$cols}\" min_count=\"{$min}\" show_count=\"{$show_count}\" orderby=\"{$orderby}\"]" );
}
