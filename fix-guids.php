<?php

require_once( 'wp-load.php' );


$site_url = get_site_url();


$args = array(
    'post_type' => 'post',
    'post_status' => 'any',
    'posts_per_page' => -1, 
    'fields' => 'ids', 
);

$query = new WP_Query($args);

if ($query->have_posts()) {
    foreach ($query->posts as $post_id) {
        $new_guid = $site_url . '/?p=' . $post_id;
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            array('guid' => $new_guid),
            array('ID' => $post_id),    
            array('%s'),               
            array('%d')               
        );

        echo "Updated GUID for post ID $post_id to $new_guid" . "<br />";
    }
} else {
    echo "No posts found.\n";
}

wp_reset_postdata();