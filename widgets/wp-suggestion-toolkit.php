<?php

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

/**
 * Core class used to implement a Related Posts widget using Relevanssi search engine.
 *
 * @see WP_Widget
 */
class SuggestionToolkit_WP_Widget extends WP_Widget {
	
	/**
	 * Sets up a new Related Posts widget instance.
	 *
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'SuggestionToolkit_WP_Widget',
			'description' => __( 'Recommendations from different sources - blog posts, WooCommerce products, YouTube video, eBay products.' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'SuggestionToolkit_WP_Widget', __( 'Suggestion Toolkit' ), $widget_ops );
		$this->alt_option_name = 'widget_related_entries';

		//$this->name = 
		$this->name = __("Suggestion Toolkit");
	}

	/**
	 * Outputs the content for the current Related Posts widget instance.
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Related Posts widget instance.
	 */
	public function widget( $args, $instance ) {
		global $suggestion_toolkit_init;

		if ( ! isset( $args['widget_id'] ) ) {
			$instance['widget_id'] =  $args['widget_id'] = $this->id;
		}
		
		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Related Posts' );

		echo $args['before_widget']; 
		if ( !empty($title) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		$instance['ptypes'] =  $instance['pe_types'];

		echo $suggestion_toolkit_init->related_posts_shortcode($instance);
		
		echo $args['after_widget']; 

		
	}

	/**
	 * Handles updating the settings for the current Related Posts widget instance.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		//print_r($new_instance); die();
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['keyword'] = sanitize_text_field( $new_instance['keyword'] );
		$instance['style'] = sanitize_text_field( $new_instance['style'] );
		$instance['order'] = sanitize_text_field( $new_instance['order'] );
		$instance['align'] = sanitize_text_field( $new_instance['align'] );
		$instance['width'] = sanitize_text_field( $new_instance['width'] );
		
		$instance['include'] = sanitize_text_field( $new_instance['include'] );
		$instance['exclude'] = sanitize_text_field( $new_instance['exclude'] );

		$instance['pe_types'] = $new_instance['pe_types'];
		//$instance['pe_types'] = array();
		$instance['num'] = array();
		foreach($instance['pe_types'] as $ptype){
			$instance['num'][$ptype] = $new_instance['num'][$ptype];
			//$instance['pe_types'][] = $ptype;
		}
				
		$instance['show_date'] = isset( $new_instance['show_date'] ) ? (bool) $new_instance['show_date'] : false;
		$instance['more'] = isset( $new_instance['more'] ) ? (bool) $new_instance['more'] : false;
		
		return $instance;
	}

	/**
	 * Outputs the settings form for the Related Posts widget.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		global $suggestion_toolkit_init;

		$title		= isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$keyword	= isset( $instance['keyword'] ) ? esc_attr( $instance['keyword'] ) : '';
		$key_source	= isset( $instance['key_source'] ) ? esc_attr( $instance['key_source'] ) : '';
		$style		= isset( $instance['style'] ) ? esc_attr( $instance['style'] ) : '';
		$order		= isset( $instance['order'] ) ? esc_attr( $instance['order'] ) : '';
		$align		= isset( $instance['align'] ) ? esc_attr( $instance['align'] ) : '';
		$width		= isset( $instance['width'] ) ? esc_attr( $instance['width'] ) : '';

		$pe_types	= isset( $instance['pe_types'] ) ? $instance['pe_types']  : array('post');
		$number		= isset( $instance['num'] ) ? $instance['num'] : array('post'=>3);

		$show_date	= isset( $instance['show_date'] ) ? (bool) $instance['show_date'] : false;
		$more		= isset( $instance['more'] ) ? (bool) $instance['more'] : false;

		
		$include     = isset( $instance['include'] ) ? esc_attr( $instance['include'] ) : '';
		$exclude     = isset( $instance['exclude'] ) ? esc_attr( $instance['exclude'] ) : '';
		
		$ptypes = apply_filters('related_posts_post_types', [(object)['value'=>'post', 'label'=>'Posts']]);
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" placeholder="<?php _e( 'Ex: Related', 'suggestion-toolkit' ); ?>"/></p>

		<?php if(count($ptypes)==1){ ?>
		<p><label for="<?php echo $this->get_field_id( 'keyword' ); ?>"><?php _e( 'Keyword:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'keyword' ); ?>" name="<?php echo $this->get_field_name( 'keyword' ); ?>" type="text" value="<?php echo $keyword; ?>"  placeholder="<?php _e( 'Ex: test', 'suggestion-toolkit' ); ?>"/></p>
		<p><?php _e("Install", 'suggestion-toolkit'); ?> <a href="<?php echo $suggestion_toolkit_init->upgrade_link; ?>" target="_blank"><?php echo $suggestion_toolkit_init->conf['plugins']['types_and_automation']; ?></a> <?php _e("plugin to enable suggestions by title and keywords of current post", 'suggestion-toolkit'); ?>.</p>
		<?php }else{ ?>
		<p><label for="<?php echo $this->get_field_id( 'key_source' ); ?>"><?php _e( 'Keyword source:' ); ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'key_source' ); ?>" name="<?php echo $this->get_field_name( 'key_source' ); ?>">
			<?php foreach($suggestion_toolkit_init->conf['key_source'] as $option=>$label){ ?>
			<option <?php echo ($key_source==$option)?"selected":""; ?> value="<?php echo $option; ?>"><?php echo $label; ?></option>
			<?php } ?>
		</select>
		</p>
		<?php } ?>

		<b><?php _e( 'Layout & styles' ); ?></b>
		<hr/>

		<p><label for="<?php echo $this->get_field_id( 'style' ); ?>"><?php _e( 'Style:' ); ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'style' ); ?>" name="<?php echo $this->get_field_name( 'style' ); ?>">
			<?php foreach($suggestion_toolkit_init->conf['style'] as $option=>$label){ ?>
			<option <?php echo ($style==$option)?"selected":""; ?> value="<?php echo $option; ?>"><?php echo $label; ?></option>
			<?php } ?>
		</select>
		</p>

		<p><label for="<?php echo $this->get_field_id( 'order' ); ?>"><?php _e( 'Order:' ); ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'order' ); ?>" name="<?php echo $this->get_field_name( 'order' ); ?>">
			<?php foreach($suggestion_toolkit_init->conf['order'] as $option=>$label){ ?>
			<option <?php echo ($order==$option)?"selected":""; ?> value="<?php echo $option; ?>"><?php echo $label; ?></option>
			<?php } ?>
		</select>
		</p>

		<p><label for="<?php echo $this->get_field_id( 'width' ); ?>"><?php _e( 'Width:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'width' ); ?>" name="<?php echo $this->get_field_name( 'width' ); ?>" type="text" value="<?php echo $width; ?>" placeholder="<?php _e( 'Ex: 100%', 'suggestion-toolkit' ); ?>"/></p>

		<p><label for="<?php echo $this->get_field_id( 'align' ); ?>"><?php _e( 'Align:' ); ?></label>
		<select class="widefat" id="<?php echo $this->get_field_id( 'align' ); ?>" name="<?php echo $this->get_field_name( 'align' ); ?>">
			<?php foreach($suggestion_toolkit_init->conf['align'] as $option=>$label){ ?>
			<option <?php echo ($align==$option)?"selected":""; ?> value="<?php echo $option; ?>"><?php echo $label; ?></option>
			<?php } ?>
		</select>
		</p>
				
		<p><input class="checkbox" type="checkbox"<?php checked( $show_date ); ?> id="<?php echo $this->get_field_id( 'show_date' ); ?>" name="<?php echo $this->get_field_name( 'show_date' ); ?>" />
		<label for="<?php echo $this->get_field_id( 'show_date' ); ?>"><?php _e( 'Display post date' ); ?></label></p>

		<p><input class="checkbox" type="checkbox"<?php checked( $more ); ?> id="<?php echo $this->get_field_id( 'more' ); ?>" name="<?php echo $this->get_field_name( 'more' ); ?>" />
		<label for="<?php echo $this->get_field_id( 'more' ); ?>"><?php _e( 'Show `more` button' ); ?></label></p>

		<b><?php _e( 'Post types' ); ?></b>
		<hr/>

		<?php 
		foreach($ptypes as $ptype){ 
		?>
			<p><input autocomplete="off" <?php if(in_array($ptype->value, $pe_types)){ echo "checked=\"checked\""; } ?> class="checkbox" type="checkbox" id="<?php echo $this->get_field_id( 'pe_types' )."_".$ptype->value; ?>" name="<?php echo $this->get_field_name( 'pe_types' ); ?>[]" value="<?php echo $ptype->value; ?>" onclick=" if(this.checked){ Array.prototype.slice.call(document.querySelectorAll('.sub_<?php echo $ptype->value; ?>')).map(el=>{ el.classList.remove('hidden'); }); }else{ Array.prototype.slice.call(document.querySelectorAll('.sub_<?php echo $ptype->value; ?>')).map(el=>{ el.classList.add('hidden'); }); }"/>
			<label for="<?php echo $this->get_field_id( 'pe_types' )."_".$ptype->value; ?>"><?php echo $ptype->label; ?></label></p>

			<?php if(empty($ptype->custom)){ ?>
			<p class="sub_<?php echo $ptype->value; ?> <?php echo (in_array($ptype->value, $pe_types))?'':'hidden'; ?>"><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:' ); ?></label>
			<input class="tiny-text" id="<?php echo $this->get_field_id( 'num' ); ?>" name="<?php echo $this->get_field_name( 'num' )."_".$ptype->value; ?>[<?php echo $ptype->value; ?>]" type="number" step="1" min="1" value="<?php echo (!empty($number[$ptype->value]))?$number[$ptype->value]:3; ?>" size="3"/></p>
			<?php }else{ ?>
			<p class="sub_<?php echo $ptype->value; ?> <?php echo (in_array($ptype->value, $pe_types))?'':'hidden'; ?>"><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php echo $ptype->custom_name; ?></label>
				<?php if($ptype->custom_type=='number'){ ?>
				<input class="tiny-text" id="<?php echo $this->get_field_id( 'num' ); ?>" name="<?php echo $this->get_field_name( 'num' )."_".$ptype->value; ?>[<?php echo $ptype->value; ?>]" type="number" step="1" min="1" value="<?php echo (!empty($number[$ptype->value]))?$number[$ptype->value]:3; ?>" size="3"/></p>
				<?php } ?>
				<?php if($ptype->custom_type=='select'){ ?>
					<select class="widefat" id="<?php echo $this->get_field_id( 'num' ); ?>" name="<?php echo $this->get_field_name( 'num' )."_".$ptype->value; ?>[<?php echo $ptype->value; ?>]">
					<?php foreach($ptype->custom as $c_val=>$c_label){ ?>
						<option vlaue="<?php echo $c_val; ?>" <?php echo (!empty($number[$ptype->value]) && ($number[$ptype->value]==$c_val))?"selected":""; ?>><?php echo $c_label; ?></option>
					<?php } ?>
					</select>
				<?php } ?>
			<?php } ?>
		<?php } ?>

		<?php if(count($ptypes)==1){ ?>
			<p><?php _e("Install", 'suggestion-toolkit'); ?> <a href="<?php echo $suggestion_toolkit_init->ps->showExtUrl(); ?>" target="_blank"><?php echo $suggestion_toolkit_init->conf['plugins']['types_and_automation']; ?></a> <?php _e("plugin to enable extended post types including products", 'suggestion-toolkit'); ?>.</p>
		<?php } ?>
		<p>&nbsp;</p>
		<b><?php _e( 'Post IDs include/exclude' ); ?></b>
		<hr/>

		<p><label for="<?php echo $this->get_field_id( 'include' ); ?>"><?php _e( 'Include:' ); ?></label>
		<textarea class="widefat" id="<?php echo $this->get_field_id( 'include' ); ?>" name="<?php echo $this->get_field_name( 'include' ); ?>" placeholder="<?php _e( 'Ex: 3, 12, 33', 'suggestion-toolkit' ); ?>"><?php echo $include; ?></textarea></p>

		<p><label for="<?php echo $this->get_field_id( 'exclude' ); ?>"><?php _e( 'Exclude:' ); ?></label>
		<textarea class="widefat" id="<?php echo $this->get_field_id( 'exclude' ); ?>" name="<?php echo $this->get_field_name( 'exclude' ); ?>" placeholder="<?php _e( 'Ex: 3, 12, 33', 'suggestion-toolkit' ); ?>"><?php echo $exclude; ?></textarea></p>
		
<?php
	}
}

// register My_Widget
add_action( 'widgets_init', function(){
	register_widget( 'SuggestionToolkit_WP_Widget' );
});

?>
