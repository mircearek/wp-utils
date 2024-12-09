<?php

class Benny_Theme {


    public function __construct()
    {
        add_action( 'after_setup_theme', array( $this, 'setup' ) ); //themesetup


        //svg support
        add_action( 'admin_head', array( $this, 'fix_svg' ) );
        add_filter( 'upload_mimes', array( $this, 'cc_mime_types' ) );
        add_filter( 'wp_check_filetype_and_ext', array( $this, 'allow_upload' ), 10, 4 );


    }

    public function setup() 
    {
        add_theme_support( 'title-tag' );
    
        register_nav_menus(
            array(
                // 'primary'                   => __( 'Primary Menu', 'benny' ),
   
            )
        );
    
        add_theme_support(
            'html5',
            array(
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            )
        );
    
        add_theme_support( 'custom-logo' );
        add_theme_support( 'post-thumbnails' );

        add_filter('use_block_editor_for_post', '__return_false', 10);

        add_image_size( 'home-hero', 1920 );
       
    
    }
    



    function cc_mime_types( $mimes ){
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
    
      
    function fix_svg() {
        echo '<style type="text/css">
              .attachment-266x266, .thumbnail img {
                   width: 100% !important;
                   height: auto !important;
              }
              </style>';
    }
      

    function allow_upload($data, $file, $filename, $mimes) {

        global $wp_version;
        if ( $wp_version !== '4.7.1' ) {
           return $data;
        }
      
        $filetype = wp_check_filetype( $filename, $mimes );
      
        return [
            'ext'             => $filetype['ext'],
            'type'            => $filetype['type'],
            'proper_filename' => $data['proper_filename']
        ];
      
    }
    

}

new THEMENAME_Theme();


