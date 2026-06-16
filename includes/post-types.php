<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'sermon_suite_register_post_types' );

function sermon_suite_register_post_types() {

    // ── Sermon Series ─────────────────────────────────────────────────────────
    register_post_type( 'ss_series', [
        'labels' => [
            'name'               => 'Sermon Series',
            'singular_name'      => 'Series',
            'add_new'            => 'Add New Series',
            'add_new_item'       => 'Add New Series',
            'edit_item'          => 'Edit Series',
            'new_item'           => 'New Series',
            'view_item'          => 'View Series',
            'search_items'       => 'Search Series',
            'not_found'          => 'No series found',
            'not_found_in_trash' => 'No series in trash',
        ],
        'public'             => true,
        'has_archive'        => false,
        'rewrite'            => [ 'slug' => 'sermons/series', 'with_front' => false ],
        'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ],
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-book-alt',
        'show_in_menu'       => false,
    ]);

    // ── Individual Sermon / Message ────────────────────────────────────────────
    register_post_type( 'ss_sermon', [
        'labels' => [
            'name'               => 'Sermons',
            'singular_name'      => 'Sermon',
            'add_new'            => 'Add New Sermon',
            'add_new_item'       => 'Add New Sermon',
            'edit_item'          => 'Edit Sermon',
            'new_item'           => 'New Sermon',
            'view_item'          => 'View Sermon',
            'search_items'       => 'Search Sermons',
            'not_found'          => 'No sermons found',
            'not_found_in_trash' => 'No sermons in trash',
        ],
        'public'             => true,
        'has_archive'        => false,
        'rewrite'            => [ 'slug' => 'sermons', 'with_front' => false ],
        'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ],
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-video-alt3',
        'show_in_menu'       => false,
    ]);
}
