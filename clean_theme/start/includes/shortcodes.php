<?php


class PROJECT_NAME_Shortcodes {

    public function __construct() {
        add_shortcode( 'myshortcode', array( $this, 'html_gen' ) );

    }

    
    public static function html_gen() {

        ob_start(); ?>

            <p>html here</p>
        <?php 
        
        $html = ob_get_contents();
        ob_end_clean();


        return $html;
    }
}