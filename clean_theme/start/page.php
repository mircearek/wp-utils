<?php get_header(); 

$sections = get_field( 'sections' );

// check if the flexible content field has rows of data
if ( have_rows( 'sections') ) :

    // loop through the selected ACF layouts and display the matching partial
    while ( have_rows( 'sections') ) : the_row();

        get_template_part( 'partials/flexible-layouts/' . get_row_layout() );
        
        
    endwhile;

elseif ( get_the_content() ) :
    ?>
    <div class="page-title">
        <h1><span><?php the_title(); ?></span></h1>
    </div>
    <section class="report-section contact">
        <div class="report-wrapper">
            <div class="report-container">
                <div class="content-wrapper">
                    <?php the_content(); ?>
                </div>
            </div>
        </div>
    </section>
    <?php 
    

endif;

get_footer();
