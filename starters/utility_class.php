<?php

/**
 * work in progress
 */
class PROJECT_UTILITY {


    public function __construct() {


        add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

        add_action(  'wp_ajax_action', array( $this, 'ajax' ) );
        add_action(  'wp_ajax_nopriv_action', array( $this, 'ajax' ) );

        add_shortcode( 'myshortcode', array( $this, 'shortcode' ) );

    }


    function scripts() {
        
        wp_enqueue_script( 'frontend-ajax', get_stylesheet_directory_uri() . '/js.js', array('jquery'), null, true );
        wp_localize_script( 'frontend-ajax', 'frontend_ajax_object',
            array( 
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'data_var_1' => 'value 1',
                'data_var_2' => 'value 2',
            )
        );
    }
    


    public function ajax() {

        $email = sanitize_text_field( $_POST['somemail'] );
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            
            echo json_encode( array( 'status' => 'failed', 'msg' => 'some message' ) );
        }

        exit;
    }



    
    public function shortcode( $atts ) {
        
        
        $html = '';


        return $html;
    }

    /**
     * https://www.php.net/manual/en/function.ob-start.php
     */
    public static function html_gen() {

        ob_start(); ?>

            <p>html here</p>
        <?php 
        
        $html = ob_get_contents();
        ob_end_clean();


        return $html;
    }

    //temp
    function cheat_sheet() {
        $current_user_id = get_current_user_id();

    }
}