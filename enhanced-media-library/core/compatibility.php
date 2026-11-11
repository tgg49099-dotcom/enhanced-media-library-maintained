<?php

if ( ! defined( 'ABSPATH' ) )
    exit;



/**
 *  Elementor
 *  @TODO: temporary solution
 *
 *  @since    2.5
 *  @since    2.9.2 register scripts for Elementor added
 *  @created  28/01/18
 */

add_action( 'elementor/editor/before_enqueue_scripts', 'wpuxss_eml_register_scripts' );

add_action( 'elementor/editor/after_enqueue_scripts', 'wpuxss_eml_elementor_scripts' );

function wpuxss_eml_elementor_scripts() {

    global $wpuxss_eml_dir;


    wp_enqueue_style( 'common' );
    wp_enqueue_style(
        'wpuxss-eml-elementor-media-style',
        $wpuxss_eml_dir . 'css/eml-admin-media.css'
    );
}





/**
 *  Impreza Theme
 *
 *  @since    2.8.8
 *  @created  08/2021
 */

add_action( 'after_setup_theme', 'wpuxss_eml_after_setup_theme_impreza', 9 );

function wpuxss_eml_after_setup_theme_impreza() {

    remove_filter( 'attachment_fields_to_edit', 'us_attachment_fields_to_edit_categories' );
}



/**
 *  SimpLy Gallery plugin
 *
 *  @since    2.8.9
 *  @created  10/2021
 */

add_action( 'wp_loaded', 'wpuxss_eml_compat_on_wp_loaded' );

function wpuxss_eml_compat_on_wp_loaded() {

    remove_filter( 'ajax_query_attachments_args', 'pgc_sgb_ajaxQueryAttachmentsArgs', 20 );
}



/**
 *  Media Shorcodes
 *
 *  @since    2.8
 *  @created  10/2020
 */

if ( wpuxss_eml_enhance_media_shortcodes() ) {

    /**
     *  Enfold Theme
     *  for [av_masonry_gallery] shortcode
     *
     *  Use Default Layout and choose the shortcode Media Elements > Masonry Gallery 
     *  to make theme gallery shows images from the specific category.
     *
     *  @since    2.8
     *  @created  9/10/20
     */

    $wp_theme = wp_get_theme();

    if ( ! empty( $wp_theme ) ) {

        $wp_parent_theme = $wp_theme->parent();

        if ( ! empty( $wp_parent_theme ) ) {
            $wp_theme = $wp_parent_theme;
        }

        if ( 'Enfold' === $wp_theme->get( 'Name' ) && version_compare( $wp_theme->get( 'Version' ), '4.8.4', '>=') ) {

            add_filter( 'shortcode_atts_av_masonry_gallery', 'wpuxss_eml_shortcode_atts', 10, 3 );
        }   
        else {
            add_filter( 'shortcode_atts_av_masonry_entries', 'wpuxss_eml_shortcode_atts', 10, 3 );
        }
    }


    /**
     *  FooGallery
     *
     *  @since    2.8.4
     *  @created  08/04/21
     */

    add_filter( 'foogallery_shortcode_atts', 'wpuxss_eml_foogallery_shortcode_atts' );
}

function wpuxss_eml_foogallery_shortcode_atts( $atts ) {

    $id = isset( $atts['id'] ) ? intval( $atts['id'] ) : 0;
    unset( $atts['id'] );

    $atts = wpuxss_eml_shortcode_atts( array(), array(), $atts );
    $atts['id'] = $id;

    if ( isset( $atts['ids'] ) ) {
        $atts['attachment_ids'] = $atts['ids'];
        unset( $atts['ids'] );
    }

    return $atts;
}
