<?php

/*
Plugin Name: Suggestion Toolkit
Plugin URI: https://erlycoder.com/product/relevant-related-posts/
Description: This WordPress plugin allows to display recomendations of blog posts, WooCommerce products, YouTube videos, eBay products in various layout styles albost any place of WordPress website (some features are enabled via extensions). It includes WordPress widget, shortcode, Gutenberg block and Elementor widget.
Author: Sergiy Dzysyak
Version: 5.0
Author URI: http://erlycoder.com/
*/


// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

require __DIR__ . '/includes/product-service/class.product-service-client.php';

if( !class_exists('SuggestionToolkit') ){
	class SuggestionToolkit {
		public $name = 'suggestion-toolkit';
		public $pluginName = '';
		public $upgrade_link = "https://erlycoder.com/product/relevant-related-posts-advanced/";

		public $conf = [];
		public $generated = false;
		public $urls = [
			'extensions'	=> "admin.php?page=suggestion-toolkit-extensions",
			'settings'		=> "admin.php?page=suggestion-toolkit",
			'support'		=> "https://erlycoder.com/support/",
			'docs'			=> "https://erlycoder.com/knowledgebase_category/related-posts-with-relevanssi/",
			'pluginCat'		=> "suggestion-toolkit-extensions",
		];
		
		/**
		 * Constructor
		 * Sets hooks, actions, shortcodes, filters.
		 *
		 */
		function __construct(){
			if(class_exists('Memcached')){
				$this->mc = new Memcached();
				
				$mc_server = (defined('MEMCACHE_SERVER'))?MEMCACHE_SERVER:'localhost';
				$mc_port = (defined('MEMCACHE_PORT'))?MEMCACHE_PORT:11211;
				$this->mc->addServer($mc_server, $mc_port); 
				$this->memc = 'memcache';
			}elseif(class_exists('Memcache')){ 
				$this->mc = new Memcache; 
				
				$mc_server = (defined('MEMCACHE_SERVER'))?MEMCACHE_SERVER:'localhost';
				$mc_port = (defined('MEMCACHE_PORT'))?MEMCACHE_PORT:11211;
				$this->mc->addServer($mc_server, $mc_port); 
				$this->memc = 'memcache';
			}else{
				$this->memc = 'files';
			}

			$GLOBALS['product-service'][$this->name]['settings'] = $this->urls['settings'];

			$this->memc = (get_option('suggestion_toolkit_cache')=='memcache')?'memcache':'files'; 
			$this->cache_ex = empty(get_option('suggestion_toolkit_cache_expiration'))?900:get_option('suggestion_toolkit_cache_expiration');
			$this->cache_ex2 = $this->cache_ex/2;
			
			load_plugin_textdomain( 'suggestion-toolkit', false, basename( __DIR__ ) . '/languages' );
			add_action( 'init', array( $this, 'init_scripts_and_styles' ) );
			
			$this->conf['align'] = array(
				'left'=>__("Left", 'suggestion-toolkit'), 
				'center'=>__("Center", 'suggestion-toolkit'), 
				'right'=>__("Right", 'suggestion-toolkit'),
			);

			$this->conf['style'] = array(
				'thumb-row'=>__("Thumbs row", 'suggestion-toolkit'), 
				'thumb-row-scroll'=>__("Thumbs row with scroll", 'suggestion-toolkit'), 
				'thumb-column'=>__("Thumbs column", 'suggestion-toolkit'), 
				'txt-row'=>__("Text only row", 'suggestion-toolkit'),
				'txt-column'=>__("Text only column", 'suggestion-toolkit')
			);

			$this->conf['order'] = array(
				'default'=>__("Default", 'suggestion-toolkit'), 
				'random'=>__("Random", 'suggestion-toolkit'), 
				'alpha_asc'=>__("Alphabetical Ascending", 'suggestion-toolkit'), 
				'alpha_desc'=>__("Alphabetical Descending", 'suggestion-toolkit'), 
				'date_asc'=>__("Publish Date Ascending", 'suggestion-toolkit'), 
				'date_desc'=>__("Publish Date Descending", 'suggestion-toolkit'), 
			);
			
			$this->conf['key_source'] = array(
				'title'=>__("Title", 'suggestion-toolkit'), 
				'tag_1'=>__("First tag", 'suggestion-toolkit'), 
				'tag_123'=>__("Tags 1,2,3", 'suggestion-toolkit'), 
			);

			$this->conf['types_exclude'] = ['revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'elementor_library', 'shop_order', 'shop_order_refund', 'ywsbs_subscription', 'wp_block'];
			
			$this->conf['plugins'] = [
				'types_and_automation'=>__("Suggestion Toolkit -  Advanced", 'suggestion-toolkit'),
			];
			
			register_activation_hook( __FILE__, [$this, 'plugin_install']);
			register_deactivation_hook( __FILE__, [$this, 'plugin_uninstall']);

			// Register widget scripts
			//add_action( 'elementor/frontend/after_register_scripts', [ $this, 'widget_scripts' ] );

			// Register elementor widgets
			add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );
			add_shortcode( 'rel_posts', [$this, 'related_posts_shortcode']);

			if(is_admin()){
				add_action('admin_init', array($this, 'admin_init'));
				
				$plugin = plugin_basename( __FILE__ );
				add_filter( "plugin_action_links_$plugin", array( $this, 'plugin_add_settings_link') );
				add_filter( 'plugin_row_meta', [$this, 'plugin_appreciation_links'], 10, 4 );
				add_action( 'admin_menu', [$this, 'extra_admin_menu'] );
			}else{
				add_filter('posts_search', [$this, 'any_word_search_posts_search_filter'], 10, 2);
			}
			
			add_filter('get_rel_posts', [$this, 'related_posts_shortcode'], 10, 1);
			add_filter('get_rec_posts', [$this, 'getRec'], 10, 2);
			
			add_filter('suggestion_toolkit_types_exclude', [$this, 'getTypesExclude'], 10, 1);

			add_filter('suggestion_toolkit_create_link', [$this, 'createShortLink'], 10, 1);
			add_action( 'template_redirect', [$this, 'redirectShortLink'] );
			//add_action( 'template_include', [$this, 'redirectShortLink'] );

			add_action( 'suggestion_toolkit_daily_hook', array($this, 'dailyHook'), 10);
			if (!wp_next_scheduled('suggestion_toolkit_daily_hook')) {
				wp_schedule_event( time(), 'daily', 'suggestion_toolkit_daily_hook');
			}

			$this->ps = new psClient($this->name, $this->name, $this->urls);
		}

		/**
		 * Creates and returns short link
		 * 
		 * @param string $url - long url.
		 * @return string - short url.
		 */
		public function createShortLink($url){
			global $wpdb;

			//if(!str_contains($url, home_url())){
			if(strpos($url, home_url()) === false){
				$wpdb->replace("{$wpdb->prefix}suggestion_toolkit_rewrite", array('URL' => $url, 'LastUsed' => date("Y-m-d H:i:s"),), array('%s', ));
				$short_url = get_site_url()."/".get_option('suggestion_toolkit_rewrite_tag')."/".$wpdb->insert_id."/";
			}else{
				$short_url = $url;
			}

			return $short_url;
		}

		/**
		 * Redirect short link to a proper location
		 * 
		 */
		public function redirectShortLink(){
			global $wpdb;

			// get the value of our rewrite tag
			$longerer = get_query_var( 'longer' );

			// look for the existence of our rewrite tag
			if ( $longerer ){
				
				$mylink = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}suggestion_toolkit_rewrite WHERE ID = '".esc_sql($longerer)."' LIMIT 1");
				if($mylink){
					wp_redirect( $mylink->URL );
				}else{
					wp_redirect( home_url() );	
				}
        		die();

			}

		}

		/**
		 * Daily hook used to remove old URL rewrite rules.
		 * 
		 */
		public function dailyHook(){
			global $wpdb;

			$wpdb->query("DELETE FROM {$wpdb->prefix}suggestion_toolkit_rewrite WHERE LastUsed < NOW() - INTERVAL 1 DAY");
		}
		
		/**
		*	Returns post types that should be excluded from the suggestions & configurations.
		*/
		public function getTypesExclude($types){
			return $this->conf['types_exclude'];
		}
		
		/**
		*	Plugin admin menu
		*/
		public function extra_admin_menu(){
			add_menu_page(__( 'Suggestion Toolkit Settings', 'suggestion-toolkit'), __( 'Suggestion Toolkit', 'suggestion-toolkit'), 'manage_options', 'suggestion-toolkit', [$this, 'basic_settings_page'], 'dashicons-smiley', 56);
			add_submenu_page('suggestion-toolkit', __( 'Basic Settings', 'suggestion-toolkit'), __( 'Settings', 'suggestion-toolkit'),	'manage_options', 'suggestion-toolkit',	[$this, 'basic_settings_page'], 0);
			
			do_action( 'suggestion-toolkit-admin-menu-items');
		}
		
		/**
		*	Plugin settings page
		*/
		public function basic_settings_page(){
			settings_errors('suggestion-toolkit-config-group');
			settings_errors('suggestion-toolkit-inline-group'); 
			
			?>
			<!-- Create a header in the default WordPress 'wrap' container -->
			<div class="wrap">
			
				<h1><?php _e("Suggestion Toolkit", 'suggestion-toolkit'); ?></h1>
				<form method="post" action="options.php">
				<?php
					$post_types = get_post_types([], 'objects');
					$ptypes = []; 
					foreach($post_types as $post_type) if(!in_array($post_type->name, $this->conf['types_exclude'])){ $ptypes[] = (object)['name'=>$post_type->name, 'label'=>$post_type->label, 'disabled'=>false]; }
					apply_filters('related_posts_config_post_types', $ptypes);
					$en_tpypes = (array)get_option( 'suggestion_toolkit_enabled_types' );

					
					settings_fields( 'suggestion-toolkit-config-group' );
					do_settings_sections( 'suggestion-toolkit-config-group' );
				?>
				<h1><?php _e("Post types available for suggestion blocks", 'suggestion-toolkit'); ?></h2>

				<div class="rpwr_admin_info">
					<div>
					<p><?php _e("You should select post types that will be available for suggestion blocks creation from post types regirterred on your website", 'suggestion-toolkit'); ?>.</p>
					<p>
						<a href="<?php echo $this->ps->showExtUrl(); ?>" target="_blank"><?php _e("Upgrade to pro", 'suggestion-toolkit'); ?></a> <?php _e("to enable other than blog 'post' type susggestions, like Woocommerce products or else ", 'suggestion-toolkit'); ?>. 
						<?php _e("Advanced version also includes additional templates and prioritized support and updates", 'suggestion-toolkit'); ?>.
					</p>
					</div>
					<img src="<?php echo plugins_url( 'assets/img/suggest_info.svg', __FILE__ ); ?>" width="400"/>
				</div>

				<table class="form-table">
					<tr>
						<td width="15%"><?php _e("Types of the posts to suggest", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<?php foreach($ptypes as $pt){ ?>
							<input <?php echo in_array($pt->name, $en_tpypes)?'checked':''; if($pt->disabled){ echo " disabled='disabled'"; } ?> autocomplete="off" type="checkbox" name="suggestion_toolkit_enabled_types[]" id="type_<?php echo $pt->name; ?>" value="<?php echo $pt->name; ?>"/><label for="type_<?php echo esc_attr($pt->name); ?>"><?php echo $pt->label; ?></label>
							<?php if(file_exists(plugin_dir_path(__FILE__)."/styles/tpl.{$pt->name}.php")){ echo " ( Template available ) "; } ?>
							<br/>
							<?php } ?>
						</td>
						<td><?php _e("If separate template is not available, than default post template will be used", 'suggestion-toolkit'); ?>.</td>
					</tr>
					<tr>
						<td width="15%"><?php _e("Append suggestions from category", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<input <?php echo get_option('suggestion_toolkit_append_random')?'checked':'';  ?> autocomplete="off" type="checkbox" name="suggestion_toolkit_append_category" id="suggestion_toolkit_append_category" value="1"/>
						</td>
						<td><?php _e("If there is not enough suggestion generated by the keywords, posts from the same category will be appended to the suggestions", 'suggestion-toolkit'); ?>.</td>
					</tr>
					<tr>
						<td width="15%"><?php _e("Append by random", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<input <?php echo get_option('suggestion_toolkit_append_random')?'checked':''; ?> autocomplete="off" type="checkbox" name="suggestion_toolkit_append_random" id="suggestion_toolkit_append_random" value="1"/>
						</td>
						<td><?php _e("If there is not enough suggestion generated by the keywords, random posts will be appended to the suggestions", 'suggestion-toolkit'); ?>.</td>
					</tr>
				</table>
				
				<h2><?php _e("Layout & Styles", 'suggestion-toolkit'); ?></h2>
				
				<table class="form-table">
					<tr>
						<td width="15%"><?php _e("Minimal thumb size", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<input autocomplete="off" type="number" min="0" max="1600" name="suggestion_toolkit_thumb_width" id="suggestion_toolkit_thumb_width" value="<?php echo get_option('suggestion_toolkit_thumb_width'); ?>" style="width: 40%;"/>px x 
							<input autocomplete="off" type="number" min="0" max="1600" name="suggestion_toolkit_thumb_height" id="suggestion_toolkit_thumb_height" value="<?php echo get_option('suggestion_toolkit_thumb_height'); ?>" style="width: 40%;"/>px
						</td>
						<td><?php //_e("", 'suggestion-toolkit'); ?></td>
					</tr>
					<tr>
						<td width="15%"><?php _e("Fit image into thumbnail area", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<select autocomplete="off" name="suggestion_toolkit_thumb_cover" id="suggestion_toolkit_thumb_cover">
								<option <?php echo (get_option('suggestion_toolkit_thumb_cover')=="cover")?"selected=\"selected\"":""; ?> value="cover"><?php _e("Cover", 'suggestion-toolkit'); ?></option>
								<option <?php echo (get_option('suggestion_toolkit_thumb_cover')=="contain")?"selected=\"selected\"":""; ?> value="contain"><?php _e("Contain", 'suggestion-toolkit'); ?></option>
							</select>
						</td>
						<td><?php //_e("", 'suggestion-toolkit'); ?></td>
					</tr>
					<tr>
						<td width="15%"><?php _e("Suggestion title font size", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<input autocomplete="off" type="number" min="6" max="44" name="suggestion_toolkit_title_font_size" id="suggestion_toolkit_title_font_size" value="<?php echo get_option('suggestion_toolkit_title_font_size'); ?>"/>px
						</td>
						<td><?php //_e("", 'suggestion-toolkit'); ?></td>
					</tr>
					<tr>
						<td width="15%"><?php _e("Number of words in titles", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<input autocomplete="off" type="number" name="suggestion_toolkit_title_words" id="suggestion_toolkit_title_words" value="<?php echo get_option('suggestion_toolkit_title_words'); ?>"/>
						</td>
						<td><?php _e("Number of words that will be followed with ellipsis instead of long titles", 'suggestion-toolkit'); ?>.</td>
					</tr>
				</table>
				
				<h2><?php _e("Cache", 'suggestion-toolkit'); ?></h2>

				<table class="form-table">
					<tr">
						<td width="15%"><?php _e("Cache type", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<select autocomplete="off" name="suggestion_toolkit_cache" id="suggestion_toolkit_cache">
								<option <?php echo (get_option('suggestion_toolkit_cache')=="")?"selected=\"selected\"":""; ?> value=""><?php _e("Auto", 'suggestion-toolkit'); ?></option>
								<?php if(class_exists('Memcached')||class_exists('Memcache')){ ?><option <?php echo (get_option('suggestion_toolkit_cache')=="memcache")?"selected=\"selected\"":""; ?> value="memcache"><?php _e("Memcache", 'suggestion-toolkit'); ?></option><?php } ?>
								<option <?php echo (get_option('suggestion_toolkit_cache')=="files")?"selected=\"selected\"":""; ?> value="files"><?php _e("Files", 'suggestion-toolkit'); ?></option>
								<?php /* ?><option <?php echo (get_option('suggestion_toolkit_cache')=="disabled")?"selected=\"selected\"":""; ?> value=""><?php _e("Disabled", 'suggestion-toolkit'); ?></option><?php */ ?>
							</select>
						</td>
						<td><?php _e("Memcache works faster, however files may same some memory on the server. Files will work better with longer expiration times.", 'suggestion-toolkit'); ?></td>
					</tr>
					<tr>
						<td width="15%"><?php _e("Cache expiration time in seconds", 'suggestion-toolkit'); ?></td>
						<td width="20%"><input autocomplete="off" type="number" name="suggestion_toolkit_cache_expiration" id="suggestion_toolkit_cache_expiration" value="<?php echo esc_attr( $this->cache_ex ); ?>"/></td>
						<td><?php _e("Longer expiration time will save server resources", 'suggestion-toolkit'); ?>.</td>
					</tr>
				</table>

				<h2><?php _e("External Links & URL Rewriting", 'suggestion-toolkit'); ?></h2>

				<table class="form-table">
					<tr">
						<td width="15%"><?php _e("Open external links in new tab", 'suggestion-toolkit'); ?></td>
						<td width="20%"><input <?php echo get_option('suggestion_toolkit_target_blank')?'checked':''; ?> autocomplete="off" type="checkbox" name="suggestion_toolkit_target_blank" id="suggestion_toolkit_target_blank" value="1"/></td>
						<td><?php _e("", 'suggestion-toolkit'); ?></td>
					</tr>
					<tr">
						<td width="15%"><?php _e("Attribute 'rel'", 'suggestion-toolkit'); ?></td>
						<td width="20%">
							<select autocomplete="off" name="suggestion_toolkit_rel" id="suggestion_toolkit_rel">
								<option <?php echo (get_option('suggestion_toolkit_rel')=="")?"selected=\"selected\"":""; ?> value=""><?php _e("No attribute", 'suggestion-toolkit'); ?></option>
								<option <?php echo (get_option('suggestion_toolkit_rel')=="nofollow")?"selected=\"selected\"":""; ?> value="nofollow"><?php _e("nofollow - ranking credit should not be passed", 'suggestion-toolkit'); ?></option>
								<option <?php echo (get_option('suggestion_toolkit_rel')=="ugc")?"selected=\"selected\"":""; ?> value="ugc"><?php _e("ugc - user generated content", 'suggestion-toolkit'); ?></option>
								<option <?php echo (get_option('suggestion_toolkit_rel')=="sponsored")?"selected=\"selected\"":""; ?> value="sponsored"><?php _e("sponsored - ads & partner links", 'suggestion-toolkit'); ?></option>
							</select>
						</td>
						<td><?php _e("", 'suggestion-toolkit'); ?></td>
					</tr>
					<tr">
						<td width="15%"><?php _e("Rewrite Affiliate & External URLs", 'suggestion-toolkit'); ?></td>
						<td width="20%"><input <?php echo get_option('suggestion_toolkit_rewrite_enable')?'checked':''; ?> autocomplete="off" type="checkbox" name="suggestion_toolkit_rewrite_enable" id="suggestion_toolkit_rewrite_enable" value="1"/></td>
						<td><?php _e("Tick-off to enable affiliate & external URL shortener.", 'suggestion-toolkit'); ?></td>
					</tr>
					<tr>
						<td width="15%"><?php _e("Subfolder for rewritten URL", 'suggestion-toolkit'); ?></td>
						<td colspan="2"><?php echo get_site_url();?>/<input autocomplete="off" type="text" name="suggestion_toolkit_rewrite_tag" id="suggestion_toolkit_rewrite_tag" value="<?php echo esc_attr( get_option('suggestion_toolkit_rewrite_tag') ); ?>"/>/</td>
					</tr>
				</table>
					

				<?php submit_button(); ?>
			</form>
			</div><!-- /.wrap -->
			<?php
		}
		
		
		
		/**
		 * Plugin settings link.
		 * 
		 * @param array $links - array of plugin settings links.
		 * @return string - links array.
		 */
		function plugin_add_settings_link( $links ) {
			array_push( $links, '<a href="'.$this->urls['extensions'].'" class="suggestion_toolkit_go_premium">' . __( 'Extensions',  'suggestion-toolkit' ) . '</a> ');
			array_unshift( $links, '<a href="'.$this->urls['settings'].'">' . __( 'Settings', 'suggestion-toolkit') . '</a>');
		  	return $links;
		}

		/**
		 * Additional plugin meta.
		 * 
		 * @param array $plugin_meta - array of plugin meta.
		 * @param string $plugin_file - plugin file.
		 * @param array $plugin_data - array of plugin data.
		 * @param string $status - plugin section page.
		 * @return string - array of plugin meta.
		 */
		function plugin_appreciation_links ( $plugin_meta = array(), $plugin_file = '', $plugin_data = array(), $status = '' ) {
		
			$base = plugin_basename(__FILE__);
			if ($plugin_file == $base) {
				$donate_link = 'https://erlycoder.com/donate/';

				$plugin_meta['docs'] = '<a href="'.$this->urls['docs'].'" target="_blank"><span class="dashicons  dashicons-search"></span>' . __( 'Docs',  'suggestion-toolkit' ) . '</a> ' . __( 'and',  'suggestion-toolkit' ) . ' <a href="'.$this->urls['support'].'" target="_blank"><span class="dashicons  dashicons-admin-users"></span>' . __( 'Support',  'suggestion-toolkit' ) . '</a> ';
				$plugin_meta['ext'] = '<a class="suggestion_toolkit_go_premium" href="'.$this->urls['extensions'].'"><span class="dashicons  dashicons-cart"></span>' . __( 'Extensions',  'suggestion-toolkit' ) . '</a> ';
				$plugin_meta['review'] = '<a href="https://wordpress.org/support/view/plugin-reviews/' . $base . '?rate=5#postform" target="_blank"><span class="dashicons dashicons-star-filled"></span>' . __( 'Write a review', 'suggestion-toolkit' ) . '</a>';
				//$plugin_meta['donate'] = '<a class="suggestion_toolkit_go_premium" href="' . esc_url( $donate_link ) . '" target="_blank">' . __( 'Donate', 'suggestion-toolkit' ) . '</a>';

				if( isset( $plugin_data['Version'] ) ) {
					global $wp_version;
					$plugin_meta['compatibility'] = '<a href="https://wordpress.org/plugins/' . $base . '/?compatibility%5Bversion%5D=' . $wp_version . '&compatibility%5Btopic_version%5D=' . $plugin_data['Version'] . '&compatibility%5Bcompatible%5D=1" target="_blank"><span class="dashicons dashicons-yes"></span>' . __( 'Confirm compatibility', 'suggestion-toolkit' ) . '</a>';
				}
			}

			return $plugin_meta;
		}

		/**
		 * Register Elementor widgets
		 */
		public function register_widgets() {
			// Its is now safe to include Widgets files
			require_once( __DIR__ . '/widgets/elementor-suggestion-toolkit.php' );
	
			// Register Widgets
			\Elementor\Plugin::instance()->widgets_manager->register_widget_type( new \SuggestionToolkit\Widgets\SuggestionToolkit_Elementor_Widget() );
		}

		/**
		 * Init plugin. Init scripts, styles and blocks.
		 */		
		function init_scripts_and_styles(){
			$tag = get_option('suggestion_toolkit_rewrite_tag');

			// rewrite rule tells wordpress to expect the given url pattern
			add_rewrite_rule( '^'.$tag.'/([^/]*)/?', 'index.php?longer=$matches[1]', 'top' );

		
			// rewrite tag adds the matches found in the pattern to the global $wp_query
			add_rewrite_tag( '%longer%', '(.*)' );
			flush_rewrite_rules();


			if(file_exists(get_template_directory()."/{$this->name}/assets/basic.css")){
				wp_register_style( 'suggestion-toolkit', get_stylesheet_directory_uri(). "/{$this->name}/assets/basic.css"  );
				wp_enqueue_style( 'suggestion-toolkit' );
			}else{
				wp_register_style( 'suggestion-toolkit', plugins_url( "{$this->name}/assets/basic.css" ) );
				wp_enqueue_style( 'suggestion-toolkit' );
			}
			
			wp_register_script(
				'suggestion-toolkit-blocks',
				plugins_url( 'js/blocks.js', __FILE__ ),
				array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-edit-post', 'wp-data', 'wp-editor' )
			);
			
			$ptypes = [(object)['value'=>'post', 'label'=>'Posts']];
			
			wp_localize_script( 'suggestion-toolkit-blocks', 'js_cfg', array('ptypes'=>apply_filters('related_posts_post_types', $ptypes), 'arrays'=>$this->conf, 'urls'=>$this->urls, 'upgrade_text'=>$this->conf['plugins']['types_and_automation']));
			
			if ( function_exists( 'register_block_type' ) ){
				register_block_type('suggestion-toolkit-blocks/suggestion-toolkit', array(
					'editor_script' => 'suggestion-toolkit-blocks',
					//'editor_style'  => 'social-photo-blocks-editor-style',
					//'style'         => 'social-photo-blocks-frontend-style',
					'render_callback' => [$this, 'related_posts_shortcode'],
					'attributes' => array(
						'style' => array(
							'type' => 'string'
						),
						'ptypes' => array(
							'type' => 'array',
							'items'   => [
								'type' => 'string',
							],
						),
						'num' => array(
							'type' => 'object'
						),
						'ptypes_key' => array(
							'type' => 'object'
						),
						'ptypes_cfg' => array(
							'type' => 'object'
						),
						'width' => array(
							'type' => 'string'
						),
						'align' => array(
							'type' => 'string'
						),
						'title' => array(
							'type' => 'string'
						),
						'keyword' => array(
							'type' => 'string'
						),
						'include' => array(
							'type' => 'string'
						),
						'exclude' => array(
							'type' => 'string'
						),
						'more' => array(
							'type' => 'string'
						),
						'show_date' => array(
							'type' => 'string'
						),
						'order' => array(
							'type' => 'string'
						),
						'updater' => array(
							'type' => 'string'
						),
					)
				) );
			}
			
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'suggestion-toolkit-blocks', 'suggestion-toolkit' );
			}
			
			wp_register_script('suggestion-toolkit-front',	plugins_url( 'js/scripts.js', __FILE__ ));
			wp_enqueue_script('suggestion-toolkit-front');
		}

		/**
		 * Plugin short code rendering - grid layout.
		 * Block code server-side rendering.
		 * 
		 * @param array $attrs - short code attributes.
		 * @return string - Rendered HTML code.
		 */
		function related_posts_shortcode($attrs){
			global $post;
			
			if(empty($post)){
				$post = wp_get_recent_posts(['numberposts' => 1, 'post_status' => 'publish'], OBJECT)[0]; 
			};
			
			if(empty($attrs['ptypes']) && !empty($attrs['num']) && is_string($attrs['num'])){
				parse_str( str_replace("&amp;", "&", $attrs['num']), $array);
				$attrs['num'] = $array;
				$attrs['ptypes'] = array_keys($array);
			}
			
			$cfg = shortcode_atts( array(
				'num' => ['post'=>'5'],
				'style' => 'thumb-row',
				'ptypes' => ['post'],
				'ptypes_key' => [],
				'ptypes_cfg' => [],
				'width' => '100%',
				'align' => 'center',
				'show_date' => 'true',
				'more' => 'true',
				'title'=>'',
				'keyword'=> strip_tags($post->post_title),
				'include'=>'',
				'exclude'=>'',
				'order'=>'random',
				'class_name' => static::class,
				'auto_type' => 'widget',
				'key_source'=>'title'
			), $attrs );
			
			$cfg['key_source'] = apply_filters("related_posts_key_source", $cfg['key_source']);
			$cfg['keyword'] = apply_filters("related_posts_keyword", $cfg['keyword'], $cfg['key_source']);
			foreach($cfg['ptypes_key'] as $ptype=>$key){ $cfg['ptypes_key'][$ptype] = apply_filters("related_posts_keyword", $key, $cfg['key_source']);	}
			
			$post_types_native = array_keys(get_post_types([], 'objects'));
			
			/**
			 * Cache ID
			 */
			$file_str = "";
			foreach($cfg as $key=>$val){
				if(is_array($val)){
					$file_str .= serialize(array_values($val));
				}else{
					$file_str .= trim($val);
				}
			}
			$cfg['cache_file_name'] = $file_str = str_replace(['&','=','%', ' '], '', $file_str);
			
			/**
			 * Read cache, if data exists.
			 */
			$context = filter_input(INPUT_GET, 'context', FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/^edit$/")));
			
			if($context!="edit"){
				if(($this->memc=='memcache')&&($cnt = $this->mc->get($file_str))){ 
					return $cnt;
				}else{
					$path = wp_upload_dir()['basedir']."/cache/{$cfg['class_name']}/{$file_str}.html";
					
					if((file_exists($path)) && ((time()-filemtime($path))<=$this->cache_ex)){
						return file_get_contents($path);
					}
				}
			}
			
			if(is_string($cfg['include'])){
				if(empty(trim($cfg['include']))){
					$cfg['include'] = [];
				}else{
					$cfg['include'] = explode(",", $cfg['include']);
					array_walk($cfg['include'], function(&$item, $key){ $item = (int)trim($item); });
				}
			}
			
			if(is_string($cfg['exclude'])){
				if(empty(trim($cfg['exclude']))){
					$cfg['exclude'] = [];
				}else{
					$cfg['exclude'] = explode(",", $cfg['exclude']);
					array_walk($cfg['exclude'], function(&$item, $key){ $item = (int)trim($item); });
				}
			}
			
			$post_id = $post->ID;
			array_push($cfg['exclude'], $post_id);
			
			/** Filter for widget settings */
			$cfg = apply_filters( 'suggestion-toolkit-settings', $cfg, $post_id );
			
			$type_posts = [];
			$number_included = 0;
			if(!empty($cfg['include'])){
				$args = array('post__in' => $cfg['include']);
				$r_q = new \WP_Query( $args ); 

				if(!empty($r_q->posts)) foreach($r_q->posts as $post_row){
					$type_posts[$post_row->post_type][] = $post_row;
					$number_included++;
				}
			}
			
			$cfg['number'] = 0;
			foreach($cfg['num'] as $type=>$num) if((in_array($type, $cfg['ptypes'])) && (!empty($num))){
				$cfg['number'] += (int)$num;
			}
			
			foreach($cfg['num'] as $type=>$num) if((in_array($type, $cfg['ptypes'])) && (in_array($type, $post_types_native))){
				$qargs = array(
					'post_type'=>$type,
					's'      => $cfg['keyword'],    // search query
					'post__not_in' => $cfg['exclude'],
					'posts_per_page'      => (int)$num,
					'post_status'         => 'publish',
					'ignore_sticky_posts' => true,
				);
				
				$qargs_ready = apply_filters('suggestion-toolkit-query-cfg', $qargs, $post_id);
				
				$exclude = $cfg['exclude'];
				$type_posts[$type] = [];
				
				$tmp_posts = $this->getRec($post_id, $qargs_ready);
				if(!empty($tmp_posts)) foreach($tmp_posts as $post_row){ 
					if(empty($type_posts[$post_row->post_type])) $type_posts[$post_row->post_type] = [];
					if((count($type_posts[$post_row->post_type])<$num)&&($number_included<$cfg['number'])){
						$type_posts[$post_row->post_type][] = $post_row;
						$number_included++;
						$exclude[] = $post_row->ID;
					}
				}
				
				if(get_option('suggestion_toolkit_append_category')){
					// Append cells with random records if no results for the same category
					$t_cnt = (!empty($type_posts[$type]))?count($type_posts[$type]):0;
					if($t_cnt<$num){
						$args = array( 'category__in'   => wp_get_post_categories( $post->ID ), 'posts_per_page' => ($num-$t_cnt), 'orderby' => 'rand', 'post_type'=>$type, 'post__not_in' => $exclude, );
						$cat_posts = get_posts( $args );
						if(!empty($cat_posts)){
							$type_posts[$type] = array_merge((array)$type_posts[$type], (array)$cat_posts);
							wp_reset_query();
						}
					}
				}
				
				if(get_option('suggestion_toolkit_append_random')){
					// Append cells with random records if no results
					$t_cnt = (!empty($type_posts[$type]))?count($type_posts[$type]):0;
					if($t_cnt<$num){
						$args = array( 'posts_per_page' => ($num-$t_cnt), 'orderby' => 'rand', 'post_type'=>$type, 'post__not_in' => $exclude, );
						$rand_posts = get_posts( $args );
						if(!empty($rand_posts)){
							$type_posts[$type] = array_merge((array)$type_posts[$type], (array)$rand_posts);
							wp_reset_query();
						}
					}
				}
			}
			
			// Filter allows filter generated posts
			$type_posts = apply_filters('suggestion-toolkit-replace-cells', $type_posts, $post_id, $cfg);

			$style_folders = [plugin_dir_path(__FILE__)."styles/"];
			$style_folders = apply_filters("relevant_related_posts_style_folders", $style_folders);
			
			$num_title_words = get_option('suggestion_toolkit_title_words'); $num_title_words = (empty($num_title_words))?5:$num_title_words;
			$cfg['num_items'] = 0;

			ob_start();
			if(is_array($type_posts)) foreach($type_posts as $ptype=>$r_posts){
				$cfg['num_items'] += count($r_posts);
				if(file_exists(get_template_directory()."/{$this->name}/styles/tpl.{$ptype}.php")){
					include get_template_directory()."/{$this->name}/styles/tpl.{$ptype}.php";
				}else{
					foreach($style_folders as $fld)	if(file_exists($fld."tpl.{$ptype}.php")){
						include $fld."tpl.{$ptype}.php";
					}
				}
			}
			$cnt_row = ob_get_clean();
			$cnt_row = apply_filters("relevant_related_posts_rendered_posts", $cnt_row, $cfg);
			
			ob_start();
			if(file_exists(get_template_directory()."/{$this->name}/styles/tpl.block-wrap.php")){
				include get_template_directory()."/{$this->name}/styles/tpl.block-wrap.php";
			}else{
				include plugin_dir_path(__FILE__)."styles/tpl.block-wrap.php";
			}

			$block_cnt = ob_get_clean();
			$block_cnt = apply_filters("relevant_related_posts_rendered_block", $block_cnt, $cfg);
			
			/**
			 * Write to cache
			 */
			$context = filter_input(INPUT_GET, 'context', FILTER_VALIDATE_REGEXP, array("options"=>array("regexp"=>"/^edit$/")));
			if($context!="edit"){
				if(($this->memc=='memcache')&&class_exists('Memcached')){
					$this->mc->set($file_str, $block_cnt, $this->cache_ex);
				}elseif(($this->memc=='memcache')&&class_exists('Memcache')){
					$this->mc->set($file_str, $block_cnt, 0, $this->cache_ex);
				}else{
					$path = wp_upload_dir()['basedir']."/cache/{$cfg['class_name']}/{$file_str}.html";
					
					file_put_contents($path, $block_cnt);
					chmod($path, 0666);
				}
			}
			
			return $block_cnt;
		}
		

		/**
		 * Plugin save settings.
		 * 
		 */
		public static function admin_init() {
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_append_category');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_append_random');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_title_words');
		
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_enabled_types');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_num_suggestions');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_more');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_cache');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_cache_expiration');
			
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_thumb_width');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_thumb_height');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_thumb_cover');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_title_font_size');
			
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_rewrite_enable');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_rewrite_tag');

			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_target_blank');
			register_setting( 'suggestion-toolkit-config-group', 'suggestion_toolkit_rel');
		}
		
		/**
		 * Plugin install routines. Check for dependencies.
		 * 
		 */
		public function plugin_install() {
			global $wpdb;
			$class_name = static::class;

			if((!is_dir(wp_upload_dir()['basedir']."/cache"))||(!is_dir(wp_upload_dir()['basedir']."/cache/{$class_name}"))){
				if(is_writable(wp_upload_dir()['basedir'])){
					@mkdir(wp_upload_dir()['basedir']."/cache");
					@chmod(wp_upload_dir()['basedir']."/cache", 0777);

					@mkdir(wp_upload_dir()['basedir']."/cache/{$class_name}");
					@chmod(wp_upload_dir()['basedir']."/cache/{$class_name}", 0777);
				}else{
					wp_die('Sorry, but this plugin requires /wp-content/uploads folder exist and be writable.');
				}
			}
			
			update_option('suggestion_toolkit_enabled_types', 'a:1:{i:0;s:4:\"post\";}', true);
			update_option('suggestion_toolkit_num_suggestions', '', true);
			update_option('suggestion_toolkit_more', '', true);
			update_option('suggestion_toolkit_append_category', '1', true);
			update_option('suggestion_toolkit_append_random', '1', true);
			update_option('suggestion_toolkit_title_words', '5', true);
			update_option('suggestion_toolkit_cache_expiration', '900', true);
			update_option('suggestion_toolkit_rewrite_enable', '1', true);
			update_option('suggestion_toolkit_rewrite_tag', 'my-shop', true);

			$this->ps->pluginActivationHook();			
		}
		
		/**
		*	Plugin uninstall routines.
		*/
		public function plugin_uninstall() {
		}
		
		/**
		*	Standard WordPress search fix for multiple keywords.
		*/
		function any_word_search_posts_search_filter($search, $query) {
			if ($query->is_search) {
				if(isset($query->query['is_suggest'])){
					$search = str_replace(')) AND ((', ')) OR ((', $search);
				}
			}
			
			return $search;
		}

		/**
		*	Get suggestions by keyword and other settings.
		*/
		public function getRec($post_id, $args){
			$r_posts = [];
			$args["is_suggest"] = true;

			if(class_exists("SWP_Query")){
				$r = new SWP_Query($args);
			}elseif(function_exists( 'relevanssi_do_query' )){
				$r = new \WP_Query();
				$r->parse_query( $args );
				relevanssi_do_query( $r );
			}else{
				$r = new \WP_Query($args);
			}

			if(!empty($r->posts)){
				wp_reset_postdata();
				return $r->posts;
			}else{
				return [];
			}
		}
		
		private $currency_symbols = array(
			'AED' => '&#1583;.&#1573;', // ?
			'AFN' => '&#65;&#102;',
			'ALL' => '&#76;&#101;&#107;',
			'AMD' => '',
			'ANG' => '&#402;',
			'AOA' => '&#75;&#122;', // ?
			'ARS' => '&#36;',
			'AUD' => '&#36;',
			'AWG' => '&#402;',
			'AZN' => '&#1084;&#1072;&#1085;',
			'BAM' => '&#75;&#77;',
			'BBD' => '&#36;',
			'BDT' => '&#2547;', // ?
			'BGN' => '&#1083;&#1074;',
			'BHD' => '.&#1583;.&#1576;', // ?
			'BIF' => '&#70;&#66;&#117;', // ?
			'BMD' => '&#36;',
			'BND' => '&#36;',
			'BOB' => '&#36;&#98;',
			'BRL' => '&#82;&#36;',
			'BSD' => '&#36;',
			'BTN' => '&#78;&#117;&#46;', // ?
			'BWP' => '&#80;',
			'BYR' => '&#112;&#46;',
			'BZD' => '&#66;&#90;&#36;',
			'CAD' => '&#36;',
			'CDF' => '&#70;&#67;',
			'CHF' => '&#67;&#72;&#70;',
			'CLF' => '', // ?
			'CLP' => '&#36;',
			'CNY' => '&#165;',
			'COP' => '&#36;',
			'CRC' => '&#8353;',
			'CUP' => '&#8396;',
			'CVE' => '&#36;', // ?
			'CZK' => '&#75;&#269;',
			'DJF' => '&#70;&#100;&#106;', // ?
			'DKK' => '&#107;&#114;',
			'DOP' => '&#82;&#68;&#36;',
			'DZD' => '&#1583;&#1580;', // ?
			'EGP' => '&#163;',
			'ETB' => '&#66;&#114;',
			'EUR' => '&#8364;',
			'FJD' => '&#36;',
			'FKP' => '&#163;',
			'GBP' => '&#163;',
			'GEL' => '&#4314;', // ?
			'GHS' => '&#162;',
			'GIP' => '&#163;',
			'GMD' => '&#68;', // ?
			'GNF' => '&#70;&#71;', // ?
			'GTQ' => '&#81;',
			'GYD' => '&#36;',
			'HKD' => '&#36;',
			'HNL' => '&#76;',
			'HRK' => '&#107;&#110;',
			'HTG' => '&#71;', // ?
			'HUF' => '&#70;&#116;',
			'IDR' => '&#82;&#112;',
			'ILS' => '&#8362;',
			'INR' => '&#8377;',
			'IQD' => '&#1593;.&#1583;', // ?
			'IRR' => '&#65020;',
			'ISK' => '&#107;&#114;',
			'JEP' => '&#163;',
			'JMD' => '&#74;&#36;',
			'JOD' => '&#74;&#68;', // ?
			'JPY' => '&#165;',
			'KES' => '&#75;&#83;&#104;', // ?
			'KGS' => '&#1083;&#1074;',
			'KHR' => '&#6107;',
			'KMF' => '&#67;&#70;', // ?
			'KPW' => '&#8361;',
			'KRW' => '&#8361;',
			'KWD' => '&#1583;.&#1603;', // ?
			'KYD' => '&#36;',
			'KZT' => '&#1083;&#1074;',
			'LAK' => '&#8365;',
			'LBP' => '&#163;',
			'LKR' => '&#8360;',
			'LRD' => '&#36;',
			'LSL' => '&#76;', // ?
			'LTL' => '&#76;&#116;',
			'LVL' => '&#76;&#115;',
			'LYD' => '&#1604;.&#1583;', // ?
			'MAD' => '&#1583;.&#1605;.', //?
			'MDL' => '&#76;',
			'MGA' => '&#65;&#114;', // ?
			'MKD' => '&#1076;&#1077;&#1085;',
			'MMK' => '&#75;',
			'MNT' => '&#8366;',
			'MOP' => '&#77;&#79;&#80;&#36;', // ?
			'MRO' => '&#85;&#77;', // ?
			'MUR' => '&#8360;', // ?
			'MVR' => '.&#1923;', // ?
			'MWK' => '&#77;&#75;',
			'MXN' => '&#36;',
			'MYR' => '&#82;&#77;',
			'MZN' => '&#77;&#84;',
			'NAD' => '&#36;',
			'NGN' => '&#8358;',
			'NIO' => '&#67;&#36;',
			'NOK' => '&#107;&#114;',
			'NPR' => '&#8360;',
			'NZD' => '&#36;',
			'OMR' => '&#65020;',
			'PAB' => '&#66;&#47;&#46;',
			'PEN' => '&#83;&#47;&#46;',
			'PGK' => '&#75;', // ?
			'PHP' => '&#8369;',
			'PKR' => '&#8360;',
			'PLN' => '&#122;&#322;',
			'PYG' => '&#71;&#115;',
			'QAR' => '&#65020;',
			'RON' => '&#108;&#101;&#105;',
			'RSD' => '&#1044;&#1080;&#1085;&#46;',
			'RUB' => '&#1088;&#1091;&#1073;',
			'RWF' => '&#1585;.&#1587;',
			'SAR' => '&#65020;',
			'SBD' => '&#36;',
			'SCR' => '&#8360;',
			'SDG' => '&#163;', // ?
			'SEK' => '&#107;&#114;',
			'SGD' => '&#36;',
			'SHP' => '&#163;',
			'SLL' => '&#76;&#101;', // ?
			'SOS' => '&#83;',
			'SRD' => '&#36;',
			'STD' => '&#68;&#98;', // ?
			'SVC' => '&#36;',
			'SYP' => '&#163;',
			'SZL' => '&#76;', // ?
			'THB' => '&#3647;',
			'TJS' => '&#84;&#74;&#83;', // ? TJS (guess)
			'TMT' => '&#109;',
			'TND' => '&#1583;.&#1578;',
			'TOP' => '&#84;&#36;',
			'TRY' => '&#8356;', // New Turkey Lira (old symbol used)
			'TTD' => '&#36;',
			'TWD' => '&#78;&#84;&#36;',
			'TZS' => '',
			'UAH' => '&#8372;',
			'UGX' => '&#85;&#83;&#104;',
			'USD' => '&#36;',
			'UYU' => '&#36;&#85;',
			'UZS' => '&#1083;&#1074;',
			'VEF' => '&#66;&#115;',
			'VND' => '&#8363;',
			'VUV' => '&#86;&#84;',
			'WST' => '&#87;&#83;&#36;',
			'XAF' => '&#70;&#67;&#70;&#65;',
			'XCD' => '&#36;',
			'XDR' => '',
			'XOF' => '',
			'XPF' => '&#70;',
			'YER' => '&#65020;',
			'ZAR' => '&#82;',
			'ZMK' => '&#90;&#75;', // ?
			'ZWL' => '&#90;&#36;',
		);
		
	}
	
	$suggestion_toolkit_init = new SuggestionToolkit();

}


require_once( __DIR__ . '/widgets/wp-suggestion-toolkit.php' );

?>
