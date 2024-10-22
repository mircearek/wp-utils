<?php
/*
 * Template Name: Template page
 */

 //the one bellow can be used on simple pages or archives. remove the term_index if normal pages

$object = get_queried_object();
$term_index = $object->taxonomy.'_'.$object->term_id;
if ( isset( $_GET['dev'] ) ) :

    $builder = '';

    if( have_rows('builder', $term_index) ) :
          // loop through the rows of data
        while ( have_rows('builder', $term_index ) ) : the_row();
            get_template_part('parts/archive-parts/'.get_row_layout());
        endwhile;
    else :
        echo 'no rows found';
    endif;

endif;