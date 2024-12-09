<section class="enroll-section">
    <div class="wrapper">
        <?php
        $blocks = get_sub_field( 'blocks' ); 
        foreach( $blocks as $block ) { 
            ?>
            <div class="card">
                <div class="image-wrapper">
                    <img src="<?php echo $block['image']['sizes']['enroll-size']?>" />
                </div>
                <div class="content">
                    <h3><?php echo $block['title']; ?></h3>
                    <?php echo $block['text']; ?>
                    <?php if ( !empty( $block['button_text'] ) ) {
                        echo '<a class="button red" href="' . $block['button_link'] . '" title="' . $block['title'] . '" target="_blank">' . $block['button_text'] . '</a>';
                    } 
                    ?>
                </div>
            </div>
        <?php } ?>
    </div>
</section>