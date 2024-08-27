<?php

add_filter('the_content', 'remove_fusion_shortcodes', 20, 1);

function remove_fusion_shortcodes( $content ) {

    if ( is_single() && !is_front_page() && !is_home() ) {

        // Remove the specified shortcodes
        $content = preg_replace('/\[\/?et_pb.*?\]/', '', $content);

        // Ensure responsive images are preserved
        if ( $content ) {
            return preg_replace_callback('/<img[^>]+src=[\"\'](https?:\/\/(.*?)\.(jpg|png|gif)(\?\w+=\w+)?)[\"\'][^>]*>/i', '_callback', $content);
        }

    }
    return $content;
}

function _callback($matches) {
    // Return the entire matched <img> tag as is to preserve responsiveness
    return $matches[0];
}
