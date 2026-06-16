<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'template_include', 'sermon_suite_template_loader' );

function sermon_suite_template_loader( $template ) {
    if ( is_singular('ss_sermon') ) {
        $custom = SERMON_SUITE_DIR . 'templates/single-ss_sermon.php';
        return file_exists($custom) ? $custom : $template;
    }
    if ( is_singular('ss_series') ) {
        $custom = SERMON_SUITE_DIR . 'templates/single-ss_series.php';
        return file_exists($custom) ? $custom : $template;
    }
    if ( is_post_type_archive('ss_sermon') ) {
        $custom = SERMON_SUITE_DIR . 'templates/archive-ss_sermon.php';
        return file_exists($custom) ? $custom : $template;
    }
    if ( is_post_type_archive('ss_series') ) {
        $custom = SERMON_SUITE_DIR . 'templates/archive-ss_series.php';
        return file_exists($custom) ? $custom : $template;
    }
    return $template;
}
