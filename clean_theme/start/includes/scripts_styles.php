<?php


class Starter_Scripts_Styles {


    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'scripts_styles' ) );

    }


    public function scripts_styles() {
        wp_enqueue_style( 'style-child', get_stylesheet_directory_uri() . '/style.css' );
        wp_enqueue_script( 'frontend-ajax', get_stylesheet_directory_uri() . '/js.js', array('jquery'), null, true );
        wp_localize_script( 'frontend-ajax', 'frontend_ajax_object',
            array( 
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'data_var_1' => 'value 1',
                'data_var_2' => 'value 2',
            )
        );
    }



}

new Starter_Scripts_Styles();