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
        <div class="gcc-series-body gcc-single-wrap">

            <a href="<?php echo esc_url( sermon_suite_archive_url() ); ?>" class="ss-back-link"><?php echo ss_icon( 'arrow-left', 12 ); ?> All Series</a>

            <?php /* Artwork in its own frame beside the title — series art usually
                     carries its own title type, so nothing is laid over it. */ ?>
            <header class="gcc-series-header<?php echo $img_lg ? '' : ' gcc-series-header--no-art'; ?>">
                <?php if ($img_lg) : ?>
                <div class="gcc-series-art"><img src="<?php echo esc_url($img_lg); ?>" alt="" /></div>
                <?php endif; ?>
                <div class="gcc-series-intro">
                    <h1 class="gcc-series-page-title"><?php the_title(); ?></h1>
                    <div class="gcc-series-page-meta">
                        <?php if ($start) echo '<span>' . esc_html( ss_format_sermon_date($start) . ($end ? ' – ' . ss_format_sermon_date($end) : '') ) . '</span>'; ?>
                        <span><?php echo count($sermons); ?> message<?php echo count($sermons) === 1 ? '' : 's'; ?></span>
                    </div>
                    <?php if (get_the_content()) : ?>
                    <div class="gcc-series-description">
                        <?php the_content(); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($topics)) : ?>
                    <div class="ss-topic-chips gcc-series-topics">
                        <?php foreach ($topics as $t) : ?>
                            <a href="<?php echo esc_url( get_term_link($t) ); ?>" class="ss-topic-chip"><?php echo esc_html($t->name); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($sermons)) : ?>
                    <a href="<?php echo esc_url( get_permalink($sermons[0]->ID) ); ?>" class="gcc-hero-watch-btn">
                        <?php echo ss_icon( 'play', 12 ); ?> Watch the Series
                    </a>
                    <?php endif; ?>
                </div>
            </header>

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
