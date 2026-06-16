<?php
/**
 * Template: Single Series
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

while ( have_posts() ) : the_post();
    $series_id = get_the_ID();
    $img_lg    = ss_get_series_image($series_id, 'lg');
    $start     = get_post_meta($series_id, '_ss_series_start_date', true);
    $end       = get_post_meta($series_id, '_ss_series_end_date',   true);
    $sermons   = ss_get_series_sermons($series_id);
    $topics    = wp_get_post_terms($series_id, 'ss_topic', ['fields' => 'all']);
    ?>
    <main class="gcc-single-series" id="gcc-main">
        <!-- Series Hero -->
        <div class="gcc-series-hero" <?php if ($img_lg) echo 'style="background-image:url(' . esc_url($img_lg) . ')"'; ?>>
            <div class="gcc-series-hero-overlay">
                <div class="gcc-series-hero-content">
                    <a href="<?php echo esc_url( sermon_suite_archive_url() ); ?>" class="gcc-back-link-hero">← All Series</a>
                    <h1><?php the_title(); ?></h1>
                    <div class="gcc-series-hero-meta">
                        <?php if ($start) echo '<span>' . ss_format_sermon_date($start) . ($end ? ' – ' . ss_format_sermon_date($end) : '') . '</span>'; ?>
                        <span><?php echo count($sermons); ?> messages</span>
                    </div>
                    <?php if (!empty($topics)) : ?>
                    <div class="gcc-series-hero-topics">
                        <?php foreach ($topics as $t) : ?>
                            <a href="<?php echo get_term_link($t); ?>" class="gcc-topic-badge-light">
                                <?php echo esc_html($t->name); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="gcc-series-body gcc-single-wrap">

            <!-- Series description -->
            <?php if (get_the_content()) : ?>
            <div class="gcc-series-description">
                <?php the_content(); ?>
            </div>
            <?php endif; ?>

            <!-- Sermons list -->
            <?php if (!empty($sermons)) : ?>
            <div class="sermon-suite-in-series">
                <h2 class="gcc-section-heading">Messages in This Series</h2>
                <div class="gcc-sermon-list">
                    <?php foreach ($sermons as $sermon) :
                        ss_render_sermon_card($sermon->ID, 'list');
                    endforeach; ?>
                </div>
            </div>
            <?php else : ?>
            <p class="gcc-no-sermons">No messages have been added to this series yet.</p>
            <?php endif; ?>

        </div>
    </main>
    <?php
endwhile;

get_footer();
