<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * All Sermons list: a Series column, a Series filter (including "No series"),
 * and a Series field in Bulk Edit.
 *
 * This is how sermons imported from a podcast feed get sorted into series:
 * filter to "No series", tick the sermons that belong together, Bulk actions →
 * Edit → choose the series → Update. Bulk Edit can publish them in the same
 * step.
 */

// ── Column ────────────────────────────────────────────────────────────────────
add_filter( 'manage_ss_sermon_posts_columns', 'ss_list_add_series_column' );
function ss_list_add_series_column( $columns ) {
    $out = [];
    foreach ( $columns as $key => $label ) {
        $out[ $key ] = $label;
        if ( $key === 'title' ) $out['ss_series'] = 'Series';
    }
    if ( ! isset( $out['ss_series'] ) ) $out['ss_series'] = 'Series';
    return $out;
}

add_action( 'manage_ss_sermon_posts_custom_column', 'ss_list_render_series_column', 10, 2 );
function ss_list_render_series_column( $column, $post_id ) {
    if ( $column !== 'ss_series' ) return;
    $series_id = (int) get_post_meta( $post_id, '_ss_series_id', true );
    if ( $series_id && get_post_type( $series_id ) === 'ss_series' ) {
        $url = add_query_arg( [ 'post_type' => 'ss_sermon', 'ss_series_filter' => $series_id ], admin_url( 'edit.php' ) );
        echo '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $series_id ) ) . '</a>';
    } else {
        echo '<span aria-hidden="true">—</span><span class="screen-reader-text">No series</span>';
    }
}

/** Every series, for the filter and Bulk Edit dropdowns. */
function ss_list_all_series() {
    return get_posts([
        'post_type'      => 'ss_series',
        'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
}

// ── Filter ────────────────────────────────────────────────────────────────────
add_action( 'restrict_manage_posts', 'ss_list_series_filter', 10, 1 );
function ss_list_series_filter( $post_type ) {
    if ( $post_type !== 'ss_sermon' ) return;
    $current = isset( $_GET['ss_series_filter'] ) ? sanitize_key( wp_unslash( $_GET['ss_series_filter'] ) ) : '';
    echo '<label class="screen-reader-text" for="ss-series-filter">Filter by series</label>';
    echo '<select name="ss_series_filter" id="ss-series-filter">';
    echo '<option value="">All series</option>';
    echo '<option value="none"' . selected( $current, 'none', false ) . '>No series</option>';
    foreach ( ss_list_all_series() as $series ) {
        echo '<option value="' . (int) $series->ID . '"' . selected( $current, (string) $series->ID, false ) . '>'
           . esc_html( $series->post_title ) . '</option>';
    }
    echo '</select>';
}

add_action( 'pre_get_posts', 'ss_list_apply_series_filter' );
function ss_list_apply_series_filter( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'ss_sermon' || empty( $_GET['ss_series_filter'] ) ) return;

    $filter = sanitize_key( wp_unslash( $_GET['ss_series_filter'] ) );
    $meta   = (array) $query->get( 'meta_query' );
    if ( $filter === 'none' ) {
        // "No series" covers never-assigned sermons and ones set to 0.
        $meta[] = [
            'relation' => 'OR',
            [ 'key' => '_ss_series_id', 'compare' => 'NOT EXISTS' ],
            [ 'key' => '_ss_series_id', 'value' => [ '', '0' ], 'compare' => 'IN' ],
        ];
    } elseif ( ctype_digit( $filter ) ) {
        $meta[] = [ 'key' => '_ss_series_id', 'value' => (int) $filter ];
    }
    $query->set( 'meta_query', $meta );
}

// ── Bulk Edit ─────────────────────────────────────────────────────────────────
add_action( 'bulk_edit_custom_box', 'ss_list_bulk_edit_series', 10, 2 );
function ss_list_bulk_edit_series( $column, $post_type ) {
    if ( $column !== 'ss_series' || $post_type !== 'ss_sermon' ) return;
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label class="inline-edit-group">
                <span class="title">Series</span>
                <select name="ss_bulk_series">
                    <option value="">— No change —</option>
                    <option value="none">Remove from series</option>
                    <?php foreach ( ss_list_all_series() as $series ) : ?>
                        <option value="<?php echo (int) $series->ID; ?>"><?php echo esc_html( $series->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </fieldset>
    <?php
}

/**
 * Apply the Bulk Edit series choice. WordPress has already checked the
 * bulk-posts nonce and skipped posts the user can't edit before save_post
 * fires for each one; the per-post check here is belt and braces.
 */
add_action( 'save_post_ss_sermon', 'ss_list_save_bulk_series', 10, 1 );
function ss_list_save_bulk_series( $post_id ) {
    if ( empty( $_REQUEST['bulk_edit'] ) || ! isset( $_REQUEST['ss_bulk_series'] ) ) return;
    $choice = sanitize_key( wp_unslash( $_REQUEST['ss_bulk_series'] ) );
    if ( $choice === '' || ! current_user_can( 'edit_post', $post_id ) ) return;

    $old = (int) get_post_meta( $post_id, '_ss_series_id', true );

    if ( $choice === 'none' ) {
        delete_post_meta( $post_id, '_ss_series_id' );
        delete_post_meta( $post_id, '_ss_series_order' );
    } elseif ( ctype_digit( $choice ) && get_post_type( (int) $choice ) === 'ss_series' ) {
        if ( $old === (int) $choice ) return;
        update_post_meta( $post_id, '_ss_series_id', (int) $choice );
        // Its position in the old series means nothing in the new one.
        delete_post_meta( $post_id, '_ss_series_order' );
        ss_list_queue_renumber( (int) $choice );
    }
}

/**
 * Number newly filed sermons within their series, in date order, once the
 * whole bulk edit has finished — leaving any order someone set by hand alone.
 */
function ss_list_queue_renumber( $series_id ) {
    static $queued = [];
    if ( isset( $queued[ $series_id ] ) ) return;
    $queued[ $series_id ] = true;
    add_action( 'shutdown', function () use ( $series_id ) { ss_list_renumber_series( $series_id ); } );
}

function ss_list_renumber_series( $series_id ) {
    $sermons = get_posts([
        'post_type'      => 'ss_sermon',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [[ 'key' => '_ss_series_id', 'value' => $series_id ]],
        'orderby'        => 'date',
        'order'          => 'ASC',
    ]);
    // Sort by sermon date, falling back to the post date. (Ordering the query
    // by the meta key instead would silently drop sermons without one.)
    $when = [];
    foreach ( $sermons as $id ) {
        $when[ $id ] = get_post_meta( $id, '_ss_sermon_date', true ) ?: get_post_field( 'post_date', $id );
    }
    usort( $sermons, function ( $a, $b ) use ( $when ) { return strcmp( $when[ $a ], $when[ $b ] ) ?: $a - $b; } );
    $used = [];
    foreach ( $sermons as $id ) {
        $o = (int) get_post_meta( $id, '_ss_series_order', true );
        if ( $o ) $used[ $o ] = true;
    }
    $next = 1;
    foreach ( $sermons as $id ) {
        if ( (int) get_post_meta( $id, '_ss_series_order', true ) ) continue;
        while ( isset( $used[ $next ] ) ) $next++;
        update_post_meta( $id, '_ss_series_order', $next );
        $used[ $next ] = true;
    }
}
