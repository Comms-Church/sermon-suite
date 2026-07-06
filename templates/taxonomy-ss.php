<?php
/**
 * Template: Taxonomy archive (topic, speaker, scripture book, category, campus)
 *
 * Renders a clean "all sermons on/by/in X" page using the shared sermon card.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$term = get_queried_object();

// Friendly label per taxonomy.
$tax_labels = [
    'ss_topic'           => 'Topic',
    'ss_speaker'         => 'Speaker',
    'ss_scripture_book'  => 'Scripture',
    'ss_series_category' => 'Category',
    'ss_campus'          => 'Campus',
];
$kicker = $tax_labels[ $term->taxonomy ] ?? 'Browse';

// For series-level taxonomies (category/campus) we list series; for the rest we list sermons.
$is_series_tax = in_array( $term->taxonomy, [ 'ss_series_category' ], true );
?>
<main class="ss-tax-page" id="ss-main">
    <div class="ss-tax-wrap">

        <a href="<?php echo esc_url( sermon_suite_archive_url() ); ?>" class="ss-back-link">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            All Sermons
        </a>

        <header class="ss-tax-header">
            <span class="ss-tax-kicker"><?php echo esc_html( $kicker ); ?></span>
            <h1 class="ss-tax-title"><?php echo esc_html( $term->name ); ?></h1>
            <?php if ( $term->description ) : ?>
            <p class="ss-tax-desc"><?php echo esc_html( $term->description ); ?></p>
            <?php endif; ?>
            <p class="ss-tax-count">
                <?php
                $n = (int) $term->count;
                echo $n . ' ' . ( $is_series_tax
                    ? ( $n === 1 ? 'series' : 'series' )
                    : ( $n === 1 ? 'message' : 'messages' ) );
                ?>
            </p>
        </header>

        <?php if ( have_posts() ) : ?>
        <div class="gcc-sermon-list ss-tax-list">
            <?php while ( have_posts() ) : the_post();
                if ( get_post_type() === 'ss_series' ) :
                    $sid   = get_the_ID();
                    $img   = ss_get_series_image( $sid, 'sm' ) ?: ss_get_series_image( $sid, 'lg' );
                    $count = count( ss_get_series_sermons( $sid ) );
                    ?>
                    <a href="<?php the_permalink(); ?>" class="ss-tax-series-row">
                        <?php if ( $img ) : ?>
                        <img src="<?php echo esc_url( $img ); ?>" alt="<?php the_title_attribute(); ?>"
                             class="ss-tax-series-thumb" loading="lazy" />
                        <?php endif; ?>
                        <span class="ss-tax-series-info">
                            <span class="ss-tax-series-title"><?php the_title(); ?></span>
                            <span class="ss-tax-series-count"><?php echo $count; ?> message<?php echo $count !== 1 ? 's' : ''; ?></span>
                        </span>
                    </a>
                    <?php
                else :
                    ss_render_sermon_card( get_the_ID(), 'list' );
                endif;
            endwhile; ?>
        </div>

        <?php
        // Pagination
        $big = 999999999;
        $links = paginate_links([
            'base'      => str_replace($big, '%#%', esc_url( get_pagenum_link($big) )),
            'format'    => '?paged=%#%',
            'current'   => max(1, get_query_var('paged')),
            'total'     => $GLOBALS['wp_query']->max_num_pages,
            'prev_text' => '← Previous',
            'next_text' => 'Next →',
        ]);
        if ( $links ) echo '<div class="ss-pagination">' . $links . '</div>';
        ?>

        <?php else : ?>
        <p class="gcc-no-results">No messages found.</p>
        <?php endif; ?>

    </div>
</main>
<?php
get_footer();
