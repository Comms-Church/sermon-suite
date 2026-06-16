<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'ss_sermon_archive', 'ss_sc_sermon_archive' );

/**
 * [ss_sermon_archive] — Series archive with optional topic filter.
 *
 * Atts:
 *   layout         = grid | list      (grid = card grid; list = full-width series blocks with sermon rows)
 *   columns        = 3                (grid mode only)
 *   show_filter    = true | false
 *   featured_first = true | false
 *   count          = -1               (max series to show)
 *   sermons_per_series = 5            (list mode: sermon rows shown per series before "View all" link)
 */
function ss_sc_sermon_archive( $atts ) {
    $atts = shortcode_atts([
        'layout'             => 'grid',
        'columns'            => 3,
        'show_filter'        => 'true',
        'featured_first'     => 'true',
        'count'              => -1,
        'sermons_per_series' => 5,
        'category'           => '',   // ss_series_category slug — filter to one category
        'campus'             => '',   // ss_campus slug
    ], $atts);

    $topics        = get_terms(['taxonomy'=>'ss_topic','hide_empty'=>true,'orderby'=>'name']);
    $current_topic = isset($_GET['topic']) ? sanitize_text_field($_GET['topic']) : '';

    // Build series query
    $query_args = [
        'posts_per_page' => (int)$atts['count'],
        'post_status'    => 'publish',
    ];
    $tax_query = [];
    if ( $current_topic ) {
        $tax_query[] = [
            'taxonomy' => 'ss_topic',
            'field'    => 'slug',
            'terms'    => $current_topic,
        ];
    }
    if ( !empty($atts['category']) ) {
        $tax_query[] = [
            'taxonomy' => 'ss_series_category',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($atts['category']),
        ];
    }
    if ( !empty($atts['campus']) ) {
        $tax_query[] = [
            'taxonomy' => 'ss_campus',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($atts['campus']),
        ];
    }
    if ( $tax_query ) {
        $query_args['tax_query'] = array_merge(['relation'=>'AND'], $tax_query);
    }
    $series_list = ss_get_all_series($query_args);

    // Featured first
    if ( $atts['featured_first'] !== 'false' ) {
        usort($series_list, function($a, $b) {
            $af = get_post_meta($a->ID, '_ss_series_featured', true) ? 1 : 0;
            $bf = get_post_meta($b->ID, '_ss_series_featured', true) ? 1 : 0;
            return $bf - $af;
        });
    }

    $cols    = max(1, min(4, (int)$atts['columns']));
    $layout  = in_array($atts['layout'], ['grid','list']) ? $atts['layout'] : 'grid';
    $sps     = max(1, (int)$atts['sermons_per_series']);

    ob_start();
    ?>
    <div class="gcc-archive-wrap gcc-archive-<?php echo $layout; ?>">

        <?php if ( $atts['show_filter'] !== 'false' && !empty($topics) ) : ?>
        <div class="gcc-filter-bar">
            <div class="gcc-filter-label">Filter by Topic:</div>
            <div class="gcc-filter-tags">
                <a href="<?php echo esc_url(remove_query_arg('topic')); ?>"
                   class="gcc-tag <?php echo !$current_topic ? 'active' : ''; ?>">All</a>
                <?php foreach ($topics as $t) : ?>
                <a href="<?php echo esc_url(add_query_arg('topic', $t->slug)); ?>"
                   class="gcc-tag <?php echo $current_topic === $t->slug ? 'active' : ''; ?>">
                    <?php echo esc_html($t->name); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( empty($series_list) ) : ?>
        <p class="gcc-no-results">No series found<?php echo $current_topic ? ' for this topic' : ''; ?>.</p>

        <?php elseif ( $layout === 'grid' ) : ?>
        <!-- ── GRID LAYOUT ── -->
        <div class="gcc-series-grid gcc-cols-<?php echo $cols; ?>">
            <?php foreach ($series_list as $series) :
                $img      = ss_get_series_image($series->ID, 'sm') ?: ss_get_series_image($series->ID, 'lg');
                $start    = get_post_meta($series->ID, '_ss_series_start_date', true);
                $end      = get_post_meta($series->ID, '_ss_series_end_date',   true);
                $count    = count(ss_get_series_sermons($series->ID));
                $featured = get_post_meta($series->ID, '_ss_series_featured',   true);
                $desc     = get_the_excerpt($series) ?: wp_trim_words(strip_tags($series->post_content), 18);
            ?>
            <a href="<?php echo esc_url(get_permalink($series->ID)); ?>"
               class="gcc-series-card <?php echo $featured ? 'gcc-series-card--featured' : ''; ?>">
                <div class="gcc-series-card-img">
                    <?php if ($img) : ?>
                    <img src="<?php echo esc_url($img); ?>"
                         alt="<?php echo esc_attr(get_the_title($series)); ?>"
                         loading="lazy" />
                    <?php else : ?>
                    <div class="gcc-series-card-placeholder"></div>
                    <?php endif; ?>
                    <?php if ($featured) : ?>
                    <span class="gcc-series-card-badge">Featured</span>
                    <?php endif; ?>
                </div>
                <div class="gcc-series-card-body">
                    <h3 class="gcc-series-card-title"><?php echo esc_html(get_the_title($series)); ?></h3>
                    <?php if ($start) : ?>
                    <span class="gcc-series-card-date">
                        <?php echo ss_format_sermon_date($start);
                        if ($end) echo ' – ' . ss_format_sermon_date($end); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($desc) : ?>
                    <p class="gcc-series-card-desc"><?php echo esc_html($desc); ?></p>
                    <?php endif; ?>
                    <span class="gcc-series-card-count"><?php echo $count; ?> message<?php echo $count !== 1 ? 's' : ''; ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php else : ?>
        <!-- ── LIST LAYOUT ── -->
        <div class="gcc-series-list-archive">
            <?php foreach ($series_list as $series) :
                $img      = ss_get_series_image($series->ID, 'lg') ?: ss_get_series_image($series->ID, 'sm');
                $start    = get_post_meta($series->ID, '_ss_series_start_date', true);
                $end      = get_post_meta($series->ID, '_ss_series_end_date',   true);
                $sermons  = ss_get_series_sermons($series->ID);
                $total    = count($sermons);
                $featured = get_post_meta($series->ID, '_ss_series_featured', true);
                $desc     = get_the_excerpt($series) ?: wp_trim_words(strip_tags($series->post_content), 30);
                $shown    = array_slice($sermons, 0, $sps);
            ?>
            <div class="gcc-series-block-list <?php echo $featured ? 'gcc-series-block-list--featured' : ''; ?>">

                <!-- Series header row -->
                <div class="gcc-series-block-header">
                    <?php if ($img) : ?>
                    <a href="<?php echo esc_url(get_permalink($series->ID)); ?>" class="gcc-series-block-img-wrap">
                        <img src="<?php echo esc_url($img); ?>"
                             alt="<?php echo esc_attr(get_the_title($series)); ?>"
                             loading="lazy" class="gcc-series-block-img" />
                    </a>
                    <?php endif; ?>
                    <div class="gcc-series-block-meta">
                        <?php if ($featured) : ?>
                        <span class="gcc-series-inline-badge">Featured</span>
                        <?php endif; ?>
                        <h2 class="gcc-series-block-title">
                            <a href="<?php echo esc_url(get_permalink($series->ID)); ?>">
                                <?php echo esc_html(get_the_title($series)); ?>
                            </a>
                        </h2>
                        <div class="gcc-series-block-dates">
                            <?php if ($start) echo ss_format_sermon_date($start) . ($end ? ' – ' . ss_format_sermon_date($end) : ''); ?>
                            <span class="gcc-series-block-count"><?php echo $total; ?> message<?php echo $total !== 1 ? 's' : ''; ?></span>
                        </div>
                        <?php if ($desc) : ?>
                        <p class="gcc-series-block-desc"><?php echo esc_html($desc); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sermon rows -->
                <?php if (!empty($shown)) : ?>
                <div class="gcc-series-sermon-rows">
                    <?php foreach ($shown as $sermon) :
                        $yt_id    = ss_get_youtube_id(get_post_meta($sermon->ID, '_ss_youtube_id', true));
                        $thumb    = $yt_id ? ss_youtube_thumb($yt_id, 'mqdefault') : '';
                        $s_date   = ss_format_sermon_date(get_post_meta($sermon->ID, '_ss_sermon_date', true));
                        $speakers = implode(', ', wp_get_post_terms($sermon->ID, 'ss_speaker', ['fields'=>'names']));
                        $scr      = get_post_meta($sermon->ID, '_ss_scripture_ref', true);
                        $scr_url  = get_post_meta($sermon->ID, '_ss_scripture_url', true);
                    ?>
                    <a href="<?php echo esc_url(get_permalink($sermon->ID)); ?>" class="gcc-sermon-row-item">
                        <?php if ($thumb) : ?>
                        <div class="gcc-sermon-row-thumb">
                            <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy" />
                            <span class="gcc-sermon-row-play">▶</span>
                        </div>
                        <?php endif; ?>
                        <div class="gcc-sermon-row-info">
                            <span class="gcc-sermon-row-title"><?php echo esc_html(get_the_title($sermon)); ?></span>
                            <span class="gcc-sermon-row-meta">
                                <?php if ($speakers) echo esc_html($speakers) . ' · '; ?>
                                <?php if ($s_date)   echo esc_html($s_date); ?>
                                <?php if ($scr)      echo ' · 📖 ' . esc_html($scr); ?>
                            </span>
                        </div>
                        <span class="gcc-sermon-row-arrow">→</span>
                    </a>
                    <?php endforeach; ?>

                    <?php if ($total > $sps) : ?>
                    <a href="<?php echo esc_url(get_permalink($series->ID)); ?>" class="gcc-series-view-all">
                        View all <?php echo $total; ?> messages in this series →
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div><!-- .gcc-series-block-list -->
            <?php endforeach; ?>
        </div><!-- .gcc-series-list-archive -->
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}

/**
 * [ss_series_grid] — Grid of series cards only.
 *
 * Attributes:
 *   count     = -1
 *   featured  = false
 *   columns   = 3
 */
add_shortcode( 'ss_series_grid', 'ss_sc_series_grid' );
function ss_sc_series_grid( $atts ) {
    $atts = shortcode_atts([
        'count'    => -1,
        'featured' => 'false',
        'columns'  => 3,
        'category' => '',
        'campus'   => '',
    ], $atts);

    $query_args = [
        'posts_per_page' => (int)$atts['count'],
    ];
    $grid_tax = [];
    if ( !empty($atts['category']) ) {
        $grid_tax[] = [ 'taxonomy' => 'ss_series_category', 'field' => 'slug', 'terms' => sanitize_text_field($atts['category']) ];
    }
    if ( !empty($atts['campus']) ) {
        $grid_tax[] = [ 'taxonomy' => 'ss_campus', 'field' => 'slug', 'terms' => sanitize_text_field($atts['campus']) ];
    }
    if ( $grid_tax ) {
        $query_args['tax_query'] = array_merge(['relation'=>'AND'], $grid_tax);
    }
    if ( $atts['featured'] === 'true' ) {
        $query_args['meta_query'] = [[
            'key'   => '_ss_series_featured',
            'value' => '1',
        ]];
    }
    $series_list = ss_get_all_series($query_args);

    ob_start();
    echo '<div class="gcc-series-grid gcc-cols-' . (int)$atts['columns'] . '">';
    foreach ($series_list as $series) {
        $img   = ss_get_series_image($series->ID, 'sm');
        $start = get_post_meta($series->ID, '_ss_series_start_date', true);
        $count = count(ss_get_series_sermons($series->ID));
        ?>
        <a href="<?php echo get_permalink($series->ID); ?>" class="gcc-series-card">
            <?php if ($img) : ?>
                <div class="gcc-series-card-img">
                    <img src="<?php echo esc_url($img); ?>"
                         alt="<?php echo esc_attr(get_the_title($series)); ?>"
                         loading="lazy" />
                </div>
            <?php endif; ?>
            <div class="gcc-series-card-body">
                <h3 class="gcc-series-card-title"><?php echo get_the_title($series); ?></h3>
                <?php if ($start) : ?>
                    <span class="gcc-series-card-date"><?php echo ss_format_sermon_date($start); ?></span>
                <?php endif; ?>
                <span class="gcc-series-card-count"><?php echo $count; ?> messages</span>
            </div>
        </a>
        <?php
    }
    echo '</div>';
    return ob_get_clean();
}

/**
 * [ss_sermon_player id="123"] — Embed a single sermon player.
 */
add_shortcode( 'ss_sermon_player', 'ss_sc_sermon_player' );
function ss_sc_sermon_player( $atts ) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $id   = (int)$atts['id'];
    if ( !$id ) $id = get_the_ID();

    $youtube_id  = ss_get_youtube_id(get_post_meta($id, '_ss_youtube_id', true));
    $scripture   = get_post_meta($id, '_ss_scripture_ref', true);
    $scrip_url   = get_post_meta($id, '_ss_scripture_url', true);
    $resources   = ss_get_sermon_resources($id);

    ob_start();
    ?>
    <div class="gcc-sermon-player">
        <?php if ($youtube_id) : ?>
        <div class="gcc-video-wrap">
            <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_id); ?>?rel=0"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen loading="lazy"></iframe>
        </div>
        <?php endif; ?>

        <?php if ($scripture) : ?>
        <div class="gcc-sermon-scripture">
            <?php if ($scrip_url) : ?>
                <a href="<?php echo esc_url($scrip_url); ?>" target="_blank" rel="noopener" class="gcc-scripture-link">
                    📖 <?php echo esc_html($scripture); ?>
                </a>
            <?php else : ?>
                <span class="gcc-scripture-text">📖 <?php echo esc_html($scripture); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($resources)) : ?>
        <div class="gcc-sermon-resources">
            <h4 class="gcc-resources-heading">Resources</h4>
            <div class="gcc-resources-list">
                <?php foreach ($resources as $r) : ?>
                <a href="<?php echo esc_url($r['url']); ?>" class="gcc-resource-item gcc-resource-<?php echo esc_attr($r['type']); ?>"
                   target="_blank" rel="noopener">
                    <?php echo ss_resource_icon($r['type']); ?>
                    <?php echo esc_html($r['label']); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── [ss_message_list] ─────────────────────────────────────────────────────────
/**
 * Flat list of individual messages — no series grouping.
 * Perfect for churches that don't use series.
 *
 * Atts:
 *   per_page    = 12
 *   layout      = list | grid
 *   columns     = 3          (grid only)
 *   show_filter = true        topic filter bar
 *   speaker     =             filter by speaker slug
 *   series_id   = 0           filter to one series (0 = all)
 *   campus      =             filter by campus slug
 *   topic       =             filter by topic slug
 *   orderby     = date | title
 *   order       = DESC | ASC
 *   show_paging = true
 */
add_shortcode( 'ss_message_list', 'ss_sc_message_list' );
function ss_sc_message_list( $atts ) {
    $atts = shortcode_atts([
        'per_page'    => 12,
        'layout'      => 'list',
        'columns'     => 3,
        'show_filter' => 'true',
        'speaker'     => '',
        'series_id'   => 0,
        'campus'      => '',
        'topic'       => '',
        'orderby'     => 'date',
        'order'       => 'DESC',
        'show_paging' => 'true',
    ], $atts);

    $paged    = max(1, get_query_var('paged') ?: (get_query_var('page') ?: 1));
    $per_page = (int)$atts['per_page'];
    $layout   = in_array($atts['layout'], ['list','grid']) ? $atts['layout'] : 'list';
    $cols     = max(1, min(4, (int)$atts['columns']));

    // Current filter from URL (allows topic filter bar to work)
    $current_topic   = !empty($atts['topic'])   ? $atts['topic']   : (isset($_GET['topic'])   ? sanitize_text_field($_GET['topic'])   : '');
    $current_speaker = !empty($atts['speaker']) ? $atts['speaker'] : (isset($_GET['speaker']) ? sanitize_text_field($_GET['speaker']) : '');
    $current_campus  = !empty($atts['campus'])  ? $atts['campus']  : (isset($_GET['campus'])  ? sanitize_text_field($_GET['campus'])  : '');

    $args = [
        'post_type'      => 'ss_sermon',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
    ];

    // Ordering
    if ( $atts['orderby'] === 'title' ) {
        $args['orderby'] = 'title';
        $args['order']   = strtoupper($atts['order']);
    } else {
        $args['meta_key'] = '_ss_sermon_date';
        $args['orderby']  = 'meta_value';
        $args['order']    = strtoupper($atts['order']);
    }

    // Series filter
    if ( (int)$atts['series_id'] > 0 ) {
        $args['meta_query'] = [[
            'key'   => '_ss_series_id',
            'value' => (int)$atts['series_id'],
        ]];
    }

    // Taxonomy filters
    $tax_query = [];
    if ( $current_topic ) {
        $tax_query[] = [ 'taxonomy' => 'ss_topic',   'field' => 'slug', 'terms' => $current_topic ];
    }
    if ( $current_speaker ) {
        $tax_query[] = [ 'taxonomy' => 'ss_speaker', 'field' => 'slug', 'terms' => $current_speaker ];
    }
    if ( $current_campus ) {
        $tax_query[] = [ 'taxonomy' => 'ss_campus',  'field' => 'slug', 'terms' => $current_campus ];
    }
    if ( $tax_query ) {
        $args['tax_query'] = array_merge(['relation'=>'AND'], $tax_query);
    }

    $query = new WP_Query($args);

    // Filter bar data
    $topics   = get_terms(['taxonomy'=>'ss_topic',   'hide_empty'=>true, 'orderby'=>'name']);
    $speakers = get_terms(['taxonomy'=>'ss_speaker', 'hide_empty'=>true, 'orderby'=>'name']);
    $campuses = get_terms(['taxonomy'=>'ss_campus',  'hide_empty'=>true, 'orderby'=>'name']);

    ob_start();
    ?>
    <div class="ss-message-list-wrap">

        <?php if ( $atts['show_filter'] !== 'false' && (!empty($topics) || !empty($campuses) || !empty($speakers)) ) : ?>
        <div class="ss-message-filters">

            <?php if ( !empty($campuses) ) : ?>
            <div class="ss-filter-group">
                <span class="ss-filter-group-label">Campus:</span>
                <a href="<?php echo esc_url(remove_query_arg('campus')); ?>"
                   class="gcc-tag <?php echo !$current_campus ? 'active' : ''; ?>">All</a>
                <?php foreach ($campuses as $c) : ?>
                <a href="<?php echo esc_url(add_query_arg('campus', $c->slug)); ?>"
                   class="gcc-tag <?php echo $current_campus === $c->slug ? 'active' : ''; ?>">
                    <?php echo esc_html($c->name); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( !empty($speakers) ) : ?>
            <div class="ss-filter-group">
                <span class="ss-filter-group-label">Speaker:</span>
                <a href="<?php echo esc_url(remove_query_arg('speaker')); ?>"
                   class="gcc-tag <?php echo !$current_speaker ? 'active' : ''; ?>">All</a>
                <?php foreach ($speakers as $sp) : ?>
                <a href="<?php echo esc_url(add_query_arg('speaker', $sp->slug)); ?>"
                   class="gcc-tag <?php echo $current_speaker === $sp->slug ? 'active' : ''; ?>">
                    <?php echo esc_html($sp->name); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( !empty($topics) ) : ?>
            <div class="ss-filter-group">
                <span class="ss-filter-group-label">Topic:</span>
                <a href="<?php echo esc_url(remove_query_arg('topic')); ?>"
                   class="gcc-tag <?php echo !$current_topic ? 'active' : ''; ?>">All</a>
                <?php foreach ($topics as $t) : ?>
                <a href="<?php echo esc_url(add_query_arg('topic', $t->slug)); ?>"
                   class="gcc-tag <?php echo $current_topic === $t->slug ? 'active' : ''; ?>">
                    <?php echo esc_html($t->name); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div><!-- .ss-message-filters -->
        <?php endif; ?>

        <?php if ( ! $query->have_posts() ) : ?>
        <p class="gcc-no-results">No messages found.</p>
        <?php elseif ( $layout === 'grid' ) : ?>

        <div class="gcc-series-grid gcc-cols-<?php echo $cols; ?>">
            <?php while ($query->have_posts()) : $query->the_post();
                ss_render_sermon_card(get_the_ID(), 'grid');
            endwhile; wp_reset_postdata(); ?>
        </div>

        <?php else : ?>

        <div class="gcc-sermon-list">
            <?php while ($query->have_posts()) : $query->the_post();
                ss_render_sermon_card(get_the_ID(), 'list');
            endwhile; wp_reset_postdata(); ?>
        </div>

        <?php endif; ?>

        <?php if ( $atts['show_paging'] !== 'false' && $query->max_num_pages > 1 ) :
            $big = 999999;
            echo '<div class="ss-pagination">';
            echo paginate_links([
                'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format'    => '?paged=%#%',
                'current'   => $paged,
                'total'     => $query->max_num_pages,
                'prev_text' => '← Previous',
                'next_text' => 'Next →',
            ]);
            echo '</div>';
        endif; ?>

    </div><!-- .ss-message-list-wrap -->
    <?php
    return ob_get_clean();
}

// ── Shared card renderer ───────────────────────────────────────────────────────

function ss_render_sermon_card( $sermon_id, $style = 'list' ) {
    $youtube_id  = ss_get_youtube_id(get_post_meta($sermon_id, '_ss_youtube_id', true));
    $thumb       = $youtube_id ? ss_youtube_thumb($youtube_id) : '';
    $date        = ss_format_sermon_date(get_post_meta($sermon_id, '_ss_sermon_date', true));
    $speakers    = wp_get_post_terms($sermon_id, 'ss_speaker', ['fields' => 'names']);
    $scripture   = get_post_meta($sermon_id, '_ss_scripture_ref', true);
    $scrip_url   = get_post_meta($sermon_id, '_ss_scripture_url', true);
    $resources   = ss_get_sermon_resources($sermon_id);
    $topics      = wp_get_post_terms($sermon_id, 'ss_topic', ['fields' => 'all']);
    $desc        = get_the_excerpt($sermon_id) ?: wp_trim_words(strip_tags(get_post_field('post_content', $sermon_id)), 30);
    ?>
    <div class="gcc-sermon-card gcc-card-<?php echo esc_attr($style); ?>">
        <?php if ($thumb) : ?>
        <a href="<?php echo get_permalink($sermon_id); ?>" class="gcc-card-thumb-wrap">
            <img src="<?php echo esc_url($thumb); ?>"
                 alt="<?php the_title_attribute(['post' => $sermon_id]); ?>"
                 class="gcc-card-thumb" loading="lazy" />
            <span class="gcc-play-btn" aria-label="Watch sermon">▶</span>
        </a>
        <?php endif; ?>
        <div class="gcc-card-body">
            <h3 class="gcc-card-title">
                <a href="<?php echo get_permalink($sermon_id); ?>"><?php echo get_the_title($sermon_id); ?></a>
            </h3>
            <div class="gcc-card-byline">
                <?php if (!empty($speakers)) : ?>
                    <span class="gcc-card-speaker"><?php echo esc_html(implode(', ', $speakers)); ?></span>
                <?php endif; ?>
                <?php if ($date) : ?>
                    <span class="gcc-card-date"><?php echo esc_html($date); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($desc && $style === 'list') : ?>
                <p class="gcc-card-desc"><?php echo esc_html($desc); ?></p>
            <?php endif; ?>
            <?php if ($scripture) : ?>
            <div class="gcc-card-scripture">
                <?php if ($scrip_url) : ?>
                    <a href="<?php echo esc_url($scrip_url); ?>" target="_blank" rel="noopener" class="gcc-scripture-inline">
                        📖 <?php echo esc_html($scripture); ?>
                    </a>
                <?php else : ?>
                    <span>📖 <?php echo esc_html($scripture); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($topics) && $style === 'list') : ?>
            <div class="gcc-card-topics">
                <?php foreach ($topics as $t) : ?>
                    <a href="<?php echo add_query_arg('topic', $t->slug, get_post_type_archive_link('ss_sermon')); ?>"
                       class="gcc-topic-badge"><?php echo esc_html($t->name); ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($resources)) : ?>
            <div class="gcc-card-resources">
                <?php foreach ($resources as $r) : ?>
                    <a href="<?php echo esc_url($r['url']); ?>" class="gcc-resource-link gcc-resource-<?php echo esc_attr($r['type']); ?>"
                       target="_blank" rel="noopener">
                        <?php echo ss_resource_icon($r['type']); ?> <?php echo esc_html($r['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <a href="<?php echo get_permalink($sermon_id); ?>" class="gcc-watch-btn">Watch</a>
        </div>
    </div>
    <?php
}

// ── [ss_latest_hero] ─────────────────────────────────────────────────────────
/**
 * Displays the most recent published sermon as a large hero banner.
 *
 * [ss_latest_hero]
 */
add_shortcode( 'ss_latest_hero', 'ss_sc_latest_hero' );
function ss_sc_latest_hero( $atts ) {
    $atts = shortcode_atts([
        'label' => 'Latest Message',
    ], $atts);

    $sermons = get_posts([
        'post_type'      => 'ss_sermon',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_ss_sermon_date',
        'orderby'        => 'meta_value',
        'order'          => 'DESC',
    ]);

    if ( empty($sermons) ) return '';

    $sermon     = $sermons[0];
    $sermon_id  = $sermon->ID;
    $youtube_id = ss_get_youtube_id( get_post_meta($sermon_id, '_ss_youtube_id', true) );
    $thumb      = $youtube_id ? ss_youtube_thumb($youtube_id, 'maxresdefault') : ss_get_series_image( (int)get_post_meta($sermon_id, '_ss_series_id', true) );
    $series_id  = (int) get_post_meta($sermon_id, '_ss_series_id', true);
    $series     = $series_id ? get_post($series_id) : null;
    $date       = ss_format_sermon_date( get_post_meta($sermon_id, '_ss_sermon_date', true) );
    $speakers   = wp_get_post_terms($sermon_id, 'ss_speaker', ['fields' => 'names']);
    $scripture  = get_post_meta($sermon_id, '_ss_scripture_ref', true);
    $desc       = get_the_excerpt($sermon_id) ?: wp_trim_words(strip_tags($sermon->post_content), 35);
    $resources  = ss_get_sermon_resources($sermon_id);

    ob_start();
    ?>
    <div class="gcc-hero-wrap" <?php if ($thumb) echo 'style="background-image:url(' . esc_url($thumb) . ')"'; ?>>
        <div class="gcc-hero-overlay">
            <div class="gcc-hero-inner">
                <div class="gcc-hero-badge"><?php echo esc_html($atts['label']); ?></div>

                <?php if ($series) : ?>
                <div class="gcc-hero-series">
                    <a href="<?php echo get_permalink($series_id); ?>">
                        <?php echo esc_html($series->post_title); ?>
                    </a>
                </div>
                <?php endif; ?>

                <h2 class="gcc-hero-title">
                    <a href="<?php echo get_permalink($sermon_id); ?>">
                        <?php echo get_the_title($sermon_id); ?>
                    </a>
                </h2>

                <div class="gcc-hero-meta">
                    <?php if (!empty($speakers)) echo '<span>' . esc_html(implode(', ', $speakers)) . '</span>'; ?>
                    <?php if ($date) echo '<span>' . esc_html($date) . '</span>'; ?>
                    <?php if ($scripture) echo '<span>📖 ' . esc_html($scripture) . '</span>'; ?>
                </div>

                <?php if ($desc) : ?>
                <p class="gcc-hero-desc"><?php echo esc_html($desc); ?></p>
                <?php endif; ?>

                <div class="gcc-hero-actions">
                    <a href="<?php echo get_permalink($sermon_id); ?>" class="gcc-hero-watch-btn">
                        ▶ Watch Now
                    </a>
                    <?php foreach ( array_slice($resources, 0, 2) as $r ) : ?>
                    <a href="<?php echo esc_url($r['url']); ?>" class="gcc-hero-resource-btn"
                       target="_blank" rel="noopener">
                        <?php echo ss_resource_icon($r['type']); ?> <?php echo esc_html($r['label']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ── [ss_related_sermons] ─────────────────────────────────────────────────────
/**
 * Shows related sermons: more from same series + more on same topic.
 * Designed to sit at the bottom of a single sermon page.
 *
 * [ss_related_sermons id="123" count="4"]
 */
add_shortcode( 'ss_related_sermons', 'ss_sc_related_sermons' );
function ss_sc_related_sermons( $atts ) {
    $atts = shortcode_atts([
        'id'    => get_the_ID(),
        'count' => 4,
    ], $atts);

    $sermon_id = (int) $atts['id'];
    $count     = (int) $atts['count'];
    $series_id = (int) get_post_meta($sermon_id, '_ss_series_id', true);
    $topics    = wp_get_post_terms($sermon_id, 'ss_topic', ['fields' => 'ids']);

    $shown_ids = [$sermon_id];
    ob_start();

    // ── More from this series ──
    if ( $series_id ) {
        $series_sermons = ss_get_series_sermons($series_id);
        $series_others  = array_filter($series_sermons, fn($s) => $s->ID !== $sermon_id);

        if ( ! empty($series_others) ) :
            $series_others = array_slice(array_values($series_others), 0, $count);
            $shown_ids     = array_merge($shown_ids, array_column($series_others, 'ID'));
            ?>
            <div class="gcc-related-block">
                <h3 class="gcc-related-heading">
                    More from
                    <a href="<?php echo get_permalink($series_id); ?>">
                        <?php echo get_the_title($series_id); ?>
                    </a>
                </h3>
                <div class="gcc-sermon-grid">
                    <?php foreach ($series_others as $s) ss_render_sermon_card($s->ID, 'grid'); ?>
                </div>
            </div>
        <?php
        endif;
    }

    // ── More on this topic ──
    if ( ! empty($topics) ) {
        $topic_sermons = get_posts([
            'post_type'      => 'ss_sermon',
            'post_status'    => 'publish',
            'posts_per_page' => $count + count($shown_ids),
            'post__not_in'   => $shown_ids,
            'tax_query'      => [[
                'taxonomy' => 'ss_topic',
                'field'    => 'term_id',
                'terms'    => $topics,
            ]],
            'meta_key'       => '_ss_sermon_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        ]);

        $topic_sermons = array_slice($topic_sermons, 0, $count);

        if ( ! empty($topic_sermons) ) :
            $topic_names = wp_get_post_terms($sermon_id, 'ss_topic', ['fields' => 'names']);
            ?>
            <div class="gcc-related-block">
                <h3 class="gcc-related-heading">
                    More on <?php echo esc_html(implode(', ', array_slice($topic_names, 0, 2))); ?>
                </h3>
                <div class="gcc-sermon-grid">
                    <?php foreach ($topic_sermons as $s) ss_render_sermon_card($s->ID, 'grid'); ?>
                </div>
            </div>
        <?php
        endif;
    }

    $output = ob_get_clean();
    if ( ! trim($output) ) return '';

    return '<div class="gcc-related-sermons">' . $output . '</div>';
}
