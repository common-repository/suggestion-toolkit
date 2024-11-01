<?php
		foreach ( $r_posts as $post ) { ?>
            <div class="sggtool-cell sggtool-cell-cnt" data-title="<?php echo $post->post_title; ?>" data-date="<?php echo get_the_date( 'U', $post->ID ); ?>">
                <a class="sggtool-post-image" href="<?php the_permalink( $post->ID ); ?>" title="<?php echo $post->post_title; ?>"><div class="sggtool-image" style="background-image: url('<?php echo get_the_post_thumbnail_url($post->ID, 'thumbnail'); ?>');"></div></a>
                <div class="sggtool-post-info">
                    <a class="sggtool-post-title" href="<?php the_permalink( $post->ID ); ?>" title="<?php echo $post->post_title; ?>"><?php echo wp_trim_words($post->post_title, $num_title_words, "..."); ?></a>
                    <?php if ( $cfg['show_date'] == 1) : ?>
                    <span class="sggtool-post-date"><?php echo get_the_date( get_option('date_format'), $post->ID ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php } 
        
		

?>
