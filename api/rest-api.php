<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'sermon_suite_register_rest_routes' );

function sermon_suite_register_rest_routes() {
    $ns = 'sermon-suite/v1';

    // GET /sermon-suite/v1/series
    register_rest_route( $ns, '/series', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_series',
        'permission_callback' => '__return_true',
        'args' => [
            'per_page' => [ 'default' => 20,  'sanitize_callback' => 'absint' ],
            'page'     => [ 'default' => 1,   'sanitize_callback' => 'absint' ],
            'topic'    => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
            'featured' => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
            'category' => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
            'campus'   => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ]);

    // GET /sermon-suite/v1/series/{id}
    register_rest_route( $ns, '/series/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_single_series',
        'permission_callback' => '__return_true',
    ]);

    // GET /sermon-suite/v1/sermons
    register_rest_route( $ns, '/sermons', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_sermons',
        'permission_callback' => '__return_true',
        'args' => [
            'per_page'  => [ 'default' => 20,  'sanitize_callback' => 'absint' ],
            'page'      => [ 'default' => 1,   'sanitize_callback' => 'absint' ],
            'series_id' => [ 'default' => 0,   'sanitize_callback' => 'absint' ],
            'topic'     => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
            'speaker'   => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
            'campus'    => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
            'search'    => [ 'default' => '',   'sanitize_callback' => 'sanitize_text_field' ],
            'year'      => [ 'default' => 0,   'sanitize_callback' => 'absint' ],
            'orderby'   => [ 'default' => 'date', 'sanitize_callback' => 'sanitize_key' ],
            'order'     => [ 'default' => 'DESC', 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ]);

    // GET /sermon-suite/v1/sermons/{id}
    register_rest_route( $ns, '/sermons/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_single_sermon',
        'permission_callback' => '__return_true',
    ]);

    // GET /sermon-suite/v1/topics
    register_rest_route( $ns, '/topics', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_topics',
        'permission_callback' => '__return_true',
    ]);

    // GET /sermon-suite/v1/speakers
    // GET /sermon-suite/v1/categories
    register_rest_route( $ns, '/campuses', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_campuses',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route( $ns, '/categories', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_categories',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route( $ns, '/speakers', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'ss_rest_get_speakers',
        'permission_callback' => '__return_true',
    ]);
}

// ── Series endpoints ───────────────────────────────────────────────────────────

function ss_rest_get_series( $request ) {
    $args = [
        'post_type'      => 'ss_series',
        'post_status'    => 'publish',
        'posts_per_page' => $request['per_page'],
        'paged'          => $request['page'],
        'meta_key'       => '_ss_series_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    ];

    $tax_query = [];
    if ( $request['topic'] ) {
        $tax_query[] = [
            'taxonomy' => 'ss_topic',
            'field'    => 'slug',
            'terms'    => $request['topic'],
        ];
    }
    if ( $request['category'] ) {
        $tax_query[] = [
            'taxonomy' => 'ss_series_category',
            'field'    => 'slug',
            'terms'    => $request['category'],
        ];
    }
    if ( $request['campus'] ) {
        $tax_query[] = [
            'taxonomy' => 'ss_campus',
            'field'    => 'slug',
            'terms'    => $request['campus'],
        ];
    }
    if ( $tax_query ) {
        $args['tax_query'] = array_merge( ['relation' => 'AND'], $tax_query );
    }

    if ( $request['featured'] === '1' || $request['featured'] === 'true' ) {
        $args['meta_query'] = [[
            'key'   => '_ss_series_featured',
            'value' => '1',
        ]];
    }

    $query = new WP_Query($args);
    $data  = [];

    foreach ( $query->posts as $series ) {
        $data[] = ss_format_series_for_api($series);
    }

    $response = new WP_REST_Response($data, 200);
    $response->header('X-WP-Total',     $query->found_posts);
    $response->header('X-WP-TotalPages', $query->max_num_pages);
    return $response;
}

function ss_rest_get_single_series( $request ) {
    $post = get_post( (int) $request['id'] );
    if ( ! $post || $post->post_type !== 'ss_series' ) {
        return new WP_Error('not_found', 'Series not found', ['status' => 404]);
    }
    $data = ss_format_series_for_api($post);
    // Include sermons
    $data['sermons'] = array_map( 'ss_format_sermon_for_api', ss_get_series_sermons($post->ID) );
    return rest_ensure_response($data);
}

function ss_format_series_for_api( $post ) {
    $sermon_count = count( ss_get_series_sermons($post->ID) );
    return [
        'id'          => $post->ID,
        'title'       => get_the_title($post),
        'slug'        => $post->post_name,
        'description' => get_the_excerpt($post) ?: wp_trim_words(strip_tags($post->post_content), 40),
        'image_sm'    => ss_get_series_image($post->ID, 'sm'),
        'image_lg'    => ss_get_series_image($post->ID, 'lg'),
        'start_date'  => get_post_meta($post->ID, '_ss_series_start_date', true),
        'end_date'    => get_post_meta($post->ID, '_ss_series_end_date',   true),
        'featured'    => (bool) get_post_meta($post->ID, '_ss_series_featured', true),
        'sermon_count'=> $sermon_count,
        'topics'      => wp_get_post_terms($post->ID, 'ss_topic', ['fields' => 'names']),
        'categories'  => array_map(function($t){ return ['id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug]; }, wp_get_post_terms($post->ID, 'ss_series_category')),
        'permalink'   => get_permalink($post),
    ];
}

// ── Sermon endpoints ───────────────────────────────────────────────────────────

function ss_rest_get_sermons( $request ) {
    $args = [
        'post_type'      => 'ss_sermon',
        'post_status'    => 'publish',
        'posts_per_page' => $request['per_page'],
        'paged'          => $request['page'],
        'orderby'        => $request['orderby'] === 'date' ? 'meta_value' : $request['orderby'],
        'order'          => strtoupper($request['order']) === 'ASC' ? 'ASC' : 'DESC',
    ];

    if ( $request['orderby'] === 'date' ) {
        $args['meta_key'] = '_ss_sermon_date';
    }

    $meta_query = [];
    if ( $request['series_id'] ) {
        $meta_query[] = [
            'key'   => '_ss_series_id',
            'value' => $request['series_id'],
        ];
    }
    if ( $meta_query ) $args['meta_query'] = $meta_query;

    $tax_query = [];
    if ( $request['topic'] ) {
        $tax_query[] = [
            'taxonomy' => 'ss_topic',
            'field'    => 'slug',
            'terms'    => $request['topic'],
        ];
    }
    if ( $request['speaker'] ) {
        $tax_query[] = [
            'taxonomy' => 'ss_speaker',
            'field'    => 'slug',
            'terms'    => $request['speaker'],
        ];
    }
    if ( $request['campus'] ) {
        $tax_query[] = [
            'taxonomy' => 'ss_campus',
            'field'    => 'slug',
            'terms'    => $request['campus'],
        ];
    }
    if ( $tax_query ) $args['tax_query'] = array_merge(['relation'=>'AND'], $tax_query);

    if ( $request['search'] ) {
        $args['s'] = $request['search'];
    }

    if ( $request['year'] ) {
        $args['date_query'] = [[
            'year' => $request['year'],
        ]];
    }

    $query = new WP_Query($args);
    $data  = array_map('ss_format_sermon_for_api', $query->posts);

    $response = new WP_REST_Response($data, 200);
    $response->header('X-WP-Total',      $query->found_posts);
    $response->header('X-WP-TotalPages', $query->max_num_pages);
    return $response;
}

function ss_rest_get_single_sermon( $request ) {
    $post = get_post( (int) $request['id'] );
    if ( ! $post || $post->post_type !== 'ss_sermon' ) {
        return new WP_Error('not_found', 'Sermon not found', ['status' => 404]);
    }
    return rest_ensure_response( ss_format_sermon_for_api($post) );
}

function ss_format_sermon_for_api( $post ) {
    $youtube_raw = get_post_meta($post->ID, '_ss_youtube_id', true);
    $youtube_id  = ss_get_youtube_id($youtube_raw);
    $series_id   = (int) get_post_meta($post->ID, '_ss_series_id', true);
    $series_title = $series_id ? get_the_title($series_id) : '';

    return [
        'id'            => $post->ID,
        'title'         => get_the_title($post),
        'slug'          => $post->post_name,
        'description'   => get_the_excerpt($post) ?: wp_trim_words(strip_tags($post->post_content), 50),
        'date'          => get_post_meta($post->ID, '_ss_sermon_date', true),
        'date_formatted'=> ss_format_sermon_date(get_post_meta($post->ID, '_ss_sermon_date', true)),
        'series_id'     => $series_id,
        'series_title'  => $series_title,
        'series_order'  => (int) get_post_meta($post->ID, '_ss_series_order', true),
        'youtube_id'    => $youtube_id,
        'youtube_embed' => $youtube_id ? "https://www.youtube.com/embed/{$youtube_id}" : '',
        'youtube_url'   => $youtube_id ? "https://youtu.be/{$youtube_id}" : '',
        'thumbnail'     => $youtube_id ? ss_youtube_thumb($youtube_id) : '',
        'scripture_ref' => get_post_meta($post->ID, '_ss_scripture_ref', true),
        'scripture_url' => get_post_meta($post->ID, '_ss_scripture_url', true),
        'topics'        => wp_get_post_terms($post->ID, 'ss_topic',   ['fields' => 'names']),
        'speakers'      => wp_get_post_terms($post->ID, 'ss_speaker', ['fields' => 'names']),
        'campuses'      => wp_get_post_terms($post->ID, 'ss_campus',  ['fields' => 'names']),
        'resources'     => ss_get_sermon_resources($post->ID),
        'permalink'     => get_permalink($post),
    ];
}

// ── Taxonomy endpoints ─────────────────────────────────────────────────────────

function ss_rest_get_topics( $request ) {
    $terms = get_terms([
        'taxonomy'   => 'ss_topic',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    if ( is_wp_error($terms) ) return $terms;
    return rest_ensure_response( array_map(function($t) {
        return [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count ];
    }, $terms));
}

function ss_rest_get_campuses( $request ) {
    $terms = get_terms(['taxonomy'=>'ss_campus','hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
    if ( is_wp_error($terms) ) return $terms;
    return rest_ensure_response( array_map(function($t) {
        return ['id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug,'count'=>$t->count];
    }, $terms));
}

function ss_rest_get_categories( $request ) {
    $terms = get_terms([
        'taxonomy'   => 'ss_series_category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    if ( is_wp_error($terms) ) return $terms;
    return rest_ensure_response( array_map(function($t) {
        return [
            'id'          => $t->term_id,
            'name'        => $t->name,
            'slug'        => $t->slug,
            'description' => $t->description,
            'count'       => $t->count,
            'parent'      => $t->parent,
        ];
    }, $terms));
}

function ss_rest_get_speakers( $request ) {
    $terms = get_terms([
        'taxonomy'   => 'ss_speaker',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    if ( is_wp_error($terms) ) return $terms;
    return rest_ensure_response( array_map(function($t) {
        return [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count ];
    }, $terms));
}
