<?php


require_once( 'wp-load.php' );
global $wpdb;
$prefix = $wpdb->prefix;
$query = "SELECT meta_value,COUNT(meta_value),GROUP_CONCAT(DISTINCT post_id ORDER BY post_id SEPARATOR ',') post_id
FROM ".$prefix."postmeta
JOIN ".$prefix."posts ON ".$prefix."posts.ID=".$prefix."postmeta.post_id
WHERE meta_key = '_sku'
AND meta_value != ''
GROUP BY meta_value HAVING COUNT(meta_value) > 1";  

$results = $wpdb->get_results( $query );


foreach( $results as $res ) {
    $sku = $res->meta_value;
    echo '<br />Deleting Products with SKU: ' . $sku . ':';
    echo 'Deleting:';
    $ids = explode(',',$res->post_id);
    foreach($ids as $id) {
        wp_delete_post( $id, true );
        
        echo $id . '<br />'; 
    }
}

