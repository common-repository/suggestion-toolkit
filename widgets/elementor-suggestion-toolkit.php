<?php

namespace SuggestionToolkit\Widgets;

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Memcache;

class SuggestionToolkit_Elementor_Widget extends \Elementor\Widget_Base {
	public function __construct( $data = [], $args = null ){
		parent::__construct( $data, $args );

		if(class_exists('Memcache')){ 
			$this->mc = new Memcache; 
			
			$mc_server = (defined('MEMCACHE_SERVER'))?MEMCACHE_SERVER:'localhost';
			$mc_port = (defined('MEMCACHE_PORT'))?MEMCACHE_PORT:11211;
			$this->mc->addServer($mc_server, $mc_port); 
		}else{
			$this->mc = null;
		}
	}

	public function get_name() {
		return 'suggestion_toolkit';
	}

	public function get_title() {
		return __( 'Suggestion Toolkit', 'suggestion-toolkit' );
	}

	public function get_icon() {
		return 'eicon-theme-builder';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	protected function _register_controls() {
		global $suggestion_toolkit_init;
		
		$ptypes = apply_filters('related_posts_post_types', [(object)['value'=>'post', 'label'=>'Posts']]);

		$this->start_controls_section(
			'basic_section',
			[
				'label' => __( 'Basic', 'suggestion-toolkit' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);
		
		$this->add_control(
			'title',
			[
				'label' => __( 'Title', 'suggestion-toolkit' )." (".__( 'optional', 'suggestion-toolkit' ).")",
				'type' => \Elementor\Controls_Manager::TEXT,
				'input_type' => 'text',
				'placeholder' => __( 'Ex: We suggest:', 'suggestion-toolkit' ),
			]
		);

		$this->add_control(
			'keyword',
			[
				'label' => __( 'Keyword', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'input_type' => 'text',
				'placeholder' => __( 'Ex: test', 'suggestion-toolkit' ),
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'layout_section',
			[
				'label' => __( 'Layout & styles', 'suggestion-toolkit' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'style',
			[
				'label' => __( 'Style', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'thumb-row',
				'options' => $suggestion_toolkit_init->conf['style'],
			]
		);

		$this->add_control(
			'order',
			[
				'label' => __( 'Order', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'default',
				'options' => $suggestion_toolkit_init->conf['order'],
			]
		);

		$this->add_control(
			'width',
			[
				'label' => __( 'Width', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'input_type' => 'text',
				'default'=>"100%",
				'placeholder' => __( 'Ex: 100%', 'suggestion-toolkit' ),
			]
		);

		$this->add_control(
			'align',
			[
				'label' => __( 'Align', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'left',
				'options' => $suggestion_toolkit_init->conf['align'],
			]
		);

		$this->add_control(
			'show_date',
			[
				'label' => __( 'Show post date', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'suggestion-toolkit' ),
				'label_off' => __( 'Hide', 'suggestion-toolkit' ),
				'return_value' => '1',
				'default' => '',
			]
		);

		$this->add_control(
			'more',
			[
				'label' => __( 'Show `more` button', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'suggestion-toolkit' ),
				'label_off' => __( 'Hide', 'suggestion-toolkit' ),
				'return_value' => '1',
				'default' => '',
			]
		);

		
		$this->end_controls_section();

		$this->start_controls_section(
			'types_section',
			[
				'label' => __( 'Post types', 'suggestion-toolkit' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);


		foreach($ptypes as $ptype){
			$this->add_control(
				'ptypes_'.$ptype->value,
				[
					'label' => $ptype->label,
					'type' => \Elementor\Controls_Manager::SWITCHER,
					'label_on' => __( 'Show', 'your-plugin' ),
					'label_off' => __( 'Hide', 'your-plugin' ),
					'return_value' => '1',
					'default' => ($ptype->value=='post')?'1':'',
				]
			);

			if(empty($ptype->custom)){
				$this->add_control(
					'num_'.$ptype->value,
					[
						'label' => __( 'Number of posts', 'suggestion-toolkit' ),
						'type' => \Elementor\Controls_Manager::TEXT,
						'input_type' => 'text',
						'placeholder' => __( 'Ex: 3', 'suggestion-toolkit' ),
						'default' => ($ptype->value=='post')?'4':'',
						'conditions' => [
							'terms' => [
								[
									'name' => 'ptypes_'.$ptype->value,
									'operator' => '!==',
									'value' => ''
								],
							]
						],
					]
				);
			}else{
				if($ptype->custom_type=='select'){
					$this->add_control(
						'num_'.$ptype->value,
						[
							'label' => __( 'Ad to display', 'suggestion-toolkit' ),
							'type' => \Elementor\Controls_Manager::SELECT,
							'default' => '',
							'options' => $ptype->custom,
						]
					);
				}
			}
		}
		
		if(count($ptypes)==1){ 
			$this->add_control(
				'important_note',
				[
					'label' => "",
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw' => 
					__( 'Install', 'suggestion-toolkit' ).
					" <a href=\"".$suggestion_toolkit_init->upgrade_link."\" target=\"_blank\">".
					$suggestion_toolkit_init->conf['plugins']['types_and_automation']."</a> ".
					__( 'plugin to enable extended post types including products', 'suggestion-toolkit' ) .".",
					'content_classes' => 'your-class',
				]
			);
		}
		

		$this->end_controls_section();

		$this->start_controls_section(
			'ids_section',
			[
				'label' => __( 'IDs include/exclude', 'suggestion-toolkit' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'include',
			[
				'label' => __( 'Include post IDs', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'input_type' => 'text',
				'placeholder' => __( 'Ex: 1, 5, 12', 'suggestion-toolkit' ),
			]
		);

		$this->add_control(
			'exclude',
			[
				'label' => __( 'Exclude post IDs', 'suggestion-toolkit' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'input_type' => 'text',
				'placeholder' => __( 'Ex: 1, 5, 12', 'suggestion-toolkit' ),
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		global $suggestion_toolkit_init;

		$widget_name = $this->get_name();

		$settings = $this->get_settings_for_display();
		
		/** Filter for widget settings */
		//$cfg = apply_filters( 'related_posts_with_relevanssi_elementor_cfg', $settings, $widget_name );
		
		echo $suggestion_toolkit_init->related_posts_shortcode($settings);

	}

	protected function _content_template() {
		
	}

}

?>
