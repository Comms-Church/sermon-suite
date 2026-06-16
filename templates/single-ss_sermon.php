<?php
/**
 * Template: Single Sermon — Two-column layout
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

while ( have_posts() ) : the_post();
    $sermon_id   = get_the_ID();
    $youtube_id  = ss_get_youtube_id( get_post_meta($sermon_id, '_ss_youtube_id', true) );
    $series_id   = (int) get_post_meta($sermon_id, '_ss_series_id', true);
    $scripture   = get_post_meta($sermon_id, '_ss_scripture_ref', true);
    $scrip_url   = get_post_meta($sermon_id, '_ss_scripture_url', true);
    $resources   = ss_get_sermon_resources($sermon_id);
    $date        = ss_format_sermon_date( get_post_meta($sermon_id, '_ss_sermon_date', true) );
    $order       = (int) get_post_meta($sermon_id, '_ss_series_order', true);
    $speakers    = wp_get_post_terms($sermon_id, 'ss_speaker', ['fields' => 'names']);
    $topics      = wp_get_post_terms($sermon_id, 'ss_topic',   ['fields' => 'all']);
    $notes       = get_post_meta($sermon_id, '_ss_sermon_notes', true);

    // Series data
    $series       = $series_id ? get_post($series_id) : null;
    $series_title = $series ? get_the_title($series) : '';
    $series_img   = $series_id ? ( ss_get_series_image($series_id, 'sm') ?: ss_get_series_image($series_id, 'lg') ) : '';
    $series_count = $series_id ? count(ss_get_series_sermons($series_id)) : 0;
    $series_start = $series_id ? get_post_meta($series_id, '_ss_series_start_date', true) : '';

    // Prev / next within series
    $prev = null; $next = null;
    if ( $series_id && $order ) {
        $siblings = ss_get_series_sermons($series_id);
        foreach ( $siblings as $sib ) {
            $o = (int) get_post_meta($sib->ID, '_ss_series_order', true);
            if ( $o === $order - 1 ) $prev = $sib;
            if ( $o === $order + 1 ) $next = $sib;
        }
    }
    ?>
    <main class="ss-sermon-page" id="ss-main">
        <div class="ss-sermon-wrap">

            <!-- Back link -->
            <a href="<?php echo $series_id ? esc_url(get_permalink($series_id)) : esc_url(sermon_suite_archive_url()); ?>"
               class="ss-back-link">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php echo $series_id ? 'Back to ' . esc_html($series_title) : 'All Messages'; ?>
            </a>

            <!-- Two-column layout -->
            <div class="ss-sermon-layout">

                <!-- ── MAIN COLUMN ── -->
                <div class="ss-sermon-main">

                    <!-- Video -->
                    <?php if ($youtube_id) : ?>
                    <div class="ss-video-wrap">
                        <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_id); ?>?rel=0"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    </div>
                    <?php endif; ?>

                    <!-- Title + byline -->
                    <h1 class="ss-sermon-title"><?php the_title(); ?></h1>
                    <div class="ss-sermon-byline">
                        <?php if (!empty($speakers)) : ?>
                            <span class="ss-byline-speaker"><?php echo esc_html(implode(', ', $speakers)); ?></span>
                        <?php endif; ?>
                        <?php if ($date) : ?>
                            <?php if (!empty($speakers)) echo '<span class="ss-byline-sep">·</span>'; ?>
                            <span class="ss-byline-date"><?php echo esc_html($date); ?></span>
                        <?php endif; ?>
                        <?php if ($order && $series_count) : ?>
                            <span class="ss-byline-sep">·</span>
                            <span class="ss-byline-order">Message <?php echo $order; ?> of <?php echo $series_count; ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if (get_the_content()) : ?>
                    <div class="ss-sermon-desc">
                        <?php the_content(); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Sermon Notes toggle -->
                    <?php if ($notes) : ?>
                    <div class="ss-notes-block">
                        <button class="ss-notes-toggle" aria-expanded="false">
                            <span class="ss-notes-toggle-label">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M5 6h6M5 9h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                Sermon Notes
                            </span>
                            <svg class="ss-notes-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <div class="ss-notes-body" hidden>
                            <?php echo wp_kses_post($notes); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Prev / Next in series -->
                    <?php if ($prev || $next) : ?>
                    <nav class="ss-series-nav" aria-label="Series navigation">
                        <?php if ($prev) : ?>
                        <a href="<?php echo get_permalink($prev->ID); ?>" class="ss-nav-card ss-nav-prev">
                            <span class="ss-nav-label">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M9 2.5L4.5 7 9 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Previous
                            </span>
                            <span class="ss-nav-title"><?php echo esc_html(get_the_title($prev->ID)); ?></span>
                        </a>
                        <?php else : ?>
                        <div></div>
                        <?php endif; ?>
                        <?php if ($next) : ?>
                        <a href="<?php echo get_permalink($next->ID); ?>" class="ss-nav-card ss-nav-next">
                            <span class="ss-nav-label">
                                Next
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M5 2.5L9.5 7 5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                            <span class="ss-nav-title"><?php echo esc_html(get_the_title($next->ID)); ?></span>
                        </a>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>

                    <!-- Related sermons -->
                    <?php
                    $related = ss_sc_related_sermons(['id' => $sermon_id, 'count' => 4]);
                    if ($related) echo $related;
                    ?>

                </div><!-- .ss-sermon-main -->

                <!-- ── SIDEBAR ── -->
                <div class="ss-sermon-sidebar">

                    <!-- Scripture -->
                    <?php if ($scripture) : ?>
                    <div class="ss-sidebar-card">
                        <div class="ss-sidebar-card-head">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><rect x="2" y="1" width="10" height="12" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M5 4.5h4M5 7h4M5 9.5h2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                            Scripture
                        </div>
                        <div class="ss-sidebar-card-body">
                            <?php if ($scrip_url) : ?>
                            <a href="<?php echo esc_url($scrip_url); ?>" target="_blank" rel="noopener" class="ss-scripture-link">
                                <?php echo esc_html($scripture); ?>
                                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 10L10 2M10 2H5M10 2v5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <?php else : ?>
                            <span class="ss-scripture-plain"><?php echo esc_html($scripture); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Resources -->
                    <?php if (!empty($resources)) : ?>
                    <div class="ss-sidebar-card">
                        <div class="ss-sidebar-card-head">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1v8M4 6l3 3 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 11h10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                            Resources
                        </div>
                        <div class="ss-sidebar-card-body ss-sidebar-card-body--flush">
                            <?php foreach ($resources as $r) :
                                $icons = ['pdf'=>'M3 2h6l3 3v9H3V2z M9 2v3h3', 'devotional'=>'M7 1v8M4 6l3 3 3-3M2 11h10', 'notes'=>'M2 2h10v10H2z M5 5h4M5 8h2', 'link'=>'M5.5 8.5L8.5 5.5M7 4l1.5-1.5a2.1 2.1 0 013 3L10 7M7 10l-1.5 1.5a2.1 2.1 0 01-3-3L4 7'];
                                $path  = $icons[$r['type']] ?? $icons['link'];
                            ?>
                            <a href="<?php echo esc_url($r['url']); ?>" target="_blank" rel="noopener" class="ss-resource-row">
                                <span class="ss-resource-icon">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="<?php echo esc_attr($path); ?>" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </span>
                                <span class="ss-resource-info">
                                    <span class="ss-resource-name"><?php echo esc_html($r['label']); ?></span>
                                    <span class="ss-resource-type"><?php echo esc_html(ucfirst($r['type'])); ?></span>
                                </span>
                                <svg class="ss-resource-arrow" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M5 2.5L9.5 7 5 11.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Topics -->
                    <?php if (!empty($topics)) : ?>
                    <div class="ss-sidebar-card">
                        <div class="ss-sidebar-card-head">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M2 7a5 5 0 1010 0A5 5 0 002 7zM7 4v3l2 2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Topics
                        </div>
                        <div class="ss-sidebar-card-body">
                            <div class="ss-topic-chips">
                                <?php foreach ($topics as $t) : ?>
                                <a href="<?php echo esc_url(get_term_link($t)); ?>" class="ss-topic-chip">
                                    <?php echo esc_html($t->name); ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Series info -->
                    <?php if ($series) : ?>
                    <div class="ss-sidebar-card">
                        <div class="ss-sidebar-card-head">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><rect x="1" y="3" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M5 6l2 1.5L9 6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Series
                        </div>
                        <div class="ss-sidebar-card-body">
                            <?php if ($series_img) : ?>
                            <a href="<?php echo esc_url(get_permalink($series_id)); ?>" class="ss-series-thumb-link">
                                <img src="<?php echo esc_url($series_img); ?>"
                                     alt="<?php echo esc_attr($series_title); ?>"
                                     class="ss-series-thumb" loading="lazy" />
                            </a>
                            <?php endif; ?>
                            <p class="ss-series-name">
                                <a href="<?php echo esc_url(get_permalink($series_id)); ?>">
                                    <?php echo esc_html($series_title); ?>
                                </a>
                            </p>
                            <p class="ss-series-meta">
                                <?php echo $series_count; ?> message<?php echo $series_count !== 1 ? 's' : ''; ?>
                                <?php if ($series_start) echo ' · ' . ss_format_sermon_date($series_start); ?>
                            </p>
                            <a href="<?php echo esc_url(get_permalink($series_id)); ?>" class="ss-view-series-btn">
                                View full series
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- .ss-sermon-sidebar -->

            </div><!-- .ss-sermon-layout -->
        </div><!-- .ss-sermon-wrap -->
    </main>
    <?php
endwhile;

get_footer();
