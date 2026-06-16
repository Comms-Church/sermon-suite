<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'sermon_suite_register_taxonomies' );

function sermon_suite_register_taxonomies() {

    // ── Topics ─────────────────────────────────────────────────────────────────
    register_taxonomy( 'ss_topic', [ 'ss_sermon', 'ss_series' ], [
        'labels' => [
            'name'          => 'Topics',
            'singular_name' => 'Topic',
            'add_new_item'  => 'Add New Topic',
            'edit_item'     => 'Edit Topic',
            'search_items'  => 'Search Topics',
        ],
        'public'            => true,
        'hierarchical'      => false,
        'rewrite'           => [ 'slug' => 'sermons/topic' ],
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);

    // ── Speakers ───────────────────────────────────────────────────────────────
    register_taxonomy( 'ss_speaker', [ 'ss_sermon' ], [
        'labels' => [
            'name'          => 'Speakers',
            'singular_name' => 'Speaker',
            'add_new_item'  => 'Add New Speaker',
            'edit_item'     => 'Edit Speaker',
            'search_items'  => 'Search Speakers',
        ],
        'public'            => true,
        'hierarchical'      => false,
        'rewrite'           => [ 'slug' => 'sermons/speaker' ],
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);

    // ── Scripture Books ────────────────────────────────────────────────────────
    register_taxonomy( 'ss_scripture_book', [ 'ss_sermon' ], [
        'labels' => [
            'name'          => 'Scripture Books',
            'singular_name' => 'Scripture Book',
            'add_new_item'  => 'Add New Book',
            'edit_item'     => 'Edit Book',
            'search_items'  => 'Search Books',
        ],
        'public'            => true,
        'hierarchical'      => true,
        'rewrite'           => [ 'slug' => 'sermons/book' ],
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);

    // ── Series Categories ──────────────────────────────────────────────────────
    register_taxonomy( 'ss_series_category', [ 'ss_series' ], [
        'labels' => [
            'name'              => 'Series Categories',
            'singular_name'     => 'Series Category',
            'add_new_item'      => 'Add New Category',
            'edit_item'         => 'Edit Category',
            'search_items'      => 'Search Categories',
            'all_items'         => 'All Categories',
            'parent_item'       => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
        ],
        'public'            => true,
        'hierarchical'      => true,
        'rewrite'           => [ 'slug' => 'sermons/category' ],
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);

    // ── Campus ────────────────────────────────────────────────────────────────
    register_taxonomy( 'ss_campus', [ 'ss_sermon', 'ss_series' ], [
        'labels' => [
            'name'          => 'Campuses',
            'singular_name' => 'Campus',
            'add_new_item'  => 'Add New Campus',
            'edit_item'     => 'Edit Campus',
            'search_items'  => 'Search Campuses',
            'all_items'     => 'All Campuses',
        ],
        'public'            => true,
        'hierarchical'      => false,
        'rewrite'           => [ 'slug' => 'sermons/campus' ],
        'show_in_rest'      => true,
        'show_admin_column' => true,
    ]);

}
