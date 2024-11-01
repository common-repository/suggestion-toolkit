<?php

if ( !class_exists( 'psClient' ) ) {
	class psClient{
		public $pluginSlug;
		public $text_domain;
		// Staging
		//public $api = "http://localhost/wp/wp-json/product-service/v1.0/";
		// Production
		public $api = "https://erlycoder.com/wp-json/product-service/v1.0/";
		public $ext_path;
		public $pluginCat = "suggestion-toolkit-extensions";
		public $urlFAQ = "https://erlycoder.com/knowledgebase_category/suggestion-toolkit/";
		public $urlSupport = "https://erlycoder.com/support/";
		public $urls;

		public function __construct($slug, $text_domain, $urls){
			$this->pluginSlug = $slug;
			$this->text_domain = $text_domain;
			
			$this->urls = $urls;
			$this->urlFAQ = $this->urls['docs'];
			$this->pluginCat = $this->urls['pluginCat'];
			$this->ext_path = ABSPATH . "wp-content/plugins";
			
			if(is_admin()){
				add_action( 'wp_ajax_install_plugin', [$this, 'installPlugin'] );
				add_action( 'wp_ajax_uninstall_plugin', [$this, 'uninstallPlugin'] );
				add_action( 'wp_ajax_update_plugin', [$this, 'updatePlugin'] );
				add_action( 'wp_ajax_activate_plugin', [$this, 'activatePlugin'] );
				add_action( 'wp_ajax_deactivate_plugin', [$this, 'deactivatePlugin'] );
				
				add_action( 'wp_ajax_domain_registration', [$this, 'domainRegistration'] );
				add_action( 'wp_ajax_confirm_registration', [$this, 'confirmRegistration'] );
				
				add_action( 'admin_enqueue_scripts', [$this, 'initRes'] );
				add_action('admin_menu', [$this, 'extra_admin_menu']);
			}
			
			//get_plugin_data(string $plugin_file, bool $markup = true, bool $translate = true);
			//$plugin_data = get_plugin_data( __FILE__ );
		}
		
		/**
		*	Plugin admin menu
		*/
		public function extra_admin_menu(){
			add_submenu_page($this->pluginSlug, __( 'Extensions', $this->text_domain), __( 'Extensions', $this->text_domain),	'manage_options', $this->pluginSlug.'-extensions', [$this, 'extensions_page'], 50);
			add_submenu_page($this->pluginSlug, __( 'Support', $this->text_domain), __( 'Support', $this->text_domain),	'manage_options', $this->pluginSlug.'-support',	[$this, 'support_page'], 60);
		}
		
		public function support_page(){
			?>
			<h1><?php _e("Need Help", $this->text_domain); ?>?</h1>

			<div class="rpwr_admin_info">
				<div>
				<p><?php _e("We are trying to make our plugins simple to use and include some explanations where they are required (to our mind)", $this->text_domain); ?>.
					<?php _e("However we do not know what can be unclear to you", $this->text_domain); ?>. <?php _e("We are tying to provide more detailed explanations and examples in", $this->text_domain); ?> <a href="<?php echo $this->urlFAQ; ?>"><?php _e("Questions & Answers", $this->text_domain); ?></a>.
				</p>
				<p>
					<?php _e("If we have not responded your specific question in", $this->text_domain); ?> <b><?php _e("Questions & Answers", $this->text_domain); ?></b> <?php _e("section", $this->text_domain); ?>, <?php _e("feel free to", $this->text_domain); ?> <a href="<?php echo $this->urlSupport; ?>"><?php _e("contact us", $this->text_domain); ?></a> <?php _e("and we will try to provide you required help as soon as possible", $this->text_domain); ?>.
				</p>
				
				<p>
					<?php _e("Sure, we will be happy to hear from you what features are you missing and what should be improved, chaged or fixed", $this->text_domain); ?>.
				</p>
				</div>
				<img src="<?php echo plugins_url( 'img/suggest_support.svg', __FILE__ ); ?>" width="300"/>
			</div>
			
			<?php
		}
		
		public function extensions_page(){
			?>
			<h1><?php _e("Plugin Extensions & Other Suggested Plugins", $this->text_domain); ?></h1>

			<div class="wrap">
			<?php $this->showPaymentStatus(); ?>
			<?php $this->showRegister(); ?>
				<div class="rpwr_admin_info">
				<?php $this->showExt(); ?>
				</div>
			</div>
			<?php
		}
		
		public function initRes(){
			wp_register_style( 'product-service-client', plugins_url( 'css/basic.css', __FILE__ )  );
			wp_enqueue_style( 'product-service-client' );
			
			//wp_enqueue_script('product-service-client',	plugins_url( 'js/scripts.js', __FILE__ ));
			
			wp_register_script('product-service-client', plugins_url( 'js/scripts.js', __FILE__ ));
			$translation_array = array(
				'api_url' => $this->api,
			);
			wp_localize_script('product-service-client', 'prodService', $translation_array );
			wp_enqueue_script('product-service-client');
		}
		
		public function showExtUrl(){
			return "options-general.php?page=Related_Posts_with_Relevanssi&tab=ext";
		}
		
		public function showSupportUrl(){
			$login = get_option($this->pluginSlug.'_login_info');
			if(!empty($login)){
				$url = "https://erlycoder.com/my-account/support-tickets/";
				$url = "https://erlycoder.com/support/";
				return $url."?key1=".$login['key1']."&key2=".$login['key2'];
			}else{
				$url = "https://erlycoder.com/support/";
				return $url;
			}
		}
		
		public function domainRegistration(){
			$response = wp_remote_request( $this->api.'register/', ['body'=>['email'=>$_POST['email'], 'url'=>$_POST['url'], 'login_url'=>$_POST['login_url']], 'method'=>'POST']);		
			$jsonData = wp_remote_retrieve_body($response);
			
			$data = @json_decode($jsonData, true);
			if(($data['status']=='ok')&&(preg_match("/^[a-zA-Z0-9]{20}$/", $data['key1']))&&(preg_match("/^[a-zA-Z0-9]{20}$/", $data['key2']))&&(is_numeric($data['user_id']))){
				update_option($this->pluginSlug.'_login_info', $data);
			}
			
			echo $jsonData;
			exit();
		}
		
		public function confirmRegistration(){
			$response = wp_remote_request( $this->api.'confirm/', ['body'=>['email'=>$_POST['email'], 'url'=>$_POST['url'], 'code'=>$_POST['code']], 'method'=>'POST']);
			$jsonData = wp_remote_retrieve_body($response);
			
			$data = @json_decode($jsonData, true);
			if(($data['status']=='ok')&&(preg_match("/^[a-zA-Z0-9]{20}$/", $data['key1']))&&(preg_match("/^[a-zA-Z0-9]{20}$/", $data['key2']))&&(is_numeric($data['user_id']))){
				update_option($this->pluginSlug.'_login_info', $data);
			}
			
			echo $jsonData;
			exit();
		}
		
		public function showPaymentStatus(){
		
			if((isset($_REQUEST['status']))&&($_REQUEST['status']=='ok')){
			?>
			<div class="ps-payment-info">
				<div>
					<p><?php _e("You purchase completed successfully", $this->pluginSlug); ?>. <?php _e("Now you can activate purchased extensions", $this->pluginSlug); ?>. <?php _e("Feel free to", $this->pluginSlug); ?> <a href="<?php echo $this->showSupportUrl(); ?>" target="_blank"><?php _e("contact us", $this->pluginSlug); ?></a>, <?php _e("if you got some questions or miss some features", $this->pluginSlug); ?>.</p>
				</div>
				<img src="<?php echo plugins_url( '/img/order_confirmed.svg', __FILE__ ); ?>"/>
			</div>
			<?php
			}elseif((isset($_REQUEST['status']))&&($_REQUEST['status']=='failed')){
			?>
			<div class="ps-payment-info">
				<div>
					<p><?php _e("You purchase have failed", $this->pluginSlug); ?>. <a href="<?php echo $this->showSupportUrl(); ?>" target="_blank"><?php _e("Contact us", $this->pluginSlug); ?></a>, <?php _e("if you believe that this is some kind or error", $this->pluginSlug); ?>.</p>
				</div>
				<img src="<?php echo plugins_url( '/img/pay_online.svg', __FILE__ ); ?>" width="400"/>
			</div>
			<?php
			}else{
			/*
			?>
			<div class="ps-payment-info">
				<div>
					<p><?php _e("You purchase completed successfully", $this->pluginSlug); ?>. <?php _e("Now you can use ", $this->pluginSlug); ?>.</p>
				</div>
				<img src="<?php echo plugins_url( '/img/order_confirmed.svg', __FILE__ ); ?>"/>
			</div>
			<?php
			*/
			}
		}
		
		public function showRegister(){
			if(isset($_REQUEST['email'])&&isset($_REQUEST['code'])&&filter_var($_REQUEST['email'], FILTER_VALIDATE_EMAIL)&&preg_match("/^[a-zA-Z0-9]{20}$/", $_REQUEST['code'])){
				$url = get_rest_url();
				$response = wp_remote_request( $this->api.'confirm/', ['body'=>['email'=>$_REQUEST['email'], 'url'=>$url, 'code'=>$_REQUEST['code']], 'method'=>'POST']);
				$jsonData = wp_remote_retrieve_body($response);
				
				$data = @json_decode($jsonData, true);
				if(($data['status']=='ok')&&(preg_match("/^[a-zA-Z0-9]{20}$/", $data['key1']))&&(preg_match("/^[a-zA-Z0-9]{20}$/", $data['key2']))&&(is_numeric($data['user_id']))){
					update_option($this->pluginSlug.'_login_info', $data);
				}
			}
		
			$login = get_option($this->pluginSlug.'_login_info');
		
			if(!empty($login)){
				return true;
			}else{
				$user = wp_get_current_user();
				?>
				<div class="ps-product-login-container">
					<div class="ps-product-login">
						<p class="ps-product-login-descr">
							<?php _e("Please, register your plugin with just one click. This will open access to all free and premium extensions.", $this->text_domain); ?>
							<?php _e("This required mainly to identify website/client for communication between services.", $this->text_domain); ?>
						</p>
						<div class="ps-product-login-form" id="ps-product-login-form">
							<div id="ps-product-login-error"></div>
							<div id="ps-product-login-form-inputs">
								<input type="hidden" name="login_rest_url" id="login_rest_url" value="<?php echo get_rest_url(); ?>"/>
								<label><?php _e("E-mail", $this->text_domain); ?>: <input name="login_email" id="login_email" type="text" value="<?php echo $user->user_email; ?>"/></label>
								<button type="button" class="ps-product-login-btn" onclick="psInst.registerPlugin(document.getElementById('login_email').value, document.getElementById('login_rest_url').value); return false;"><?php _e("Register plugin", $this->text_domain); ?></button>
							</div>
						</div>
						<div class="ps-product-verify-form hidden" id="ps-product-verify-form">
							<div><?php _e("Please, input verification code from the e-mail.", $this->text_domain); ?></div>
							<div id="ps-product-verify-error"></div>
							<div id="ps-product-verify-form-inputs">
								<label><?php _e("Code", $this->text_domain); ?>: <input name="login_code" id="login_code" type="text" value=""/></label>
								<span>
									<button type="button" class="ps-product-login-btn" onclick="psInst.verifyRegistrationPlugin(document.getElementById('login_code').value, document.getElementById('login_email').value, document.getElementById('login_rest_url').value); return false;"><?php _e("Verify", $this->text_domain); ?></button>&nbsp;
									<a href="#" onclick="psInst.verifyResend(); return false;"><?php _e("Resend", $this->text_domain); ?></a>
								</span>
							</div>
						</div>
						
					</div>
				</div>
				
				<?php
				
				return false;
			}
		}
		
		public function showExt(){
			/*
			if( ! function_exists('get_plugin_data') ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			*/
			//$user = wp_get_current_user();
			
			$login_json = get_option($this->pluginSlug.'_login_info');
			if(!empty($login_json)){
				$user_id = $login_json['user_id'];
				$key1 = $login_json['key1'];
				$key2 = $login_json['key2'];
			}
			
			if(!empty($key1)&&!empty($key2)&&preg_match("/^[a-zA-Z0-9]{20}$/", $key1)&&preg_match("/^[a-zA-Z0-9]{20}$/", $key2)){
				$response = wp_remote_request( $this->api.'list/'.$user_id, ['body'=>['k1'=>$key1, 'k2'=>$key2, 'category'=>$this->pluginCat], 'method'=>'POST']);
			}else{
				$response = wp_remote_request( $this->api.'list/', ['body'=>['category'=>$this->pluginCat], 'method'=>'POST']);
			}
			$jsonData = wp_remote_retrieve_body($response);
			$items = @json_decode($jsonData, true);
			
			if(!empty($items['code'])&&($items['code']=='Access error')){
				update_option($this->pluginSlug.'_login_info', $data);
				wp_redirect($this->urls['extensions']);
				exit();
			}

			?><div class="ps-product-lst"><?php

			if((isset($items['status']))&&($items['status']=='ok')){ 
			
			if(!empty($items['products_ext'])){
			?><h2><?php _e("Extensions", $this->text_domain); ?></h2><?php
			}
			foreach($items['products_ext'] as $item){ 
			?><div class="ps-product-lst-item">
				<div style="width: 50px; height: 50px; background-image: url(<?php echo $item['image']; ?>); background-size: contain; background-repeat: no-repeat; margin-right: 10px;"></div>
				<div class="ps-product-lst-item-name">
					<b><?php echo $item['name']; ?></b>&nbsp;&nbsp;<a target="_blank" href="https://erlycoder.com/product/<?php echo $item['slug'];?>/"><?php _e("Learn more", $this->text_domain); ?></a>
					<div><?php echo $item['short_description']; ?></div>
					<div class="ps-product-lst-item-downloads">
					<?php if(!empty($item['downloads'])) foreach($item['downloads'] as $key=>$download){ ?>
						<?php 
							if(file_exists(ABSPATH . "wp-content/plugins/".$download['download_plugin']."/index.php")){ 
								$installed_plugin_version = get_plugin_data(ABSPATH . "wp-content/plugins/".$download['download_plugin']."/index.php")['Version']; 
								$installed = true; 
								if($download['download_version']!=$installed_plugin_version){ $update=true; }else{ $update=false; } 
								
							}else{ 
								$installed = false; 
								$update=false; 
							} 
							
							$active = is_plugin_active($download['download_plugin']."/index.php");
							
							?>
					
						<div class="ps-product-lst-item-download">
						
							<div><?php echo $download['name']; ?> [ <?php if($installed){ echo "v".$installed_plugin_version; } ?> <?php if($update){ ?>&nbsp;<a href="#" onclick="psInst.updatePlugin('<?php echo $download['download_id']; ?>'); return false;"><?php echo __("Update to", $this->text_domain)." ".$download['download_version']; ?></a><?php } ?> ]</div>
						
							<div class="ps-product-lst-item-actions">
							<?php 
							if($installed){ 
								
								if($active){ 
									if(isset($GLOBALS['product-service'][$this->pluginSlug])){
										?>&nbsp;<a href="<?php echo $GLOBALS['product-service'][$this->pluginSlug]['settings']; ?>" ><?php _e("Settings", $this->text_domain); ?></a>&nbsp;|<?php
									}
									?>&nbsp;<a href="#" onclick="psInst.deactivatePlugin('<?php echo $download['download_plugin']; ?>'); return false;"><?php _e("Deactivate", $this->text_domain); ?></a>&nbsp;<?php
								}else{ 
									?>&nbsp;<a href="#" onclick="psInst.uninstallPlugin('<?php echo $download['download_id']; ?>'); return false;"><?php _e("Uninstall", $this->text_domain); ?></a>&nbsp;|<?php 
									?>&nbsp;<a href="#" onclick="psInst.activatePlugin('<?php echo $download['download_plugin']; ?>'); return false;"><?php _e("Activate", $this->text_domain); ?></a>&nbsp;<?php 
								}
							}else{ 
								?>&nbsp;<a href="#" onclick="psInst.installPlugin('<?php echo $download['download_id']; ?>'); return false;"><?php _e("Install", $this->text_domain); ?></a>&nbsp;<?php 
							} ?>
							</div>
						</div>
					<?php } ?>
					</div>
				
				</div>
				
				<?php if(empty($item['downloads']) && !empty($login_json)){ ?>
				<button class="ps-product-btn" type="button" onclick="psInst.toggleToCart(<?php echo $item['id']; ?>, <?php echo $item['price']; ?>); this.classList.toggle('checked'); return false;">
					<i class="fa fa-check" aria-hidden="true"></i>
				<?php if($item['price']==0){ ?>
					<span>
					<b><?php _e("FREE", $this->text_domain); ?> - <?php _e("Get now", $this->text_domain); ?></b>
					</span>
				<?php }else{ ?>
					<span>
					<?php if($item['regular_price']>$item['price']){ echo "<strike>$".number_format($item['regular_price'], 2, ".", "")."</strike>"; } ?>
					<b>$<?php echo number_format($item['price'], 2, ".", ""); ?> <?php _e("domain/year", $this->text_domain); ?> <br/> <?php _e("Buy now", $this->text_domain); ?></b>
					</span>
				<?php } ?>
				</button>
				<?php }elseif(empty($login_json)){ ?>
				<a href="#ps-product-login-form"><?php _e("Please, register first"); ?></a>
				<?php }else{ ?>
				<div class="ps-product-btn-placeholder">&nbsp;</div>
				<?php } ?>
			</div>
			<?php } ?>
			
			<?php if(!empty($items['products_plug'])){ ?>
			<h3><?php _e("Suggested plugins and packages", $this->text_domain); ?></h3>
			<?php } ?>
			<?php foreach($items['products_plug'] as $item) if($item['slug']!=$this->pluginSlug){
			?><div class="ps-product-lst-item">
				<div style="width: 50px; height: 50px; background-image: url(<?php echo $item['image']; ?>); background-size: contain; background-repeat: no-repeat; margin-right: 10px;"></div>
				<div class="ps-product-lst-item-name">
					<b><?php echo $item['name']; ?></b>&nbsp;&nbsp;<a target="_blank" href="https://erlycoder.com/product/<?php echo $item['slug'];?>/"><?php _e("Learn more", $this->text_domain); ?></a>
					<div><?php echo $item['short_description']; ?></div>
					<div class="ps-product-lst-item-downloads">
					<?php if(!empty($item['downloads'])) foreach($item['downloads'] as $key=>$download){ ?>
						<?php 
							if(file_exists(ABSPATH . "wp-content/plugins/".$download['download_plugin']."/index.php")){ 
								$installed_plugin_version = get_plugin_data(ABSPATH . "wp-content/plugins/".$download['download_plugin']."/index.php")['Version']; 
								$installed = true; 
								if($download['download_version']!=$installed_plugin_version){ $update=true; }else{ $update=false; } 
							}else{ 
								$installed = false; 
								$update=false; 
							} 
							
							$active = is_plugin_active($download['download_plugin']."/index.php");
							
							?>
					
						<div class="ps-product-lst-item-download">
						
							<div><?php echo $download['name']; ?> [ <?php if($installed){ echo "v".$installed_plugin_version; } ?> <?php if($update){ ?>&nbsp;<a href="#" onclick="psInst.updatePlugin('<?php echo $download['download_id']; ?>'); return false;"><?php echo __("Update to", $this->text_domain)." ".$download['download_version']; ?></a><?php } ?> ]</div>
						
							<div class="ps-product-lst-item-actions">
							<?php 
							if($installed){ 
								
								if($active){ 
									if(isset($GLOBALS['product-service'][$this->pluginSlug])){
										?>&nbsp;<a href="<?php echo $GLOBALS['product-service'][$this->pluginSlug]['settings']; ?>" ><?php _e("Settings", $this->text_domain); ?></a>&nbsp;|<?php
									}
									?>&nbsp;<a href="#" onclick="psInst.deactivatePlugin('<?php echo $download['download_plugin']; ?>'); return false;"><?php _e("Deactivate", $this->text_domain); ?></a>&nbsp;<?php
								}else{ 
									?>&nbsp;<a href="#" onclick="psInst.uninstallPlugin('<?php echo $download['download_id']; ?>'); return false;"><?php _e("Uninstall", $this->text_domain); ?></a>&nbsp;|<?php 
									?>&nbsp;<a href="#" onclick="psInst.activatePlugin('<?php echo $download['download_plugin']; ?>'); return false;"><?php _e("Activate", $this->text_domain); ?></a>&nbsp;<?php 
								}
							}else{ 
								?>&nbsp;<a href="#" onclick="psInst.installPlugin('<?php echo $download['download_id']; ?>'); return false;"><?php _e("Install", $this->text_domain); ?></a>&nbsp;<?php 
							} ?>
							</div>
						</div>
					<?php } ?>
					</div>
				
				</div>
				<?php if(empty($item['downloads']) && !empty($login_json)){ ?>
				<button class="ps-product-btn" type="button" onclick="psInst.toggleToCart(<?php echo $item['id']; ?>, <?php echo $item['price']; ?>); this.classList.toggle('checked'); return false;">
					<i class="fa fa-check" aria-hidden="true"></i>
				<?php if($item['price']==0){ ?>
					<span>
					<b><?php _e("FREE", $this->text_domain); ?> - <?php _e("Get now", $this->text_domain); ?></b>
					</span>
				<?php }else{ ?>
					<span>
					<?php if($item['regular_price']>$item['price']){ echo "<strike>$".number_format($item['regular_price'], 2, ".", "")."</strike>"; } ?>
					<b>$<?php echo number_format($item['price'], 2, ".", ""); ?> <?php _e("domain/year", $this->text_domain); ?> <br/> <?php _e("Buy now", $this->text_domain); ?></b>
					</span>
				<?php } ?>
				</button>
				<?php }elseif(empty($login_json)){ ?>
				<a href="#ps-product-login-form"><?php _e("Please, register first"); ?></a>
				<?php }else{ ?>
				<div class="ps-product-btn-placeholder">&nbsp;</div>
				<?php } ?>
			</div>
			<?php }  
			
			if(!empty($login_json)){ ?>
			
			<div class="ps-product-checkout-row ps-product-checkout">
				<div class="ps-product-checkout-error hidden" id="checkout-error1"><?php _e("Please, select extensions first", $this->text_domain); ?>.</div>
				<div class="ps-product-checkout-error hidden" id="checkout-error2"><?php _e("Checkout failed, try again or contact", $this->text_domain); ?> <a href="https://erlycoder.com/support/" target="_blank"><?php _e("support", $this->text_domain); ?></a>.</div>
				<button class="ps-product-checkout" type="button" onclick="psInst.proceedCheckout(<?php echo $user_id; ?>, '<?php echo $this->pluginSlug; ?>', '<?php echo rawurlencode($key1); ?>', '<?php echo rawurlencode($key2); ?>'); return false;"><b id="ps-product-total-num">0</b>&nbsp; <?php _e("Plugins for", $this->text_domain); ?>&nbsp; <b id="ps-product-total-price">$0.00</b>&nbsp; - &nbsp;<b><?php _e("Complete purchase", $this->text_domain); ?></b></button>
			</div>
			
			<?php }
			
			}else{
				?><center><b><?php _e("Service is temporary unavailable", $this->text_domain); ?></b></center><?php
			}
			
			?>
			
			</div><?php
		}
		
		public function showSupport(){
		}

		public function installPlugin(){
			// http://localhost/wp/wp-admin/admin-ajax.php?action=install_ext&download_id=c3563be9-a03f-4432-8e5a-5a155e72ac20
			$user = wp_get_current_user();
			
			$response = wp_remote_request( $this->api.'list/'.$user->ID);
			$jsonData = wp_remote_retrieve_body($response);
			$items = @json_decode($jsonData, true);
			
			$downloads = [];
			foreach($items as $item){
				foreach($item['downloads'] as $id=>$dnld){
					$downloads[$id] = $dnld;
				}
			}
			
			if(!empty($downloads[$_REQUEST['download_id']])){
		
				// Now you can use it!
				$file_url = $downloads[$_REQUEST['download_id']]['download_url'];
				$tmp_file = download_url( $file_url );
				 
				if(file_exists($tmp_file)){
					WP_Filesystem();
					
					$dirs1 = scandir($this->ext_path);

					$unzipfile = unzip_file( $tmp_file, $this->ext_path);
					if ( $unzipfile ) {
						echo json_encode(['status'=>'ok']);       
					} else {
						echo json_encode(['error'=>'There was an error unzipping the file']);       
					}
	 
					// Copies the file to the final destination and deletes temporary file.
					//copy( $tmp_file, $filepath );
					@unlink( $tmp_file );
					echo json_encode(['status'=>'ok']);       
				}elseif( is_wp_error( $tmp_file ) ) {
					echo json_encode(['error'=> $tmp_file->get_error_message()]);
				}
			}else{
				echo json_encode(['error'=>'Download error']);
			}
			
			exit();
		}
		
		public function uninstallPlugin(){
			// http://localhost/wp/wp-admin/admin-ajax.php?action=uninstall_ext&download_id=c3563be9-a03f-4432-8e5a-5a155e72ac20
			
			$response = wp_remote_request( $this->api.'list/'.$user->ID);
			$jsonData = wp_remote_retrieve_body($response);
			$items = @json_decode($jsonData, true);
			
			$downloads = [];
			foreach($items as $item){
				foreach($item['downloads'] as $id=>$dnld){
					$downloads[$id] = $dnld;
				}
			}
			
			$this->rrmdir($this->ext_path."/".$downloads[$_REQUEST['download_id']]['download_plugin']);
			unset($installed_ext[$_REQUEST['download_id']]);
			
			echo json_encode(['status'=>'ok']);       
			exit();
		}
		
		public function updatePlugin(){
			// http://localhost/wp/wp-admin/admin-ajax.php?action=install_ext&download_id=c3563be9-a03f-4432-8e5a-5a155e72ac20
			$user = wp_get_current_user();
			
			$response = wp_remote_request( $this->api.'list/'.$user->ID);
			$jsonData = wp_remote_retrieve_body($response);
			$items = @json_decode($jsonData, true);
			
			$downloads = [];
			foreach($items as $item){
				foreach($item['downloads'] as $id=>$dnld){
					$downloads[$id] = $dnld;
				}
			}
			
			if(!empty($downloads[$_REQUEST['download_id']])){
		
				// Now you can use it!
				$file_url = $downloads[$_REQUEST['download_id']]['download_url'];
				$tmp_file = download_url( $file_url );
				 
				if(file_exists($tmp_file)){
					WP_Filesystem();
					
					$dirs1 = scandir($this->ext_path);

					$unzipfile = unzip_file( $tmp_file, $this->ext_path);
					if ( $unzipfile ) {
						echo json_encode(['status'=>'ok']);       
					} else {
						echo json_encode(['error'=>'There was an error unzipping the file']);       
					}
	 
					// Copies the file to the final destination and deletes temporary file.
					//copy( $tmp_file, $filepath );
					@unlink( $tmp_file );
					echo json_encode(['status'=>'ok']);       
				}elseif( is_wp_error( $tmp_file ) ) {
					echo json_encode(['error'=> $tmp_file->get_error_message()]);
				}
			}else{
				echo json_encode(['error'=>'Download error']);
			}
			
			exit();
		}
		
		private function rrmdir($src) {
			if(!file_exists($src)) return 0;
			
			$dir = opendir($src);
			while(false !== ( $file = readdir($dir)) ) {
				if (( $file != '.' ) && ( $file != '..' )) {
				    $full = $src . '/' . $file;
				    if ( is_dir($full) ) {
				        rrmdir($full);
				    }
				    elseif(file_exists($full)) {
				        unlink($full);
				    }
				}
			}
			closedir($dir);
			rmdir($src);
		}
		
		public function activatePlugin(){
			$res = activate_plugin($_REQUEST['plugin']."/index.php");
			
			if(is_wp_error($res)){
				echo json_encode(['error'=>'Activation error']);
			}else{
				echo json_encode(['status'=>'ok']);
			}
			
			exit();
		}
		
		public function deactivatePlugin(){
			$res = deactivate_plugins($_REQUEST['plugin']."/index.php");
			
			if(is_wp_error($res)){
				echo json_encode(['error'=>'Deactivation error']);
			}else{
				echo json_encode(['status'=>'ok']);
			}
			
			exit();
		}
		
		/**
		*	Parent plugin activation routines
		*/
		public function pluginActivationHook(){
		}
	}
}

?>
