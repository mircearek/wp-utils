<?php

class Starter_Utils {


    public function __construct() {
        add_action( 'after_setup_theme', array( $this, 'menus_register' ), 0 );

        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'title-tag' );
        //add_image_size( 'custom-size', 220, 180, true ); 
    }

    function menus_register(){
        register_nav_menus( array(
            'header_menu'   => __( 'Header Menu', 'starter' ),
            'footer_menu'   => __( 'Footer Menu', 'starter' ),
        ) );
    }
    
}
new Starter_Utils();