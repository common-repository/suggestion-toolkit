<?php 

$thumb_width = empty(get_option('suggestion_toolkit_thumb_width'))?"100px":get_option('suggestion_toolkit_thumb_width')."px";
$thumb_height = empty(get_option('suggestion_toolkit_thumb_height'))?"100px":get_option('suggestion_toolkit_thumb_height')."px";
$thumb_cover = empty(get_option('suggestion_toolkit_thumb_cover'))?'contain':get_option('suggestion_toolkit_thumb_cover');
$title_font_size = empty(get_option('suggestion_toolkit_title_font_size'))?"14px":get_option('suggestion_toolkit_title_font_size')."px";
$layout_style = get_option('suggestion_toolkit_layout_style');
$id = mt_rand();
?>

<?php if($cfg['auto_type']=='popup'){ ?>
<div class="sggtool-wrap-popup hidden" id="sggtool-wrap-popup">
<?php } ?>

<div class="sggtool-header">
	<?php if($cfg['auto_type']=='popup'){ ?>
	<a class="close" href="#" id="sggtool-wrap-popup-close"><span class="dashicons dashicons-no-alt"></span></a>
	<?php } ?>
	<?php if(!empty($cfg['title'])){ ?><b><?php echo $cfg['title']; ?></b><?php } ?>
	
	<?php if(in_array($cfg['style'], ['thumb-row-scroll'])){ ?>
	<div></div>
	<div class="sggtool-scroll">
		<a class="sggtool-scroll-left" href="#" onclick="sggtool_scrollLeft('sggtool-wrap<?php echo $id; ?>'); return false;"><i class="fa fa-arrow-left" aria-hidden="true"></i></a>
		<a class="sggtool-scroll-right" href="#" onclick="sggtool_scrollRight('sggtool-wrap<?php echo $id; ?>'); return false;"><i class="fa fa-arrow-right" aria-hidden="true"></i></a>
	</div>
	<?php } ?>
	
</div>
<div id='sggtool-wrap<?php echo $id; ?>' class="sggtool-wrap <?php echo $cfg['align']."".$layout_style." ".$cfg['style']." ".((!empty($cfg['auto_type']))?$cfg['auto_type']:""); ?>" style="--cfg-num: <?php echo $cfg['number']; ?>; --cfg-width: <?php echo $cfg['width']; ?>; --cfg-thumb-width: <?php echo $thumb_width; ?>; --cfg-thumb-height: <?php echo $thumb_height; ?>;  --cfg-thumb-cover: <?php echo $thumb_cover; ?>; --cfg-title-font-size: <?php echo $title_font_size; ?>; --cfg-num_items: <?php echo $cfg['num_items']; ?>" data-sort-order="<?php echo (!empty($cfg['order']))?$cfg['order']:'random'; ;?>">
	<?php echo $cnt_row; ?>
    <?php if ( $cfg['more'] == 1) { ?>
    <div class="sggtool-cell sggtool-more-cell">
        <a href="<?php echo get_site_url(); ?>?s=<?php echo $cfg['keyword']; ?>&post_type[]=<?php echo  implode("&post_type[]=", $cfg['ptypes']); ?>" alt="<?php _e("More", 'related-posts-with-relevanssi'); ?>" title="<?php _e("More", 'related-posts-with-relevanssi'); ?>">
            <span class="sggtool-more-text"><?php _e("More", 'related-posts-with-relevanssi'); ?></span>
            <svg class="sggtool-more-icon" fill="currentColor" xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"><path d="M5 3l3.057-3 11.943 12-11.943 12-3.057-3 9-9z"/></svg>
        </a>
    </div>
    <?php } ?>
</div>
<?php if($cfg['auto_type']=='popup'){ ?>
</div>
<?php } ?>
