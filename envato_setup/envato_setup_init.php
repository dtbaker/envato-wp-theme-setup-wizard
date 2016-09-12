<?php

// This is the setup wizard init file.
// This file changes for each one of dtbaker's themes
// This is where I extend the default 'Envato_Theme_Setup_Wizard' class and can do things like remove steps from the setup process.

// This particular init file has a custom "Update" step that is triggered on a theme update. If the setup wizard finds some old shortcodes after a theme update then it will go through the content and replace them. Probably remove this from your end product.

if ( ! defined( 'ABSPATH' ) ) exit;


add_filter('envato_setup_logo_image','dtbwp_envato_setup_logo_image');
function dtbwp_envato_setup_logo_image($old_image_url){
	return get_template_directory_uri().'/images/logo.png';
}

if ( ! function_exists( 'envato_theme_setup_wizard' ) ) :
	function envato_theme_setup_wizard() {

		if(class_exists('Envato_Theme_Setup_Wizard')) {
			class dtbwp_Envato_Theme_Setup_Wizard extends Envato_Theme_Setup_Wizard {

				/**
				 * Holds the current instance of the theme manager
				 *
				 * @since 1.1.3
				 * @var Envato_Theme_Setup_Wizard
				 */
				private static $instance = null;

				/**
				 * @since 1.1.3
				 *
				 * @return Envato_Theme_Setup_Wizard
				 */
				public static function get_instance() {
					if ( ! self::$instance ) {
						self::$instance = new self;
					}

					return self::$instance;
				}

				public function init_actions(){
					if ( apply_filters( $this->theme_name . '_enable_setup_wizard', true ) && current_user_can( 'manage_options' )  ) {
						add_filter( $this->theme_name . '_theme_setup_wizard_content', array(
							$this,
							'theme_setup_wizard_content'
						) );
						add_filter( $this->theme_name . '_theme_setup_wizard_steps', array(
							$this,
							'theme_setup_wizard_steps'
						) );
					}
					parent::init_actions();
				}

				public function theme_setup_wizard_steps($steps){
					//unset($steps['design']); // this removes the "logo" step
					return $steps;
				}
				public function theme_setup_wizard_content($content){
					if($this->is_possible_upgrade()){
						array_unshift_assoc($content,'upgrade',array(
							'title' => __( 'Upgrade', 'envato_setup' ),
							'description' => __( 'Upgrade Content and Settings', 'envato_setup' ),
							'pending' => __( 'Pending.', 'envato_setup' ),
							'installing' => __( 'Installing Updates.', 'envato_setup' ),
							'success' => __( 'Success.', 'envato_setup' ),
							'install_callback' => array( $this,'_content_install_updates' ),
							'checked' => 1
						));
					}
					return $content;
				}

				public function get_default_theme_style(){
					return false;
				}

				public function is_possible_upgrade(){
					$widget = get_option('widget_text');
					if(is_array($widget)) {
						foreach($widget as $item){
							if(isset($item['dtbwp_widget_bg'])){
								return true;
							}
						}
					}
					// check if shop page is already installed?
					$shoppage = get_page_by_title( 'Shop' );
					if ( $shoppage || get_option( 'page_on_front', false ) ) {
						return true;
					}

					return false;
				}

				public function _content_install_updates(){

					// replace old line shortcode with new one.
					global $wpdb;
					$sql = "UPDATE ".$wpdb->posts." SET post_content = REPLACE ( post_content, 'boutique_line', 'dtbaker_line');";
					$wpdb->query($sql);

					$sql = "UPDATE ".$wpdb->posts." SET post_content = REPLACE ( post_content, 'boutique_banner', 'dtbaker_banner');";
					$wpdb->query($sql);

					$sql = "UPDATE ".$wpdb->posts." SET post_content = REPLACE ( post_content, 'boutique_icon', 'dtbaker_icon');";
					$wpdb->query($sql);

					$sql = "UPDATE ".$wpdb->posts." SET post_content = REPLACE ( post_content, 'google_map', 'dtbaker_google_map');";
					$wpdb->query($sql);


					$widget = get_option('widget_text');
					if(is_array($widget)) {
						foreach ( $widget as $key => $val ) {
							if ( ! empty( $val['text'] ) ) {
								$widget[ $key ]['text'] = str_replace( '[dtbaker_icon icon="truck"]', '<div class="dtbaker-icon-truck"></div>', $val['text'] );
							}
						}
						update_option( 'widget_text', $widget );
					}

					return true;

				}

			}

			dtbwp_Envato_Theme_Setup_Wizard::get_instance();
		}else{
			// log error?
		}
	}
endif;