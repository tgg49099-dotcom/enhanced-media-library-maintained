<?php
/*
Plugin Name: Enhanced Media Library
Plugin URI: https://wpUXsolutions.com/plugins/enhanced-media-library
Description: This plugin will be handy for those who need to manage a lot of media files.
Version: 2.9.4
Author: wpUXsolutions
Author URI: http://wpUXsolutions.com
Text Domain: enhanced-media-library
Domain Path: /languages
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Copyright 2013-2024 wpUXsolutions  (email : wpUXsolutions@gmail.com)
*/



if ( ! defined( 'ABSPATH' ) )
    exit;



if ( ! defined('EML_VERSION') ) define( 'EML_VERSION', '2.9.4' );



global $wpuxss_eml_dir,
       $wpuxss_eml_slug,
       $wpuxss_eml_filename,
       $wpuxss_eml_basename;



if ( ! function_exists( 'wpuxss_get_eml_slug' ) ) {


    /**
     *  wpuxss_get_eml_slug
     *
     *  keeping the name for older versions compatibility
     * 
     *  @since    2.1
     *  @since    2.9 modified
     *  @created  27/10/15
     */

    function wpuxss_get_eml_slug() {

        $path_array = explode( '/', dirname( __FILE__ ) );
        $slug = end( $path_array );

        return $slug;
    }



    /**
     *  wpuxss_get_eml_basename
     *
     *  @since    2.1
     *  @since    2.8.16 modified
     *  @created  27/10/15
     */

    function wpuxss_get_eml_basename() {

        global $wpuxss_eml_basename;

        return $wpuxss_eml_basename;
    }



    /**
     *  wpuxss_eml_enhance_media_shortcodes
     *
     *  @since    2.1.4
     *  @created  08/01/16
     */

    function wpuxss_eml_enhance_media_shortcodes() {

        $wpuxss_eml_lib_options = get_option( 'wpuxss_eml_lib_options', array() );

        $enhance_media_shortcodes = isset( $wpuxss_eml_lib_options['enhance_media_shortcodes'] ) ? (bool) $wpuxss_eml_lib_options['enhance_media_shortcodes'] : false;

        return $enhance_media_shortcodes;
    }



    /**
     *  wpuxss_eml_enable_infinite_scrolling
     *
     *  @since    2.9
     *  @created  09/2021
     */

    function wpuxss_eml_enable_infinite_scrolling() {

        $wpuxss_eml_lib_options = get_option( 'wpuxss_eml_lib_options', array() );

        $enable_infinite_scrolling = isset( $wpuxss_eml_lib_options['infinite_scrolling'] ) ? (bool) $wpuxss_eml_lib_options['infinite_scrolling'] : false;

        return $enable_infinite_scrolling;
    }



    /**
     *  wpuxss_eml_on_activation
     *
     *  @since    2.7
     *  @created  31/08/18
     */

    register_activation_hook( __FILE__, 'wpuxss_eml_on_activation' );

    function wpuxss_eml_on_activation() {

        // per site
        // main site if multisite
        wpuxss_eml_set_options();


        if ( is_multisite() && is_network_admin() ) {

            // network options
            wpuxss_eml_set_network_options();

            // common (site) options
            do_action( 'wpuxss_eml_set_site_options' );
        }
    }



    /**
     *  wpuxss_eml_on_plugins_loaded
     *
     *  @since    2.6.1
     *  @since    2.9 modified
     *  @created  20/05/18
     */

    add_action( 'plugins_loaded', 'wpuxss_eml_on_plugins_loaded' );

    function wpuxss_eml_on_plugins_loaded() {

        global $wpuxss_eml_dir,
               $wpuxss_eml_slug,
               $wpuxss_eml_filename,
               $wpuxss_eml_basename;


        $wpuxss_eml_dir      = plugin_dir_url( __FILE__ );
        $wpuxss_eml_slug     = wpuxss_get_eml_slug();
        $wpuxss_eml_filename = basename( __FILE__ );
        $wpuxss_eml_basename = $wpuxss_eml_slug . '/' . $wpuxss_eml_filename;


        // on update
        if ( EML_VERSION !== get_option( 'wpuxss_eml_version', '' ) ) {
            wpuxss_eml_on_activation();
            update_option( 'wpuxss_eml_version', EML_VERSION );
            delete_site_transient( 'eml_transient' );
        }


        // plugin action links
        add_filter( 
            'plugin_action_links_' . wpuxss_get_eml_basename(),
            'wpuxss_eml_settings_link', 10, 4 
        );
        add_filter( 
            'network_admin_plugin_action_links_' . wpuxss_get_eml_basename(),
            'wpuxss_eml_settings_link', 10, 4 
        );

        add_filter( 'plugin_row_meta', 'wpuxss_eml_plugin_row_meta', 10, 4 );
    }



    /**
     *  wpuxss_eml_on_init
     *
     *  @since    1.0
     *  @created  03/08/13
     */

    add_action( 'init', 'wpuxss_eml_on_init', 12 );

    function wpuxss_eml_on_init() {

        $wpuxss_eml_taxonomies = get_option( 'wpuxss_eml_taxonomies', array() );
        $wpuxss_eml_tax_options = get_option( 'wpuxss_eml_tax_options', array() );

        // register eml taxonomies
        foreach ( (array) $wpuxss_eml_taxonomies as $taxonomy => $params ) {

            if ( $params['eml_media'] && ! empty( $params['labels']['singular_name'] ) && ! empty( $params['labels']['name'] ) ) {

                $labels = array_map( 'sanitize_text_field', $params['labels'] );

                if ( (bool) $wpuxss_eml_tax_options['tax_archives'] ) {

                    $rewrite = array(
                        'slug' => wpuxss_eml_sanitize_slug( $params['rewrite']['slug'], $taxonomy ),
                        'with_front' => (bool) $params['rewrite']['with_front']
                    );
                    $public = true;
                }
                else {
                    $rewrite = $public = false;
                }

                register_taxonomy(
                    $taxonomy,
                    'attachment',
                    array(
                        'labels'                => $labels,
                        'public'                => $public,
                        'show_ui'               => true,
                        'show_admin_column'     => (bool) $params['show_admin_column'],
                        'hierarchical'          => (bool) $params['hierarchical'],
                        'update_count_callback' => '_eml_update_attachment_term_count',
                        'sort'                  => (bool) $params['sort'],
                        'show_in_rest'          => (bool) $params['show_in_rest'],
                        'query_var'             => sanitize_key( $taxonomy ),
                        'rewrite'               => $rewrite
                    )
                );
            }
        } // endforeach
    }



    /**
     *  wpuxss_eml_on_wp_loaded
     *
     *  @since    1.0
     *  @created  03/11/13
     */

    add_action( 'wp_loaded', 'wpuxss_eml_on_wp_loaded' );

    function wpuxss_eml_on_wp_loaded() {

        global $wp_taxonomies;

        $wpuxss_eml_taxonomies = get_option( 'wpuxss_eml_taxonomies', array() );
        $taxonomies = get_taxonomies( array(), 'object' );

        // discover 'foreign' taxonomies
        foreach ( $taxonomies as $taxonomy => $params ) {

            if ( ! empty( $params->object_type ) && ! array_key_exists( $taxonomy, $wpuxss_eml_taxonomies ) &&
                 ! in_array( 'revision', $params->object_type ) &&
                 ! in_array( 'nav_menu_item', $params->object_type ) &&
                 $taxonomy !== 'wp_theme' &&
                 $taxonomy !== 'post_format' &&
                 $taxonomy !== 'link_category' &&
                 $taxonomy !== 'wp_pattern_category' &&
                 $taxonomy !== 'wp_template_part_area' ) {

                $wpuxss_eml_taxonomies[$taxonomy] = array(
                    'eml_media' => 0,
                    'admin_filter' => 1, // since 2.7
                    'media_uploader_filter' => 1, // since 2.7
                    'media_popup_taxonomy_edit' => 0,
                    'taxonomy_auto_assign' => 0
                );

                if ( in_array('attachment',$params->object_type) )
                    $wpuxss_eml_taxonomies[$taxonomy]['assigned'] = 1;
                else
                    $wpuxss_eml_taxonomies[$taxonomy]['assigned'] = 0;
            }
        }

        // assign/unassign taxonomies to atachment
        foreach ( $wpuxss_eml_taxonomies as $taxonomy => $params ) {

            $taxonomy = sanitize_key($taxonomy);

            if ( (bool) $params['assigned'] )
                register_taxonomy_for_object_type( $taxonomy, 'attachment' );

            if ( ! (bool) $params['assigned'] )
                unregister_taxonomy_for_object_type( $taxonomy, 'attachment' );
        }


        /**
         *  Clean up update_count_callback
         *  Set custom update_count_callback for post type
         *
         *  @since 2.3
         */
        foreach ( $taxonomies as $taxonomy => $params ) {

            if ( in_array( 'attachment', $params->object_type ) &&
                 isset( $wp_taxonomies[$taxonomy]->update_count_callback ) &&
                 '_update_generic_term_count' === $wp_taxonomies[$taxonomy]->update_count_callback ) {

                unset( $wp_taxonomies[$taxonomy]->update_count_callback );
            }

            if ( in_array( 'post', $params->object_type ) ) {

                if ( in_array( 'attachment', $params->object_type ) )
                    $wp_taxonomies[$taxonomy]->update_count_callback = '_eml_update_post_term_count';
                else
                    unset( $wp_taxonomies[$taxonomy]->update_count_callback );
            }
        }

        update_option( 'wpuxss_eml_taxonomies', $wpuxss_eml_taxonomies );
    }



    /**
     *  wpuxss_eml_admin_enqueue_scripts
     *
     *  @since    1.1.1
     *  @created  07/04/14
     */

    add_action( 'admin_enqueue_scripts', 'wpuxss_eml_admin_enqueue_scripts' );

    function wpuxss_eml_admin_enqueue_scripts() {

        global $wpuxss_eml_dir,
               $current_screen;


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

        $wpuxss_eml_lib_options = get_option( 'wpuxss_eml_lib_options' );


        // admin styles
        wp_enqueue_style(
            'wpuxss-eml-admin-custom-style',
            $wpuxss_eml_dir . 'css/eml-admin.css',
            false,
            EML_VERSION,
            'all'
        );
        wp_style_add_data( 'wpuxss-eml-admin-custom-style', 'rtl', 'replace' );

        // media styles
        wp_enqueue_style(
            'wpuxss-eml-admin-media-style',
            $wpuxss_eml_dir . 'css/eml-admin-media.css',
            false,
            EML_VERSION,
            'all'
        );
        wp_style_add_data( 'wpuxss-eml-admin-media-style', 'rtl', 'replace' );


        wp_enqueue_style ( 'wp-jquery-ui-dialog' );


        // admin scripts
        wp_enqueue_script(
            'wpuxss-eml-admin-script',
            $wpuxss_eml_dir . 'js/eml-admin.js',
            array( 'jquery', 'jquery-ui-dialog', 'underscore' ),
            EML_VERSION,
            true
        );

        $admin_l10n = array(
            'admin_notice_nonce' => wp_create_nonce( 'eml-admin-notice-nonce' )
        );

        wp_localize_script(
            'wpuxss-eml-admin-script',
            'wpuxss_eml_admin_l10n',
            $admin_l10n
        );


        // scripts for list view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'list' === $media_library_mode ) {

            wp_enqueue_script(
                'wpuxss-eml-media-list-script',
                $wpuxss_eml_dir . 'js/eml-media-list.js',
                array('jquery'),
                EML_VERSION,
                true
            );

            $media_list_l10n = array(
                '$_GET'             => wp_json_encode($_GET),
                'uncategorized'     => __( 'All Uncategorized', 'enhanced-media-library' ),
                'reset_all_filters' => __( 'Reset All Filters', 'enhanced-media-library' ),
                'filters_to_show'   => $wpuxss_eml_lib_options ? array_map( 'sanitize_key', $wpuxss_eml_lib_options['filters_to_show'] ) : array(
                    'types',
                    'dates',
                    'taxonomies'
                )
            );

            wp_localize_script(
                'wpuxss-eml-media-list-script',
                'wpuxss_eml_media_list_l10n',
                $media_list_l10n
            );
        }


        // scripts for grid view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'grid' === $media_library_mode ) {

            wp_dequeue_script( 'media' );
        }
    }



    /**
     *  wpuxss_eml_register_scripts
     * 
     *  @todo :: make a separate one for Elementor if need be
     *
     *  @since    2.9.1
     *  @created  2024/05
     */

    add_action( 'wp_loaded', 'wpuxss_eml_register_scripts' );

    function wpuxss_eml_register_scripts() {

        global $wpuxss_eml_dir;


        $suffix = defined( 'EML_SCRIPT_DEBUG' ) ? '' : '.min';
        $rpath  = defined( 'EML_SCRIPT_DEBUG' ) ? 'js/source/' : 'js/';

        wp_register_script(
            'wpuxss-eml-media-views-script',
            $wpuxss_eml_dir . $rpath . 'eml-media-views' . $suffix . '.js',
            array('media-views'),
            EML_VERSION,
            true
        );

        wp_register_script(
            'wpuxss-eml-taxonomies-options-script',
            $wpuxss_eml_dir . $rpath . 'eml-taxonomies-options' . $suffix . '.js',
            array( 'jquery', 'underscore', 'wpuxss-eml-admin-script' ),
            EML_VERSION,
            true
        );
    }



    /**
     *  wpuxss_eml_enqueue_media
     *
     *  @since    2.0
     *  @created  04/09/14
     */

    add_action( 'wp_enqueue_media', 'wpuxss_eml_enqueue_media' );

    function wpuxss_eml_enqueue_media() {

        global $wpuxss_eml_dir,
               $current_screen;


        if ( ! is_admin() ) {
            return;
        }


        $media_library_mode = get_user_option( 'media_library_mode', get_current_user_id() ) ? get_user_option( 'media_library_mode', get_current_user_id() ) : 'grid';

        $wpuxss_eml_lib_options = get_option( 'wpuxss_eml_lib_options' );
        $wpuxss_eml_taxonomies = get_option( 'wpuxss_eml_taxonomies', array() );
        $media_taxonomies = get_object_taxonomies( 'attachment','object' );
        $media_taxonomy_names = array_keys( $media_taxonomies );

        $media_taxonomies_ready_for_script = array();
        $filter_taxonomy_names_ready_for_script = array();
        $compat_taxonomies_to_hide = array();


        $terms = get_terms( $media_taxonomy_names, array('fields'=>'all','get'=>'all') );
        $terms_id_tt_id_ready_for_script = wpuxss_eml_get_media_term_pairs( $terms, 'id=>tt_id' );


        $users_ready_for_script = array();

        if( current_user_can( 'manage_options' ) && $wpuxss_eml_lib_options ) {

            if ( in_array( 'authors', $wpuxss_eml_lib_options['filters_to_show'] ) ) {

                foreach( get_users( array( 'capability' => 'upload_files' ) ) as $user ) {
                    $users_ready_for_script[] = array(
                        'user_id' => $user->ID,
                        'user_name' => $user->data->display_name
                    );
                }
            }
        }


        if ( function_exists( 'wp_terms_checklist' ) ) {

            foreach ( $media_taxonomies as $taxonomy ) {

                $taxonomy_terms = array();


                ob_start();

                    wp_terms_checklist( 0, array( 'taxonomy' => $taxonomy->name, 'checked_ontop' => false, 'walker' => new Walker_Media_Taxonomy_Uploader_Filter() ) );

                    $html = '';
                    if ( ob_get_contents() != false ) {
                        $html = ob_get_contents();
                    }

                ob_end_clean();


                $html = str_replace( '}{', '},{', $html );
                $html = '[' . $html . ']';
                $taxonomy_terms = json_decode( $html, true );

                $media_taxonomies_ready_for_script[$taxonomy->name] = array(
                    'singular_name' => $taxonomy->labels->singular_name,
                    'plural_name'   => $taxonomy->labels->name,
                    'term_list'     => $taxonomy_terms,
                );


                if ( (bool) $wpuxss_eml_taxonomies[$taxonomy->name]['media_uploader_filter'] ) {
                    $filter_taxonomy_names_ready_for_script[] = $taxonomy->name;
                }

                if ( ! (bool) $wpuxss_eml_taxonomies[$taxonomy->name]['media_popup_taxonomy_edit'] ) {
                    $compat_taxonomies_to_hide[] = $taxonomy->name;
                }
            } // foreach
        }


        // generic scripts

        wp_enqueue_script(
            'wpuxss-eml-media-models-script',
            $wpuxss_eml_dir . 'js/eml-media-models.js',
            array('media-models'),
            EML_VERSION,
            true
        );

        wp_enqueue_script( 'wpuxss-eml-media-views-script' );


        // TODO:
        // wp_enqueue_script(
        //     'wpuxss-eml-tags-box-script',
        //     '/wp-admin/js/tags-box.js',
        //     array(),
        //     EML_VERSION,
        //     true
        // );


        $media_models_l10n = array(
            'media_orderby'   => $wpuxss_eml_lib_options ? sanitize_text_field( $wpuxss_eml_lib_options['media_orderby'] ) : 'date',
            'media_order'     => $wpuxss_eml_lib_options ? strtoupper( sanitize_text_field( $wpuxss_eml_lib_options['media_order'] ) ) : 'DESC',
            'bulk_edit_nonce' => wp_create_nonce( 'eml-bulk-edit-nonce' ),
            'natural_sort'    => (bool) $wpuxss_eml_lib_options['natural_sort'],
            'loads_per_page'  => (int) $wpuxss_eml_lib_options['loads_per_page']
        );

        wp_localize_script(
            'wpuxss-eml-media-models-script',
            'wpuxss_eml_media_models_l10n',
            $media_models_l10n
        );


        $media_views_l10n = array(
            'terms'                     => $terms_id_tt_id_ready_for_script,
            'taxonomies'                => $media_taxonomies_ready_for_script,
            'filter_taxonomies'         => $filter_taxonomy_names_ready_for_script,
            'compat_taxonomies'         => $media_taxonomy_names,
            'compat_taxonomies_to_hide' => $compat_taxonomies_to_hide,
            'is_tax_compat'             => count( $media_taxonomy_names ) - count( $compat_taxonomies_to_hide ) > 0 ? 1 : 0,
            'force_filters'             => (bool) $wpuxss_eml_lib_options['force_filters'],
            'filter_uploaded'           => (bool) $wpuxss_eml_lib_options['filter_uploaded'],
            'filters_to_show'           => $wpuxss_eml_lib_options ? array_map( 'sanitize_key', $wpuxss_eml_lib_options['filters_to_show'] ) : array(
                'types',
                'dates',
                'taxonomies'
            ),
            'users'                     => $users_ready_for_script,
            'uncategorized'             => __( 'All Uncategorized', 'enhanced-media-library' ),
            'filter_by'                 => __( 'Filter by', 'enhanced-media-library' ),
            'in'                        => __( 'All', 'enhanced-media-library' ),
            'not_in'                    => __( 'Not in', 'enhanced-media-library' ),
            'reset_filters'             => __( 'Reset All Filters', 'enhanced-media-library' ),
            'author'                    => __( 'author', 'enhanced-media-library' ),
            'authors'                   => __( 'authors', 'enhanced-media-library' ),
            'current_screen'            => isset( $current_screen ) ? $current_screen->id : '',

            'saveButton_success'        => __( 'Saved.', 'enhanced-media-library' ),
            'saveButton_failure'        => __( 'Something went wrong.', 'enhanced-media-library' ),
            'saveButton_text'           => __( 'Save Changes', 'enhanced-media-library' ),

            'select_all'                => __( 'Select All', 'enhanced-media-library' ),
            'deselect'                  => __( 'Deselect ', 'enhanced-media-library'),
            'grid_sidebar_width'        => (int) $wpuxss_eml_lib_options['grid_sidebar_width'],
            'ideal_column_width'        => (int) $wpuxss_eml_lib_options['ideal_column_width'],
        );

        wp_localize_script(
            'wpuxss-eml-media-views-script',
            'wpuxss_eml_mvln',
            $media_views_l10n
        );


        if ( wpuxss_eml_enhance_media_shortcodes() ) {

            wp_enqueue_script(
                'wpuxss-eml-enhanced-medialist-script',
                $wpuxss_eml_dir . 'js/eml-enhanced-medialist.js',
                array('media-views'),
                EML_VERSION,
                true
            );

            wp_enqueue_script(
                'wpuxss-eml-media-editor-script',
                $wpuxss_eml_dir . 'js/eml-media-editor.js',
                array('media-editor','media-views', 'wpuxss-eml-enhanced-medialist-script'),
                EML_VERSION,
                true
            );

            $enhanced_medialist_l10n = array(
                'uploaded_to' => __( 'Uploaded to post #', 'enhanced-media-library' ),
                'based_on' => __( 'Based On', 'enhanced-media-library' )
            );

            wp_localize_script(
                'wpuxss-eml-enhanced-medialist-script',
                'wpuxss_eml_enhanced_medialist_l10n',
                $enhanced_medialist_l10n
            );
        }


        // scripts for grid view :: /wp-admin/upload.php
        if ( isset( $current_screen ) && 'upload' === $current_screen->base && 'grid' === $media_library_mode ) {

            wp_enqueue_script(
                'wpuxss-eml-media-grid-script',
                $wpuxss_eml_dir . 'js/eml-media-grid.js',
                array( 'media-grid'),
                EML_VERSION,
                true
            );

            wp_enqueue_script(
                'wpuxss-eml-media-script',
                $wpuxss_eml_dir . 'js/eml-media.js',
                array( 'media-grid' ),
                EML_VERSION,
                true
            );
            
            $media_grid_l10n = array(
                'grid_show_caption' => (int) $wpuxss_eml_lib_options['grid_show_caption'],
                'grid_caption_type' => isset( $wpuxss_eml_lib_options['grid_caption_type'] ) ? sanitize_key( $wpuxss_eml_lib_options['grid_caption_type'] ) : 'title',
                'ideal_column_width' => (int) $wpuxss_eml_lib_options['ideal_column_width'],
                'more_details' => __( 'More Details', 'enhanced-media-library' ),
                'less_details' => __( 'Less Details', 'enhanced-media-library' )
            );

            wp_localize_script(
                'wpuxss-eml-media-grid-script',
                'wpuxss_eml_media_grid_l10n',
                $media_grid_l10n
            );
        }
    }



    /**
     *  wpuxss_eml_set_options
     *
     *  @since    2.6
     *  @created  02/05/18
     */

    function wpuxss_eml_set_options() {

        $wpuxss_eml_taxonomies  = get_option( 'wpuxss_eml_taxonomies' );
        $wpuxss_eml_lib_options = get_option( 'wpuxss_eml_lib_options', array() );
        $wpuxss_eml_tax_options = get_option( 'wpuxss_eml_tax_options', array() );


        // taxonomies
        if ( false === $wpuxss_eml_taxonomies ) {

            $wpuxss_eml_taxonomies = array(

                'media_category' => array(
                    'assigned' => 1,
                    'eml_media' => 1,

                    'labels' => array(
                        'name' => __( 'Media Categories', 'enhanced-media-library' ),
                        'singular_name' => __( 'Media Category', 'enhanced-media-library' ),
                        'menu_name' => __( 'Media Categories', 'enhanced-media-library' ),
                        'all_items' => __( 'All Media Categories', 'enhanced-media-library' ),
                        'edit_item' => __( 'Edit Media Category', 'enhanced-media-library' ),
                        'view_item' => __( 'View Media Category', 'enhanced-media-library' ),
                        'update_item' => __( 'Update Media Category', 'enhanced-media-library' ),
                        'add_new_item' => __( 'Add New Media Category', 'enhanced-media-library' ),
                        'new_item_name' => __( 'New Media Category Name', 'enhanced-media-library' ),
                        'parent_item' => __( 'Parent Media Category', 'enhanced-media-library' ),
                        'parent_item_colon' => __( 'Parent Media Category:', 'enhanced-media-library' ),
                        'search_items' => __( 'Search Media Categories', 'enhanced-media-library' )
                    ),

                    'hierarchical' => 1,

                    'show_admin_column' => 1,
                    'admin_filter' => 1,          // list view filter
                    'media_uploader_filter' => 1, // grid view filter
                    'media_popup_taxonomy_edit' => 0, // since 2.7

                    'sort' => 0,
                    'show_in_rest' => 0,
                    'rewrite' => array(
                        'slug' => 'media_category',
                        'with_front' => 1
                    )
                )
            );
        }

        // false !== $wpuxss_eml_taxonomies
        else {

            $media_taxonomy_args_defaults = array(
                'assigned' => 1,
                'eml_media' => 1,
                'labels' => array(),

                'hierarchical' => 1,
                'show_admin_column' => 1,
                'admin_filter' => 1,          // list view filter
                'media_uploader_filter' => 1, // grid view filter
                'media_popup_taxonomy_edit' => 0, // since 2.7

                'sort' => 0,
                'show_in_rest' => 0,
                'rewrite' => array(
                    'slug' => '',
                    'with_front' => 1
                )
            );

            $non_media_taxonomy_args_defaults = array(
                'assigned' => 0,
                'eml_media' => 0,
                'admin_filter' => 1, // since 2.7
                'media_uploader_filter' => 1, // since 2.7
                'media_popup_taxonomy_edit' => 0,
                'taxonomy_auto_assign' => 0
            );


            foreach( $wpuxss_eml_taxonomies as $taxonomy => $params ) {

                if ( empty( $params['eml_media'] ) ) {
                    $wpuxss_eml_taxonomies[$taxonomy]['eml_media'] = 0;
                }

                $defaults = (bool) $wpuxss_eml_taxonomies[$taxonomy]['eml_media'] ? $media_taxonomy_args_defaults : $non_media_taxonomy_args_defaults;

                $taxonomy_params = array_intersect_key( $params, $defaults );
                $wpuxss_eml_taxonomies[$taxonomy] = array_merge( $defaults, $taxonomy_params );

                if ( (bool) $wpuxss_eml_taxonomies[$taxonomy]['eml_media'] && empty( $params['rewrite']['slug'] ) ) {
                    $wpuxss_eml_taxonomies[$taxonomy]['rewrite']['slug'] = $taxonomy;
                }
            } // foreach
        } // if

        update_option( 'wpuxss_eml_taxonomies', $wpuxss_eml_taxonomies );


        // media library options
        $eml_lib_options_defaults = array(
            'enhance_media_shortcodes' => isset( $wpuxss_eml_tax_options['enhance_media_shortcodes'] ) ? (bool) $wpuxss_eml_tax_options['enhance_media_shortcodes'] : ( isset( $wpuxss_eml_tax_options['enhance_gallery_shortcode'] ) ? (bool) $wpuxss_eml_tax_options['enhance_gallery_shortcode'] : 0 ),
            'media_orderby' => isset( $wpuxss_eml_tax_options['media_orderby'] ) ? sanitize_text_field( $wpuxss_eml_tax_options['media_orderby'] ) : 'date',
            'media_order' => isset( $wpuxss_eml_tax_options['media_order'] ) ? strtoupper( sanitize_text_field( $wpuxss_eml_tax_options['media_order'] ) ) : 'DESC',
            'natural_sort' => 0,
            'force_filters' => isset( $wpuxss_eml_tax_options['force_filters'] ) ? (bool) $wpuxss_eml_tax_options['force_filters'] : 1,
            'filters_to_show' => array(
                'types',
                'dates',
                'taxonomies'
            ),
            'show_count' => isset( $wpuxss_eml_tax_options['show_count'] ) ? (bool) $wpuxss_eml_tax_options['show_count'] : 1,
            'include_children' => 1,
            'filter_uploaded' => 0,
            'infinite_scrolling' => 0,
            'loads_per_page' => 80,
            'grid_show_caption' => 0,
            'grid_caption_type' => 'title',
            'grid_sidebar_width' => 270,
            'ideal_column_width' => 170,
            'search_in' => array(
                'filenames',
                'titles',
                'captions',
                'descriptions'
            ),
            'search_min_letters' => 2,
            'search_on_enter' => 0,
            'search_auto' => 1
        );

        $wpuxss_eml_lib_options = array_intersect_key( $wpuxss_eml_lib_options, $eml_lib_options_defaults );
        $wpuxss_eml_lib_options = array_merge( $eml_lib_options_defaults, $wpuxss_eml_lib_options );

        // check previous version
        if ( version_compare( get_option( 'wpuxss_eml_version', '' ), '2.8.9', '<=' ) ) {
            // ensure that filenames included in the search by default
            array_push( $wpuxss_eml_lib_options['search_in'], 'filenames' );
        }

        update_option( 'wpuxss_eml_lib_options', $wpuxss_eml_lib_options );


        // taxonomy options
        $eml_tax_options_defaults = array(
            'tax_archives' => 0, // since 2.6
            'edit_all_as_hierarchical' => 0,
            'bulk_edit_save_button' => 0 // since 2.7
        );

        $wpuxss_eml_tax_options = array_intersect_key( $wpuxss_eml_tax_options, $eml_tax_options_defaults );
        $wpuxss_eml_tax_options = array_merge( $eml_tax_options_defaults, $wpuxss_eml_tax_options );

        update_option( 'wpuxss_eml_tax_options', $wpuxss_eml_tax_options );


        // MIME types
        if ( false === get_option( 'wpuxss_eml_mimes' ) ) {

            $allowed_mimes = get_allowed_mime_types();

            foreach ( wp_get_mime_types() as $ext => $type ) {
                $wpuxss_eml_mimes[$ext] = array(
                    'mime'     => $type,
                    'singular' => $type,
                    'plural'   => $type,
                    'filter'   => 0,
                    'upload'   => isset($allowed_mimes[$ext]) ? 1 : 0
                );
            }

            update_option( 'wpuxss_eml_mimes', $wpuxss_eml_mimes );
        }

        if ( version_compare( get_option( 'wpuxss_eml_version', '' ), '2.8.9', '<=' ) ) {
            // getting rid of mime type backup
            delete_site_option( 'wpuxss_eml_mimes_backup' );
        }

        do_action( 'wpuxss_eml_set_options' );
    }



    /**
     *  wpuxss_eml_set_network_options
     *
     *  @since    2.6.3
     *  @created  21/05/18
     */

    function wpuxss_eml_set_network_options() {

        $wpuxss_eml_network_options = get_site_option( 'wpuxss_eml_network_options', array() );

        $wpuxss_eml_network_options_defaults = array(
            'media_settings' => 1,
            'utilities' => 1
        );

        $wpuxss_eml_network_options = array_intersect_key( $wpuxss_eml_network_options, $wpuxss_eml_network_options_defaults );
        $wpuxss_eml_network_options = array_merge( $wpuxss_eml_network_options_defaults, $wpuxss_eml_network_options );

        update_site_option( 'wpuxss_eml_network_options', $wpuxss_eml_network_options );
    }



    /**
     *  wpuxss_eml_settings_link
     *
     *  Add settings link to the plugin action links
     *
     *  @since    2.1
     *  @created  27/10/15
     */

    function wpuxss_eml_settings_link( $actions ) {

        $settings_page = is_network_admin() ? 'settings.php' : 'options-general.php';

        if ( ! is_network_admin() ) {
            $custom_links['settings'] = '<a href="' . self_admin_url($settings_page.'?page=media') . '">' . __( 'Media Settings', 'enhanced-media-library' ) . '</a>';
        }

        $custom_links['utility'] = '<a href="' . self_admin_url($settings_page.'?page=eml-settings') . '">' . __( 'Utilities', 'enhanced-media-library' ) . '</a>';

        return array_merge( $custom_links, $actions );
    }



    /**
     *  wpuxss_eml_plugin_row_meta
     *
     *  @since    2.2.1
     *  @created  11/04/15
     */

    function wpuxss_eml_plugin_row_meta( $plugin_meta, $plugin_file ) {

        if ( wpuxss_get_eml_basename() !== $plugin_file ) {
            return $plugin_meta;
        }

        $plugin_meta[] = '<a href="https://wpuxsolutions.com/documents/enhanced-media-library" target="_blank">' . __( 'Docs', 'enhanced-media-library' ) . '</a>';
        $plugin_meta[] = '<a href="https://wpuxsolutions.com/support" target="_blank">' . __( 'Support', 'enhanced-media-library' ) . '</a>';

        if ( ! defined( 'EML_IS_PRO' ) ) {

            $plugin_meta[] = '<a href="https://wpuxsolutions.com/plugins/enhanced-media-library-pro" class="eml-pro" target="_blank">' . __( 'Go PRO', 'enhanced-media-library' ) . '</a>';
        }

        $plugin_meta[] = '<a href="https://wpuxsolutions.com/plugins/enhanced-media-library-3-0" class="eml-pro" target="_blank">' . __( '3.0 Beta', 'enhanced-media-library' ) . '</a>';

        return $plugin_meta;
    }



    /**
     *  Enable Infinite Scrolling
     *
     *  @since    2.9
     *  @created  09/2021
     */

    if ( wpuxss_eml_enable_infinite_scrolling() ) {
        add_filter( 'media_library_infinite_scrolling', '__return_true' );
    }



    /**
     *  Free functionality
     */

    include_once( 'core/mime-types.php' );
    include_once( 'core/taxonomies.php' );
    include_once( 'core/media-templates.php' );
    include_once( 'core/compatibility.php' );

    if ( wpuxss_eml_enhance_media_shortcodes() ) {
        include_once( 'core/medialist.php' );
    }

    if ( is_admin() ) {
        include_once( 'core/options-pages.php' );
    }

}
