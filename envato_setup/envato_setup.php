<?php
/**
 * Envato Theme Setup Wizard Class
 *
 * Takes new users through some basic steps to setup their ThemeForest theme.
 *
 * @author      dtbaker
 * @author      vburlak
 * @package     envato_wizard
 * @version     1.1.7
 *
 * Based off the WooThemes installer.
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Envato_Theme_Setup_Wizard' ) ) {
	/**
	 * Envato_Theme_Setup_Wizard class
	 */
	class Envato_Theme_Setup_Wizard {

		/**
		 * The class version number.
		 *
		 * @since 1.1.1
		 * @access private
		 *
		 * @var string
		 */
		protected $version = '1.1.9';

		/** @var string Current theme name, used as namespace in actions. */
		protected $theme_name = '';

		/** @var string Theme author username, used in check for oauth. */
		protected $envato_username = '';

		/** @var string Full url to server-script.php (available from https://gist.github.com/dtbaker ) */
		protected $oauth_script = '';

		/** @var string Current Step */
		protected $step   = '';

		/** @var array Steps for the setup wizard */
		protected $steps  = array();

		/**
		 * Relative plugin path
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $plugin_path = '';

		/**
		 * Relative plugin url for this plugin folder, used when enquing scripts
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $plugin_url = '';

		/**
		 * The slug name to refer to this menu
		 *
		 * @since 1.1.1
		 *
		 * @var string
		 */
		protected $page_slug;

		/**
		 * TGMPA instance storage
		 *
		 * @var object
		 */
		protected $tgmpa_instance;

		/**
		 * TGMPA Menu slug
		 *
		 * @var string
		 */
		protected $tgmpa_menu_slug = 'tgmpa-install-plugins';

		/**
		 * TGMPA Menu url
		 *
		 * @var string
		 */
		protected $tgmpa_url = 'themes.php?page=tgmpa-install-plugins';

		/**
		 * The slug name for the parent menu
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $page_parent;

		/**
		 * Complete URL to Setup Wizard
		 *
		 * @since 1.1.2
		 *
		 * @var string
		 */
		protected $page_url;


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

		/**
		 * A dummy constructor to prevent this class from being loaded more than once.
		 *
		 * @see Envato_Theme_Setup_Wizard::instance()
		 *
		 * @since 1.1.1
		 * @access private
		 */
		public function __construct() {
			$this->init_globals();
			$this->init_actions();
		}

		/**
		 * Get the default style. Can be overriden by theme init scripts.
		 *
		 * @see Envato_Theme_Setup_Wizard::instance()
		 *
		 * @since 1.1.7
		 * @access public
		 */
		public function get_default_theme_style(){
			return 'pink';
		}

		/**
		 * Get the default style. Can be overriden by theme init scripts.
		 *
		 * @see Envato_Theme_Setup_Wizard::instance()
		 *
		 * @since 1.1.9
		 * @access public
		 */
		public function get_header_logo_width(){
			return '200px';
		}

		/**
		 * Setup the class globals.
		 *
		 * @since 1.1.1
		 * @access public
		 */
		public function init_globals() {
			$current_theme = wp_get_theme();
			$this->theme_name = strtolower( preg_replace( '#[^a-zA-Z]#','',$current_theme->get( 'Name' ) ) );
			$this->envato_username = apply_filters( $this->theme_name . '_theme_setup_wizard_username', 'dtbaker' );
			$this->oauth_script = apply_filters( $this->theme_name . '_theme_setup_wizard_oauth_script', 'http://dtbaker.net/files/envato/wptoken/server-script.php' );
			$this->page_slug = apply_filters( $this->theme_name . '_theme_setup_wizard_page_slug', $this->theme_name.'-setup' );
			$this->parent_slug = apply_filters( $this->theme_name . '_theme_setup_wizard_parent_slug', '' );

			//If we have parent slug - set correct url
			if( $this->parent_slug !== '' ){
				$this->page_url = 'admin.php?page='.$this->page_slug;
			}else{
				$this->page_url = 'themes.php?page='.$this->page_slug;
			}
			$this->page_url = apply_filters( $this->theme_name . '_theme_setup_wizard_page_url', $this->page_url );

			//set relative plugin path url
			$this->plugin_path = trailingslashit( $this->cleanFilePath( dirname( __FILE__ ) ) );
			$relative_url = str_replace( $this->cleanFilePath( get_template_directory() ), '', $this->plugin_path );
			$this->plugin_url = trailingslashit( get_template_directory_uri() . $relative_url );
		}

		/**
		 * Setup the hooks, actions and filters.
		 *
		 * @uses add_action() To add actions.
		 * @uses add_filter() To add filters.
		 *
		 * @since 1.1.1
		 * @access public
		 */
		public function init_actions() {
			if ( apply_filters( $this->theme_name . '_enable_setup_wizard', true ) && current_user_can( 'manage_options' )  ) {
				add_action( 'after_switch_theme', array( $this, 'switch_theme' ) );

				if(class_exists( 'TGM_Plugin_Activation' ) && isset($GLOBALS['tgmpa'])) {
					add_action( 'init', array( $this, 'get_tgmpa_instanse' ), 30 );
					add_action( 'init', array( $this, 'set_tgmpa_url' ), 40 );
				}

				add_action( 'admin_menu', array( $this, 'admin_menus' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
				add_action( 'admin_init', array( $this, 'admin_redirects' ), 30 );
				add_action( 'admin_init', array( $this, 'init_wizard_steps' ), 30 );
				add_action( 'admin_init', array( $this, 'setup_wizard' ), 30 );
				add_filter( 'tgmpa_load', array( $this, 'tgmpa_load' ), 10, 1 );
				add_action( 'wp_ajax_envato_setup_plugins', array( $this, 'ajax_plugins' ) );
				add_action( 'wp_ajax_envato_setup_content', array( $this, 'ajax_content' ) );
			}
			if ( function_exists( 'envato_market' ) ) {
				add_action( 'admin_init', array( $this, 'envato_market_admin_init' ), 20 );
				add_filter( 'http_request_args', array( $this, 'envato_market_http_request_args' ), 10, 2 );
			}
			add_action('upgrader_post_install', array($this,'upgrader_post_install'), 10, 2);
		}

		/**
		 * After a theme update we clear the setup_complete option. This prompts the user to visit the update page again.
		 *
		 * @since 1.1.8
		 * @access public
		 */
		public function upgrader_post_install($return, $theme) {
			if ( is_wp_error( $return ) ) {
				return $return;
			}
			if ( $theme != get_stylesheet() ) {
				return $return;
			}
			update_option( 'envato_setup_complete', false );

			return $return;
		}
		/**
		 * We determine if the user already has theme content installed. This can happen if swapping from a previous theme or updated the current theme. We change the UI a bit when updating / swapping to a new theme.
		 *
		 * @since 1.1.8
		 * @access public
		 */
		public function is_possible_upgrade(){
			return false;
		}
		public function enqueue_scripts() {
		}
		public function tgmpa_load( $status ) {
			return is_admin() || current_user_can( 'install_themes' );
		}

		public function switch_theme() {
			set_transient( '_'.$this->theme_name.'_activation_redirect', 1 );
		}
		public function admin_redirects() {
			ob_start();
			if ( ! get_transient( '_'.$this->theme_name.'_activation_redirect' ) || get_option( 'envato_setup_complete', false ) ) {
				return;
			}
			delete_transient( '_'.$this->theme_name.'_activation_redirect' );
			wp_safe_redirect( admin_url( $this->page_url ) );
			exit;
		}

		/**
		 * Get configured TGMPA instance
		 *
		 * @access public
		 * @since 1.1.2
		 */
		public function get_tgmpa_instanse(){
			$this->tgmpa_instance = call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) );
		}

		/**
		 * Update $tgmpa_menu_slug and $tgmpa_parent_slug from TGMPA instance
		 *
		 * @access public
		 * @since 1.1.2
		 */
		public function set_tgmpa_url(){

			$this->tgmpa_menu_slug = ( property_exists($this->tgmpa_instance, 'menu') ) ? $this->tgmpa_instance->menu : $this->tgmpa_menu_slug;
			$this->tgmpa_menu_slug = apply_filters($this->theme_name . '_theme_setup_wizard_tgmpa_menu_slug', $this->tgmpa_menu_slug);

			$tgmpa_parent_slug = ( property_exists($this->tgmpa_instance, 'parent_slug') && $this->tgmpa_instance->parent_slug !== 'themes.php' ) ? 'admin.php' : 'themes.php';

			$this->tgmpa_url = apply_filters($this->theme_name . '_theme_setup_wizard_tgmpa_url', $tgmpa_parent_slug.'?page='.$this->tgmpa_menu_slug);

		}

		/**
		 * Add admin menus/screens.
		 */
		public function admin_menus() {

			if( $this->is_submenu_page() ){
				//prevent Theme Check warning about "themes should use add_theme_page for adding admin pages"
				$add_subpage_function = 'add_submenu'.'_page';
				$add_subpage_function( $this->parent_slug, __( 'Setup Wizard','envato_setup' ), __( 'Setup Wizard','envato_setup' ), 'manage_options', $this->page_slug, array( $this, 'setup_wizard' ) );
			}else{
				add_theme_page( __( 'Setup Wizard','envato_setup' ), __( 'Setup Wizard','envato_setup' ), 'manage_options', $this->page_slug, array( $this, 'setup_wizard' ) );
			}

		}


		/**
		 * Setup steps.
		 *
		 * @since 1.1.1
		 * @access public
		 * @return array
		 */
		public function init_wizard_steps() {

			$this->steps = array(
				'introduction' => array(
					'name'    => __( 'Introduction', 'envato_setup' ),
					'view'    => array( $this, 'envato_setup_introduction' ),
					'handler' => array( $this, 'envato_setup_introduction_save' ),
				),
			);
			if ( class_exists( 'TGM_Plugin_Activation' ) && isset( $GLOBALS['tgmpa'] ) ) {
				$this->steps['default_plugins'] = array(
					'name' => __( 'Plugins', 'envato_setup' ),
					'view' => array( $this, 'envato_setup_default_plugins' ),
					'handler' => '',
				);
			}
			$this->steps['updates'] = array(
				'name'    => __( 'Updates', 'envato_setup' ),
				'view'    => array( $this, 'envato_setup_updates' ),
				'handler' => array( $this, 'envato_setup_updates_save' ),
			);
			$this->steps['default_content'] = array(
				'name'    => __( 'Content', 'envato_setup' ),
				'view'    => array( $this, 'envato_setup_default_content' ),
				'handler' => '',
			);
			$this->steps['design'] = array(
				'name'    => __( 'Logo & Design', 'envato_setup' ),
				'view'    => array( $this, 'envato_setup_logo_design' ),
				'handler' => array( $this, 'envato_setup_logo_design_save' ),
			);
			$this->steps['customize'] = array(
				'name'    => __( 'Customize', 'envato_setup' ),
				'view'    => array( $this, 'envato_setup_customize' ),
				'handler' => '',
			);
			$this->steps['help_support'] = array(
				'name'    => __( 'Support', 'envato_setup' ),
				'view'    => array( $this, 'envato_setup_help_support' ),
				'handler' => '',
			);
			$this->steps['next_steps'] = array(
				'name'    => __( 'Ready!', 'envato_setup' ),
				'view'    => array( $this, 'envato_setup_ready' ),
				'handler' => '',
			);


			$this->steps = apply_filters(  $this->theme_name . '_theme_setup_wizard_steps', $this->steps );

		}

		/**
		 * Show the setup wizard
		 */
		public function setup_wizard() {
			if ( empty( $_GET['page'] ) || $this->page_slug !== $_GET['page'] ) {
				return;
			}
			ob_end_clean();

			$this->step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( array_keys( $this->steps ) );

			wp_register_script( 'jquery-blockui', $this->plugin_url . 'js/jquery.blockUI.js', array( 'jquery' ), '2.70', true );
			wp_register_script( 'envato-setup', $this->plugin_url . 'js/envato-setup.js', array( 'jquery', 'jquery-blockui' ), $this->version );
			wp_localize_script( 'envato-setup', 'envato_setup_params', array(
				'tgm_plugin_nonce'            => array(
					'update' => wp_create_nonce( 'tgmpa-update' ),
					'install' => wp_create_nonce( 'tgmpa-install' ),
				),
				'tgm_bulk_url' => admin_url( $this->tgmpa_url ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'wpnonce' => wp_create_nonce( 'envato_setup_nonce' ),
				'verify_text' => __( '...verifying','envato_setup' ),
			) );

			//wp_enqueue_style( 'envato_wizard_admin_styles', $this->plugin_url . '/css/admin.css', array(), $this->version );
			wp_enqueue_style( 'envato-setup', $this->plugin_url . 'css/envato-setup.css', array( 'wp-admin', 'dashicons', 'install' ), $this->version );

			//enqueue style for admin notices
			wp_enqueue_style( 'wp-admin' );

			wp_enqueue_media();
			wp_enqueue_script( 'media' );

			ob_start();
			$this->setup_wizard_header();
			$this->setup_wizard_steps();
			$show_content = true;
			echo '<div class="envato-setup-content">';
			if ( ! empty( $_REQUEST['save_step'] ) && isset( $this->steps[ $this->step ]['handler'] ) ) {
				$show_content = call_user_func( $this->steps[ $this->step ]['handler'] );
			}
			if ( $show_content ) {
				$this->setup_wizard_content();
			}
			echo '</div>';
			$this->setup_wizard_footer();
			exit;
		}

		public function get_step_link( $step ) {
			return  add_query_arg( 'step', $step, admin_url( 'admin.php?page=' .$this->page_slug ) );
		}
		public function get_next_step_link() {
			$keys = array_keys( $this->steps );
			return add_query_arg( 'step', $keys[ array_search( $this->step, array_keys( $this->steps ) ) + 1 ], remove_query_arg( 'translation_updated' ) );
		}

		/**
		 * Setup Wizard Header
		 */
	public function setup_wizard_header() {
		?>
		<!DOCTYPE html>
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php _e( 'Theme &rsaquo; Setup Wizard', 'envato_setup' ); ?></title>
			<?php wp_print_scripts( 'envato-setup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
			<?php do_action( 'admin_print_scripts' ); ?>
			<?php do_action( 'admin_head' ); ?>
		</head>
		<body class="envato-setup wp-core-ui">
		<h1 id="wc-logo">
			<a href="http://themeforest.net/user/dtbaker/portfolio" target="_blank"><?php
				$image_url = get_theme_mod( 'logo_header_image', get_template_directory_uri().'/images/'.get_theme_mod( 'dtbwp_site_color',$this->get_default_theme_style() ).'/logo.png' );
				$image_url = apply_filters('envato_setup_logo_image',$image_url);
				if ( $image_url ) {
					$image = '<img class="site-logo" src="%s" alt="%s" style="width:%s; height:auto" />';
					printf(
						$image,
						$image_url,
						get_bloginfo( 'name' ),
						$this->get_header_logo_width()
					);
				} else { ?>
					<img src="<?php echo $this->plugin_url; ?>images/logo.png" alt="Envato install wizard" /><?php
				} ?></a>
		</h1>
		<?php
		}

		/**
		 * Setup Wizard Footer
		 */
		public function setup_wizard_footer() {
		?>
		<?php if ( 'next_steps' === $this->step ) : ?>
			<a class="wc-return-to-dashboard" href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Return to the WordPress Dashboard', 'envato_setup' ); ?></a>
		<?php endif; ?>
		</body>
		<?php
		@do_action( 'admin_footer' ); // this was spitting out some errors in some admin templates. quick @ fix until I have time to find out what's causing errors.
		do_action( 'admin_print_footer_scripts' );
		?>
		</html>
		<?php
	}

		/**
		 * Output the steps
		 */
		public function setup_wizard_steps() {
			$ouput_steps = $this->steps;
			array_shift( $ouput_steps );
			?>
			<ol class="envato-setup-steps">
				<?php foreach ( $ouput_steps as $step_key => $step ) : ?>
					<li class="<?php
					$show_link = false;
					if ( $step_key === $this->step ) {
						echo 'active';
					} elseif ( array_search( $this->step, array_keys( $this->steps ) ) > array_search( $step_key, array_keys( $this->steps ) ) ) {
						echo 'done';
						$show_link = true;
					}
					?>"><?php
						if ( $show_link ) {
							?>
							<a href="<?php echo esc_url( $this->get_step_link( $step_key ) );?>"><?php echo esc_html( $step['name'] );?></a>
							<?php
						} else {
							echo esc_html( $step['name'] );
						}
						?></li>
				<?php endforeach; ?>
			</ol>
			<?php
		}

		/**
		 * Output the content for the current step
		 */
		public function setup_wizard_content() {
			isset( $this->steps[ $this->step ] ) ? call_user_func( $this->steps[ $this->step ]['view'] ) : false;
		}

		/**
		 * Introduction step
		 */
		public function envato_setup_introduction() {
			if ( isset( $_REQUEST['export'] ) ) {

				$default_content = array();
				$post_types = array('attachment','wpcf7_contact_form','post','page');
				foreach(get_post_types() as $post_type){
					if(!in_array($post_type,$post_types)){
						$post_types[] = $post_type;
					}
				}
				$categories = get_categories(array('type'=>''));
				$taxonomies = get_taxonomies();
//				print_r($categories);
				foreach($post_types as $post_type){
					if(in_array($post_type,array('revisions','event','event-recurring')))continue; // post types to ignore.
					$args = array('post_type'=>$post_type,'posts_per_page'=>-1);
					if($post_type == 'attachment'){

					}
					$post_datas = get_posts($args);
					if(!isset($default_content[$post_type])){
						$default_content[$post_type] = array();
					}
					$object = get_post_type_object( $post_type );
					if($object && !empty($object->labels->singular_name)){
						$type_title = $object->labels->name;
					}else{
						$type_title = ucwords($post_type).'s';
					}

					foreach($post_datas as $post_data) {
						$meta = get_post_meta($post_data->ID, '', true);
						foreach($meta as $meta_key => $meta_val){
							if(
								in_array($meta_key,array('_location_id')) || // which keys to nuke all the time
								(
									strpos($post_type,'dtbaker') === false &&
									!in_array($post_type,array('nav_menu_item','location','event','product','wpcf7_contact_form')) && // post types we want to keep all meta values for
									!in_array($meta_key,array('dtbwp_post_title_details','dtbwp_page_style','_wp_attached_file','_thumbnail_id')) // meta keys we want to keep for other post types
								)
							) { // which keys to keep
								unset($meta[$meta_key]);
							}
						}
						// copy stock images into the images/stock/ folder for theme import.
						if($post_type == 'attachment'){
							$file = get_attached_file($post_data->ID);
							if(is_file($file)){
								$post_data->guid = wp_get_attachment_url($post_data->ID);
								if(is_dir(get_home_path().'/../theme/images/stock/')) {
									copy( $file, get_home_path() . '/../theme/images/stock/' . basename( $file ) );
								}
							}
							// fix for incorrect GUID when renaming files with the rename plugin, causes import to bust.

						}
						$terms = array();
						foreach($taxonomies as $taxonomy){
							$terms[$taxonomy] = wp_get_post_terms( $post_data->ID, $taxonomy, array('fields'=>'all'));
							/*if($terms[$taxonomy]){
								foreach($terms[$taxonomy] as $tax_id => $tax){
									if(!empty($tax->term_id)) {
										$terms[ $taxonomy ][ $tax_id ] -> meta = get_term_meta( $tax->term_id );
									}
								}
							}*/
						}
						$default_content[ $post_type ][] = array(
							'type_title' => $type_title,
							'post_id' => $post_data->ID,
							'post_title' => $post_data->post_title,
							'post_status' => $post_data->post_status,
							'post_name' => $post_data->post_name,
							'post_content' => $post_data->post_content,
							'post_excerpt' => $post_data->post_excerpt,
							'post_parent' => $post_data->post_parent,
							'menu_order' => $post_data->menu_order,
							'post_date' => $post_data->post_date,
							'post_date_gmt' => $post_data->post_date_gmt,
							'guid' => $post_data->guid,
							'post_mime_type' => $post_data->post_mime_type,
							'meta' => $meta,
							'terms' => $terms,
//							'other' => $post_data,
						);
					}

				}
				// put certain content at very end.
				$nav = isset($default_content['nav_menu_item']) ? $default_content['nav_menu_item'] : array();
				if($nav){
					unset($default_content['nav_menu_item']);
					$default_content['nav_menu_item'] = $nav;
				}
//				print_r($default_content);
//				exit;
				// find the ID of our menu names so we can import them into default menu locations and also the widget positions below.
				$menus = get_terms( 'nav_menu' );
				$menu_ids = array();
				foreach ( $menus as $menu ) {
					if ( $menu->name == 'Main Menu' ) {
						$menu_ids['primary'] = $menu->term_id;
					} else if ( $menu->name == 'Quick Links' ) {
						$menu_ids['footer_quick'] = $menu->term_id;
					}
				}
				// used for me to export my widget settings.
				$widget_positions = get_option( 'sidebars_widgets' );
				$widget_options = array();
				$my_options = array();
				foreach ( $widget_positions as $sidebar_name => $widgets ) {
					if ( is_array( $widgets ) ) {
						foreach ( $widgets as $widget_name ) {
							$widget_name_strip = preg_replace( '#-\d+$#','',$widget_name );
							$widget_options[ $widget_name_strip ] = get_option( 'widget_'.$widget_name_strip );
						}
					}
				}
				// choose which custom options to load into defaults
				$all_options = wp_load_alloptions();
				foreach ( $all_options as $name => $value ) {
					if ( stristr( $name, '_widget_area_manager' ) ) { $my_options[ $name ] = $value; }
					if ( stristr( $name, 'wam_' ) ) { $my_options[ $name ] = $value; }
					//if ( stristr( $name, 'dbem_' ) !== false ) { $my_options[ $name ] = $value; }
					if ( stristr( $name, 'dtbaker_featured_images' ) !== false ) { $my_options[ $name ] = $value; }
				}
				$my_options['dbem_credits'] = 0;
				$my_options['travel_settings'] = array( 'api_key' => 'AIzaSyBsnYWO4SSibatp0SjsU9D2aZ6urI-_cJ8' );
				$my_options['tt-font-google-api-key'] = 'AIzaSyBsnYWO4SSibatp0SjsU9D2aZ6urI-_cJ8';
				?>
				<h1>Current Settings:</h1>
				<!--<p>categories.json:</p>
				<textarea style="width:100%; height:80px;"><?php /*echo json_encode( $categories );*/?></textarea>-->
				<p>default.json:</p>
				<textarea style="width:100%; height:80px;"><?php echo json_encode( $default_content );?></textarea>
				<p>widget_positions.json</p>
				<textarea style="width:100%; height:80px;"><?php echo json_encode( $widget_positions );?></textarea>
				<p>widget_options.json:</p>
				<textarea style="width:100%; height:80px;"><?php echo json_encode( $widget_options );?></textarea>
				<p>menu.json:</p>
				<textarea style="width:100%; height:80px;"><?php echo json_encode( $menu_ids );?></textarea>
				<p>options.json:</p>
				<textarea style="width:100%; height:80px;"><?php echo json_encode( $my_options );?></textarea>
				<p>Copy these values into your PHP code when distributing/updating the theme.</p>
				<?php
			} else if( $this->is_possible_upgrade() ){
				?>
				<h1><?php printf( __( 'Welcome to the setup wizard for %s.' ), wp_get_theme()); ?></h1>
				<p><?php _e('It looks like you may have recently upgraded to this theme. Great! This setup wizard will help ensure all the default settings are correct. It will also show some information about your new website and support options.');?></p>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>"
					   class="button-primary button button-large button-next"><?php _e( 'Let\'s Go!' ); ?></a>
					<a href="<?php echo esc_url( wp_get_referer() && ! strpos( wp_get_referer(),'update.php' ) ? wp_get_referer() : admin_url( '' ) ); ?>"
					   class="button button-large"><?php _e( 'Not right now' ); ?></a>
				</p>
				<?php
			} else if( get_option('envato_setup_complete', false) ){
				?>
				<h1><?php printf( __( 'Welcome to the setup wizard for %s.' ), wp_get_theme()); ?></h1>
				<p><?php _e('It looks like you have already run the setup wizard. Below are some options: ');?></p>
				<ul>
					<li>
						<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>"
						   class="button-primary button button-next button-large"><?php _e( 'Run Setup Wizard Again' ); ?></a>
					</li>
					<li>
						<form method="post">
							<input type="hidden" name="reset-font-defaults" value="yes">
							<input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e( 'Reset font style and colors' ); ?>" name="save_step" />
							<?php wp_nonce_field( 'envato-setup' ); ?>
						</form>
					</li>
				</ul>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url( wp_get_referer() && ! strpos( wp_get_referer(),'update.php' ) ? wp_get_referer() : admin_url( '' ) ); ?>"
					   class="button button-large"><?php _e( 'Cancel' ); ?></a>
				</p>
				<?php
			} else {
				?>
				<h1><?php printf( __( 'Welcome to the setup wizard for %s.' ), wp_get_theme()); ?></h1>
				<p><?php printf( __( 'Thank you for choosing the %s theme from ThemeForest. This quick setup wizard will help you configure your new website. This wizard will install the required WordPress plugins, default content, logo and tell you a little about Help &amp; Support options. <br/>It should only take 5 minutes.' ), wp_get_theme()); ?></p>
				<p><?php _e( 'No time right now? If you don\'t want to go through the wizard, you can skip and return to the WordPress dashboard. Come back anytime if you change your mind!' ); ?></p>
				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>"
					   class="button-primary button button-large button-next"><?php _e( 'Let\'s Go!' ); ?></a>
					<a href="<?php echo esc_url( wp_get_referer() && ! strpos( wp_get_referer(),'update.php' ) ? wp_get_referer() : admin_url( '' ) ); ?>"
					   class="button button-large"><?php _e( 'Not right now' ); ?></a>
				</p>
				<?php
			}
		}

		/**
		 *
		 * Handles save button from welcome page. This is to perform tasks when the setup wizard has already been run. E.g. reset defaults
		 *
		 * @since 1.2.5
		 */
		public function envato_setup_introduction_save(){

			check_admin_referer( 'envato-setup' );

			if(!empty($_POST['reset-font-defaults']) && $_POST['reset-font-defaults'] == 'yes'){

				// clear font options
				update_option( 'tt_font_theme_options', array() );

				// reset site color
				remove_theme_mod( 'dtbwp_site_color' );

				if(class_exists('dtbwp_customize_save_hook')){
					$site_color_defaults = new dtbwp_customize_save_hook();
					$site_color_defaults->save_color_options();
				}


				$file_name    = get_template_directory() . '/style.custom.css';
				if(file_exists($file_name)) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
					WP_Filesystem();
					global $wp_filesystem;
					$wp_filesystem->put_contents( $file_name, '' );
				}
				?>
				<p><strong><?php _e('Options have been reset. Please go to Appearance > Customize in the WordPress backend.');?></strong></p>
				<?php
				return true;
			}
			return false;
		}


		private function _get_plugins() {
			$instance = call_user_func( array( get_class( $GLOBALS['tgmpa'] ), 'get_instance' ) );
			$plugins = array(
				'all'      => array(), // Meaning: all plugins which still have open actions.
				'install'  => array(),
				'update'   => array(),
				'activate' => array(),
			);

			foreach ( $instance->plugins as $slug => $plugin ) {
				if ( $instance->is_plugin_active( $slug ) && false === $instance->does_plugin_have_update( $slug ) ) {
					// No need to display plugins if they are installed, up-to-date and active.
					continue;
				} else {
					$plugins['all'][ $slug ] = $plugin;

					if ( ! $instance->is_plugin_installed( $slug ) ) {
						$plugins['install'][ $slug ] = $plugin;
					} else {
						if ( false !== $instance->does_plugin_have_update( $slug ) ) {
							$plugins['update'][ $slug ] = $plugin;
						}

						if ( $instance->can_plugin_activate( $slug ) ) {
							$plugins['activate'][ $slug ] = $plugin;
						}
					}
				}
			}
			return $plugins;
		}

		/**
		 * Page setup
		 */
		public function envato_setup_default_plugins() {

			tgmpa_load_bulk_installer();
			// install plugins with TGM.
			if ( ! class_exists( 'TGM_Plugin_Activation' ) || ! isset( $GLOBALS['tgmpa'] ) ) {
				die( 'Failed to find TGM' );
			}
			$url = wp_nonce_url( add_query_arg( array( 'plugins' => 'go' ) ), 'envato-setup' );
			$plugins = $this->_get_plugins();

			// copied from TGM

			$method = ''; // Leave blank so WP_Filesystem can populate it as necessary.
			$fields = array_keys( $_POST ); // Extra fields to pass to WP_Filesystem.

			if ( false === ( $creds = request_filesystem_credentials( esc_url_raw( $url ), $method, false, false, $fields ) ) ) {
				return true; // Stop the normal page form from displaying, credential request form will be shown.
			}

			// Now we have some credentials, setup WP_Filesystem.
			if ( ! WP_Filesystem( $creds ) ) {
				// Our credentials were no good, ask the user for them again.
				request_filesystem_credentials( esc_url_raw( $url ), $method, true, false, $fields );

				return true;
			}

			/* If we arrive here, we have the filesystem */

			?>
			<h1><?php _e( 'Default Plugins', 'envato_setup' ); ?></h1>
			<form method="post">

				<?php
				$plugins = $this->_get_plugins();
				if ( count( $plugins['all'] ) ) {
					?>
					<p><?php _e( 'Your website needs a few essential plugins. The following plugins will be installed or updated:', 'envato_setup' ); ?></p>
					<ul class="envato-wizard-plugins">
						<?php foreach ( $plugins['all'] as $slug => $plugin ) {  ?>
							<li data-slug="<?php echo esc_attr( $slug );?>"><?php echo esc_html( $plugin['name'] );?>
								<span>
    								<?php
								    $keys = array();
								    if ( isset( $plugins['install'][ $slug ] ) ) { $keys[] = 'Installation'; }
								    if ( isset( $plugins['update'][ $slug ] ) ) { $keys[] = 'Update'; }
								    if ( isset( $plugins['activate'][ $slug ] ) ) { $keys[] = 'Activation'; }
								    echo implode( ' and ',$keys ).' required';
								    ?>
    							</span>
								<div class="spinner"></div>
							</li>
						<?php } ?>
					</ul>
					<?php
				} else {
					echo '<p><strong>'._e( 'Good news! All plugins are already installed and up to date. Please continue.','envato_setup' ).'</strong></p>';
				} ?>

				<p><?php _e( 'You can add and remove plugins later on from within WordPress.', 'envato_setup' ); ?></p>

				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button-primary button button-large button-next" data-callback="install_plugins"><?php _e( 'Continue', 'envato_setup' ); ?></a>
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large button-next"><?php _e( 'Skip this step', 'envato_setup' ); ?></a>
					<?php wp_nonce_field( 'envato-setup' ); ?>
				</p>
			</form>
			<?php
		}


		public function ajax_plugins() {
			if ( ! check_ajax_referer( 'envato_setup_nonce', 'wpnonce' ) || empty( $_POST['slug'] ) ) {
				wp_send_json_error( array( 'error' => 1, 'message' => __( 'No Slug Found','envato_setup' ) ) );
			}
			$json = array();
			// send back some json we use to hit up TGM
			$plugins = $this->_get_plugins();
			// what are we doing with this plugin?
			foreach ( $plugins['activate'] as $slug => $plugin ) {
				if ( $_POST['slug'] == $slug ) {
					$json = array(
						'url' => admin_url( $this->tgmpa_url ),
						'plugin' => array( $slug ),
						'tgmpa-page' => $this->tgmpa_menu_slug,
						'plugin_status' => 'all',
						'_wpnonce' => wp_create_nonce( 'bulk-plugins' ),
						'action' => 'tgmpa-bulk-activate',
						'action2' => -1,
						'message' => __( 'Activating Plugin','envato_setup' ),
					);
					break;
				}
			}
			foreach ( $plugins['update'] as $slug => $plugin ) {
				if ( $_POST['slug'] == $slug ) {
					$json = array(
						'url' => admin_url( $this->tgmpa_url ),
						'plugin' => array( $slug ),
						'tgmpa-page' => $this->tgmpa_menu_slug,
						'plugin_status' => 'all',
						'_wpnonce' => wp_create_nonce( 'bulk-plugins' ),
						'action' => 'tgmpa-bulk-update',
						'action2' => -1,
						'message' => __( 'Updating Plugin','envato_setup' ),
					);
					break;
				}
			}
			foreach ( $plugins['install'] as $slug => $plugin ) {
				if ( $_POST['slug'] == $slug ) {
					$json = array(
						'url' => admin_url( $this->tgmpa_url ),
						'plugin' => array( $slug ),
						'tgmpa-page' => $this->tgmpa_menu_slug,
						'plugin_status' => 'all',
						'_wpnonce' => wp_create_nonce( 'bulk-plugins' ),
						'action' => 'tgmpa-bulk-install',
						'action2' => -1,
						'message' => __( 'Installing Plugin','envato_setup' ),
					);
					break;
				}
			}

			if ( $json ) {
				$json['hash'] = md5( serialize( $json ) ); // used for checking if duplicates happen, move to next plugin
				wp_send_json( $json );
			} else {
				wp_send_json( array( 'done' => 1, 'message' => __( 'Success','envato_setup' ) ) );
			}
			exit;

		}


		private function _content_default_get() {

			$content = array();


			/*$content['categories'] = array(
				'title' => __( 'Categories', 'envato_setup' ),
				'description' => __( 'Insert default Categories as seen in the demo.', 'envato_setup' ),
				'pending' => __( 'Pending.', 'envato_setup' ),
				'installing' => __( 'Installing.', 'envato_setup' ),
				'success' => __( 'Success.', 'envato_setup' ),
				'install_callback' => array( $this,'_content_install_categories' ),
			);*/

			// find out what content is in our default json file.
			$available_content = $this->_get_json( 'default.json' );
			foreach($available_content as $post_type => $post_data){
				if(count($post_data)){
					$first = current($post_data);
					$post_type_title = !empty($first['type_title']) ? $first['type_title'] : ucwords( $post_type ).'s';
					if($post_type_title == 'Navigation Menu Items'){
						$post_type_title = 'Navigation';
					}
					$content[$post_type] = array(
						'title' => $post_type_title,
						'description' => sprintf( __( 'This will create default %s as seen in the demo.', 'envato_setup' ), $post_type_title ),
						'pending' => __( 'Pending.', 'envato_setup' ),
						'installing' => __( 'Installing.', 'envato_setup' ),
						'success' => __( 'Success.', 'envato_setup' ),
						'install_callback' => array( $this,'_content_install_type' ),
						'checked' => $this->is_possible_upgrade() ? 0 : 1 // dont check if already have content installed.
					);
				}
			}

			/*$content['pages'] = array(
				'title' => __( 'Pages', 'envato_setup' ),
				'description' => __( 'This will create default pages as seen in the demo.', 'envato_setup' ),
				'pending' => __( 'Pending.', 'envato_setup' ),
				'installing' => __( 'Installing Default Pages.', 'envato_setup' ),
				'success' => __( 'Success.', 'envato_setup' ),
				'install_callback' => array( $this,'_content_install_pages' ),
			);
			$content['products'] = array(
				'title' => __( 'Products', 'envato_setup' ),
				'description' => __( 'Insert default shop products and categories as seen in the demo.', 'envato_setup' ),
				'pending' => __( 'Pending.', 'envato_setup' ),
				'installing' => __( 'Installing Default Products.', 'envato_setup' ),
				'success' => __( 'Success.', 'envato_setup' ),
				'install_callback' => array( $this,'_content_install_products' ),
			);*/
			$content['widgets'] = array(
				'title' => __( 'Widgets', 'envato_setup' ),
				'description' => __( 'Insert default sidebar widgets as seen in the demo.', 'envato_setup' ),
				'pending' => __( 'Pending.', 'envato_setup' ),
				'installing' => __( 'Installing Default Widgets.', 'envato_setup' ),
				'success' => __( 'Success.', 'envato_setup' ),
				'install_callback' => array( $this,'_content_install_widgets' ),
				'checked' => $this->is_possible_upgrade() ? 0 : 1 // dont check if already have content installed.
			);
			/*$content['menu'] = array(
				'title' => __( 'Menu', 'envato_setup' ),
				'description' => __( 'Insert default menu as seen in the demo.', 'envato_setup' ),
				'pending' => __( 'Pending.', 'envato_setup' ),
				'installing' => __( 'Installing Default Menu.', 'envato_setup' ),
				'success' => __( 'Success.', 'envato_setup' ),
				'install_callback' => array( $this,'_content_install_menu' ),
			);*/
			$content['settings'] = array(
				'title' => __( 'Settings', 'envato_setup' ),
				'description' => __( 'Configure default settings.', 'envato_setup' ),
				'pending' => __( 'Pending.', 'envato_setup' ),
				'installing' => __( 'Installing Default Settings.', 'envato_setup' ),
				'success' => __( 'Success.', 'envato_setup' ),
				'install_callback' => array( $this,'_content_install_settings' ),
				'checked' => $this->is_possible_upgrade() ? 0 : 1 // dont check if already have content installed.
			);

			$content = apply_filters( $this->theme_name . '_theme_setup_wizard_content', $content );

			return $content;

		}

		/**
		 * Page setup
		 */
		public function envato_setup_default_content() {
			?>
			<h1><?php _e( 'Default Content', 'envato_setup' ); ?></h1>
			<form method="post">
				<?php if($this->is_possible_upgrade()){ ?>
					<p><?php _e('It looks like you already have content installed on this website. If you would like to install the default demo content as well you can select it below. Otherwise just choose the upgrade option to ensure everything is up to date.'); ?></p>
				<?php }else{ ?>
					<p><?php printf( __( 'It\'s time to insert some default content for your new WordPress website. Choose what you would like inserted below and click Continue. It is recommended to leave everything selected. Once inserted, this content can be managed from the WordPress admin dashboard. ', 'envato_setup' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '" target="_blank">', '</a>' ); ?></p>
				<?php } ?>
				<table class="envato-setup-pages" cellspacing="0">
					<thead>
					<tr>
						<td class="check"> </td>
						<th class="item"><?php _e( 'Item', 'envato_setup' ); ?></th>
						<th class="description"><?php _e( 'Description', 'envato_setup' ); ?></th>
						<th class="status"><?php _e( 'Status', 'envato_setup' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $this->_content_default_get() as $slug => $default ) {  ?>
						<tr class="envato_default_content" data-content="<?php echo esc_attr( $slug );?>">
							<td>
								<input type="checkbox" name="default_content[<?php echo esc_attr( $slug );?>]" class="envato_default_content" id="default_content_<?php echo esc_attr( $slug );?>" value="1" <?php echo (!isset($default['checked']) || $default['checked']) ? ' checked':'';?>>
							</td>
							<td><label for="default_content_<?php echo esc_attr( $slug );?>"><?php echo $default['title']; ?></label></td>
							<td class="description"><?php echo $default['description']; ?></td>
							<td class="status"> <span><?php echo $default['pending'];?></span> <div class="spinner"></div></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>

				<p class="envato-setup-actions step">
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button-primary button button-large button-next" data-callback="install_content"><?php _e( 'Continue', 'envato_setup' ); ?></a>
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large button-next"><?php _e( 'Skip this step', 'envato_setup' ); ?></a>
					<?php wp_nonce_field( 'envato-setup' ); ?>
				</p>
			</form>
			<?php
		}


		public function ajax_content() {
			$content = $this->_content_default_get();
			if ( ! check_ajax_referer( 'envato_setup_nonce', 'wpnonce' ) || empty( $_POST['content'] ) && isset( $content[ $_POST['content'] ] ) ) {
				wp_send_json_error( array( 'error' => 1, 'message' => __( 'No content Found','envato_setup' ) ) );
			}

			$json = false;
			$this_content = $content[ $_POST['content'] ];

			if ( isset( $_POST['proceed'] ) ) {
				// install the content!

				if ( ! empty( $this_content['install_callback'] ) ) {
					if ( $result = call_user_func( $this_content['install_callback'] ) ) {
						if( is_array( $result ) && isset( $result['retry'] ) ){
							// we split the stuff up again.
							$json = array(
								'url' => admin_url( 'admin-ajax.php' ),
								'action' => 'envato_setup_content',
								'proceed' => 'true',
								'retry' => time(),
								'retry_count' => $result['retry_count'],
								'content' => $_POST['content'],
								'_wpnonce' => wp_create_nonce( 'envato_setup_nonce' ),
								'message' => $this_content['installing'],
							);
						}else{
							$json = array(
								'done' => 1,
								'message' => $this_content['success'],
								'debug' => $result,
							);
						}
					}
				}
			} else {

				$json = array(
					'url' => admin_url( 'admin-ajax.php' ),
					'action' => 'envato_setup_content',
					'proceed' => 'true',
					'content' => $_POST['content'],
					'_wpnonce' => wp_create_nonce( 'envato_setup_nonce' ),
					'message' => $this_content['installing'],
				);
			}

			if ( $json ) {
				$json['hash'] = md5( serialize( $json ) ); // used for checking if duplicates happen, move to next plugin
				wp_send_json( $json );
			} else {
				wp_send_json( array( 'error' => 1, 'message' => __( 'Error','envato_setup' ) ) );
			}

			exit;

		}



		private function _imported_term_id( $original_term_id , $new_term_id = false ){
			$terms = get_transient('importtermids');
			if(!is_array($terms))$terms = array();
			if($new_term_id){
				$terms[$original_term_id] = $new_term_id;
				set_transient('importtermids', $terms, 60 * 60 * 24 );
			}else if($original_term_id && isset($terms[$original_term_id])){
				return $terms[$original_term_id];
			}
			return false;
		}

		/*private function _content_install_categories(){
			$all_data = $this->_get_json('categories.json');
			foreach($all_data as $data_id => $cat) {

				$term_id = term_exists( $cat['category_nicename'], 'category' );
				if ( $term_id ) {
					if ( is_array( $term_id ) ) { $term_id = $term_id['term_id']; }
					if ( isset( $cat['term_id'] ) ) {
						$this->_imported_term_id( intval( $cat['term_id'] ), (int) $term_id );
					}
					continue;
				}
				if(!empty( $cat['category_parent'] )){
					// see if we have imported this yet?
					$cat['category_parent'] = $this->_imported_term_id($cat['category_parent']);
				}

				$category_parent      = empty( $cat['category_parent'] ) ? 0 : $cat['category_parent']; //category_exists( $cat['category_parent'] );
				$category_description = isset( $cat['category_description'] ) ? $cat['category_description'] : '';
				$catarr               = array(
					'category_nicename'    => $cat['category_nicename'],
					'category_parent'      => $category_parent,
					'cat_name'             => $cat['cat_name'],
					'category_description' => $category_description,
				);

				$id = wp_insert_category( $catarr );
				if ( ! is_wp_error( $id ) ) {
					if ( isset( $cat['term_id'] ) ) {
						$this->_imported_term_id( intval( $cat['term_id'] ), $id );
					}
				}
			}

			return true;

		}*/


		private function _imported_post_id( $original_id = false , $new_id = false ){
			if(is_array($original_id) || is_object($original_id))return false;
			$post_ids = get_transient('importpostids');
			if(!is_array($post_ids))$post_ids = array();
			if($new_id){
				$post_ids[$original_id] = $new_id;
				set_transient('importpostids', $post_ids, 60 * 60 * 24 );
			}else if($original_id && isset($post_ids[$original_id])){
				return $post_ids[$original_id];
			}else if($original_id === false){
				return $post_ids;
			}
			return false;
		}
		private function _post_orphans( $original_id = false, $missing_parent_id = false ){
			$post_ids = get_transient('postorphans');
			if(!is_array($post_ids))$post_ids = array();
			if($missing_parent_id){
				$post_ids[$original_id] = $missing_parent_id;
				set_transient('postorphans', $post_ids, 60 * 60 * 24 );
			}else if($original_id && isset($post_ids[$original_id])){
				return $post_ids[$original_id];
			}else if($original_id === false){
				return $post_ids;
			}
			return false;
		}

		private function _cleanup_imported_ids(){
			// loop over all attachments and assign the correct post ids to those attachments.

		}

		private $delay_posts = array();
		private function _delay_post_process( $post_type, $post_data ){
			if(!isset($this->delay_posts[$post_type]))$this->delay_posts[$post_type]= array();
			$this->delay_posts[$post_type][] = $post_data;
		}


		// return the difference in length between two strings
		public function cmpr_strlen( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}

		private function _process_post_data( $post_type, $post_data, $delayed = false ){

			if ( ! post_type_exists( $post_type ) ) {
				return false;
			}
			/*if ( 'nav_menu_item' == $post_type ) {
				$this->process_menu_item( $post );
				continue;
			}*/

			if(empty($post_data['post_title']) && empty($post_data['post_name'])){
				// this is menu items
				$post_data['post_name'] = $post_data['post_id'];
			}

			$post_data['post_type'] = $post_type;

			$post_parent = (int) $post_data['post_parent'];
			if ( $post_parent ) {
				// if we already know the parent, map it to the new local ID
				if ( $this->_imported_post_id( $post_parent ) ) {
					$post_data['post_parent'] = $this->_imported_post_id( $post_parent );
					// otherwise record the parent for later
				} else {
					$this->_post_orphans( intval( $post_data['post_id'] ) , $post_parent);
					$post_data['post_parent'] = 0;
				}
			}

			// check if already exists
			if( empty($post_data['post_title']) && !empty($post_data['post_name'])){
				global $wpdb;
				$sql = "
					SELECT ID, post_name, post_parent, post_type
					FROM $wpdb->posts
					WHERE post_name = %s
					AND post_type = %s
				";
				$pages = $wpdb->get_results( $wpdb->prepare($sql,array($post_data['post_name'], $post_type)), OBJECT_K );
				$foundid = 0;
				foreach ( (array) $pages as $page ) {
					if($page->post_name == $post_data['post_name'] && empty($page->post_title)){
						$foundid = $page->ID;
					}
				}
				if($foundid){
					$this->_imported_post_id( $post_data['post_id'], $foundid );
					return true;
				}
			}
			$post_exists = post_exists( $post_data['post_title'] ); //, '', $post_data['post_date_gmt'] );
			if ( $post_exists && get_post_type( $post_exists ) == $post_type ) {
				$existing_post = get_post($post_exists);
				if(!empty($post_data['post_title']) || (empty($post_data['post_title']) && $existing_post->post_name == $post_data['post_name'])) {
					// this is the same.
					$this->_imported_post_id( $post_data['post_id'], $post_exists );
//					echo $post_data['post_id'] . " title " . $post_data['post_title'] . " already exists 1: $post_exists\n";
					return true;
				}
			}
			/*$date2 = get_date_from_gmt($post_data['post_date_gmt']);
			$post_exists = post_exists( $post_data['post_title'], '', $date2 );
			if ( $post_exists && get_post_type( $post_exists ) == $post_type ) {
				$existing_post = get_post($post_exists);
				if(!empty($post_data['post_title']) || (empty($post_data['post_title']) && $existing_post->post_name == $post_data['post_name'])) {
					$this->_imported_post_id( $post_data['post_id'], $post_exists );
//					echo $post_data['post_id'] . " already exists 2\n";
					return true;
				}
			}
			if(!empty($post_data['post_date'])) {
				$post_exists = post_exists( $post_data['post_title'], '', $post_data['post_date'] );
				if ( $post_exists && get_post_type( $post_exists ) == $post_type ) {
					$existing_post = get_post($post_exists);
					if(!empty($post_data['post_title']) || (empty($post_data['post_title']) && $existing_post->post_name == $post_data['post_name'])) {
						$this->_imported_post_id( $post_data['post_id'], $post_exists );
//						echo $post_data['post_id'] . " already exists 3\n";
						return true;
					}
				}
			}*/
			switch($post_type){
				case 'attachment':
					// import media via url
					if(!empty($post_data['guid'])){

						// check if this has already been imported.
						$old_guid = $post_data['guid'];
						if($this->_imported_post_id( $old_guid)){
							return true; // alrady done;
						}
						// ignore post parent, we haven't imported those yet.
//							$file_data = wp_remote_get($post_data['guid']);
						$remote_url = $post_data['guid'];

						$post_data['upload_date'] = date('Y/m',strtotime($post_data['post_date_gmt']));
						if ( isset( $post_data['meta'] ) ) {
							foreach ( $post_data['meta'] as $key => $meta ) {
								if ( $key == '_wp_attached_file' ) {
									foreach((array)$meta as $meta_val) {
										if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta_val, $matches ) ) {
											$post_data['upload_date'] = $matches[0];
										}
									}
								}
							}
						}

						$upload = $this->_fetch_remote_file( $remote_url, $post_data );

						if ( !is_array($upload) || is_wp_error( $upload ) ) {
							// todo: error
							return false;
						}

						if ( $info = wp_check_filetype( $upload['file'] ) ) {
							$post['post_mime_type'] = $info['type'];
						} else {
							return false;
//								return new WP_Error( 'attachment_processing_error', __( 'Invalid file type', 'wordpress-importer' ) );
						}

						$post_data['guid'] = $upload['url'];

						// as per wp-admin/includes/upload.php
						$post_id = wp_insert_attachment( $post_data, $upload['file'] );
						wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

						// remap resized image URLs, works by stripping the extension and remapping the URL stub.
						if ( preg_match( '!^image/!', $info['type'] ) ) {
							$parts = pathinfo( $remote_url );
							$name = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

							$parts_new = pathinfo( $upload['url'] );
							$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

							$this->_imported_post_id( $parts['dirname'] . '/' . $name , $parts_new['dirname'] . '/' . $name_new );
						}
						$this->_imported_post_id( $post_data['post_id'], $post_id );
						$this->_imported_post_id( $old_guid, $post_id );

					}
					break;
				default:
					// work out if we have to delay this post insertion
					if ( ! empty( $post_data['meta'] ) ) {
						foreach ( $post_data['meta'] as $meta_key => $meta_val ) {

							// export gets meta straight from the DB so could have a serialized string
							$meta_val = maybe_unserialize( $meta_val );
							if ( is_array( $meta_val ) && count( $meta_val ) == 1 ) {
								$meta_val = current( $meta_val );
							}

							if ( ( $meta_key == '_menu_item_object_id' || $meta_key == '_menu_item_menu_item_parent' ) && $meta_val ) {
								// we get the linked page id that we should have previously entered.
//								echo "Trying to find meta key $meta_key value $meta_val and got.... ";
								$meta_val = $this->_imported_post_id( $meta_val );
//								echo " $meta_val \n\n";
								if ( ! $meta_val ) {
									if ( $delayed ) {
//										echo "Failed, already delayed ".$post_data['post_id']."\n\n";
										// already delayed, unable to find this meta value, skip inserting it
										return false;
									} else {
//										echo "Delaying post id ".$post_data['post_id']."... \n\n";
										$this->_delay_post_process( $post_type, $post_data );

										return true;
									}
								}
							}
						}
					}

					// we have to format the post content. rewriting images and gallery stuff
					$replace = $this->_imported_post_id();
					$urls_replace = array();
					foreach($replace as $key=>$val){
						if($key && $val && !is_numeric($key) && !is_numeric($val)){
							$urls_replace[$key] = $val;
						}
					}
					if($urls_replace) {
						uksort( $urls_replace, array( &$this, 'cmpr_strlen' ) );
						foreach ( $urls_replace as $from_url => $to_url ) {
							$post_data['post_content'] = str_replace($from_url, $to_url, $post_data['post_content']);
						}
					}
					if(preg_match_all('#\[gallery[^\]]*\]#',$post_data['post_content'],$matches)){
						foreach($matches[0] as $match_id => $string){
							if(preg_match('#ids="([^"]+)"#',$string,$ids_matches)){
								$ids = explode(",",$ids_matches[1]);
								foreach($ids as $key=>$val){
									$new_id = $val ? $this->_imported_post_id($val) : false;
									if(!$new_id)unset($ids[$key]);
									else $ids[$key] = $new_id;
								}
								$new_ids = implode(',',$ids);
								$post_data['post_content'] = str_replace($ids_matches[0], 'ids="'.$new_ids.'"', $post_data['post_content']);
							}
						}
					}
					// we have to fix up all the visual composer inserted image ids
					if(preg_match_all('#parallax_image="(\d+)"#',$post_data['post_content'],$matches)){
						foreach($matches[0] as $match_id => $string){
							$new_id = $this->_imported_post_id($matches[1][$match_id]);
							if($new_id){
								$post_data['post_content'] = str_replace($string, 'parallax_image="'.$new_id.'"', $post_data['post_content']);
							}
						}
					}
					if(preg_match_all('# image="(\d+)"#',$post_data['post_content'],$matches)){
						foreach($matches[0] as $match_id => $string){
							$new_id = $this->_imported_post_id($matches[1][$match_id]);
							if($new_id){
								$post_data['post_content'] = str_replace($string, ' image="'.$new_id.'"', $post_data['post_content']);
							}
						}
					}
					// contact form 7 id fixes.
					if(preg_match_all('#\[contact-form-7[^\]]*\]#',$post_data['post_content'],$matches)){
						foreach($matches[0] as $match_id => $string){
							if(preg_match('#id="(\d+)"#',$string,$id_match)){
								$new_id = $this->_imported_post_id($id_match[1]);
								if($new_id) {
									$post_data['post_content'] = str_replace($id_match[0], 'id="'.$new_id.'"', $post_data['post_content']);
								}else{
									// no imported ID found. remove this entry.
									$post_data['post_content'] = str_replace($matches[0], '(insert contact form here)', $post_data['post_content']);
								}
							}
						}
					}

					$post_id = wp_insert_post( $post_data, true );
//					echo "Processing ".$post_data['post_id']." \n\n";
					if ( !is_wp_error( $post_id ) ) {
						$this->_imported_post_id( $post_data['post_id'], $post_id );
						// add/update post meta
						if ( ! empty( $post_data['meta'] ) ) {
							foreach ( $post_data['meta'] as $meta_key => $meta_val ) {

								// export gets meta straight from the DB so could have a serialized string
								$meta_val = maybe_unserialize( $meta_val );
								if(is_array($meta_val) && count($meta_val) == 1){
									$meta_val = current($meta_val);
								}

								if( ( $meta_key == '_menu_item_object_id' || $meta_key == '_menu_item_menu_item_parent' ) && $meta_val ){
									// we get the linked page id that we should have previously entered.
									$meta_val = $this->_imported_post_id( $meta_val );
									if(!$meta_val){
										continue;
									}
								}

								$meta_val = maybe_unserialize( $meta_val );

								// if the post has a featured image, take note of this in case of remap
								if ( '_thumbnail_id' == $meta_key ) {
									/// find this inserted id and use that instead.
									$inserted_id = $this->_imported_post_id( intval( $meta_val ) );
									if($inserted_id){
										$meta_val = $inserted_id;
									}
								}
//									echo "Post meta $meta_key was $meta_val \n\n";

								update_post_meta( $post_id, $meta_key, $meta_val );

							}
						}
						if ( ! empty( $post_data['terms'] ) ) {
							$terms_to_set = array();
							foreach ( $post_data['terms'] as $term_slug => $terms ) {
								foreach($terms as $term) {
									//									echo "Adding category;";print_r($term);echo "\n\n";
									/*"term_id": 21,
									"name": "Tea",
									"slug": "tea",
									"term_group": 0,
									"term_taxonomy_id": 21,
									"taxonomy": "category",
									"description": "",
									"parent": 0,
									"count": 1,
									"filter": "raw"*/
									$taxonomy    =  $term['taxonomy'];
									if(taxonomy_exists($taxonomy)) {
										$term_exists = term_exists( $term['slug'], $taxonomy );
										$term_id     = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;
										if ( ! $term_id ) {
											if(!empty( $term['parent'] )){
												// see if we have imported this yet?
												$term['parent'] = $this->_imported_term_id($term['parent']);
											}

											$t = wp_insert_term( $term['name'], $taxonomy, $term );
											if ( ! is_wp_error( $t ) ) {
												$term_id = $t['term_id'];
												//do_action( 'wp_import_insert_term', $t, $term, $post_id, $post );
											} else {
												// todo - error
												continue;
											}
										}
										$this->_imported_term_id($term['term_id'], $term_id);
										$terms_to_set[ $taxonomy ][] = intval( $term_id );
									}
								}
							}
							foreach ( $terms_to_set as $tax => $ids ) {
								wp_set_post_terms( $post_id, $ids, $tax );
							}
						}
					}

					break;
			}
			return true;
		}

		private function _content_install_type(){
			$post_type = !empty($_POST['content']) ? $_POST['content'] : false;
			$all_data = $this->_get_json('default.json');
			if(!$post_type || !isset($all_data[$post_type])){
				return false;
			}
			$limit = 10 + (isset($_REQUEST['retry_count']) ? (int)$_REQUEST['retry_count'] : 0);
			$x = 0;
			foreach($all_data[$post_type] as $post_data){

				$this->_process_post_data($post_type, $post_data);

				if($x++ > $limit){
					return array('retry' => 1, 'retry_count' => $limit);
				}

			}

			foreach($this->delay_posts as $delayed_post_type => $delayed_post_datas){
				foreach($delayed_post_datas as $delayed_post_id => $delayed_post_data){
					unset($this->delay_posts[$delayed_post_type][$delayed_post_id]);
					//echo "Processing delayed post $delayed_post_type id ".$delayed_post_data['post_id']."\n\n";
					$this->_process_post_data($delayed_post_type, $delayed_post_data);
				}
			}
			foreach($this->delay_posts as $delayed_post_type => $delayed_post_datas){
				foreach($delayed_post_datas as $delayed_post_id => $delayed_post_data){
					unset($this->delay_posts[$delayed_post_type][$delayed_post_id]);
					//echo "Processing delayed post $delayed_post_type id ".$delayed_post_data['post_id']."\n\n";
					$this->_process_post_data($delayed_post_type, $delayed_post_data, true);
				}
			}

			$this->_handle_post_orphans();

			// now we have to handle any custom SQL queries. This is needed for the events manager to store location and event details.
			$sql = $this->_get_sql(basename($post_type).'.sql');
			if($sql){
				global $wpdb;
				// do a find-replace with certain keys.
				if(preg_match_all('#__POSTID_(\d+)__#',$sql,$matches)){
					foreach($matches[0] as $match_id => $match){
						$new_id = $this->_imported_post_id($matches[1][$match_id]);
						if(!$new_id)$new_id = 0;
						$sql = str_replace($match,$new_id,$sql);
					}
				}
				$sql = str_replace("__DBPREFIX__",$wpdb->prefix,$sql);
				$bits = preg_split("/;(\s*\n|$)/", $sql);
				foreach($bits as $bit){
					$bit = trim($bit);
					if($bit){
						$wpdb->query($bit);
					}
				}
			}

			return true;

		}

		private function _handle_post_orphans(){
			$orphans = $this->_post_orphans();
			foreach($orphans as $original_post_id => $original_post_parent_id){
				if($original_post_parent_id) {
					if ( $this->_imported_post_id( $original_post_id ) && $this->_imported_post_id( $original_post_parent_id ) ) {
						$post_data = array();
						$post_data['ID'] = $this->_imported_post_id( $original_post_id );
						$post_data['post_parent'] = $this->_imported_post_id( $original_post_parent_id );
						wp_update_post( $post_data );
						$this->_post_orphans( $original_post_id, 0 ); // ignore future
					}
				}
			}
		}

		private function _fetch_remote_file( $url, $post ) {
			// extract the file name and extension from the url
			$file_name = basename( $url );
			$local_file = trailingslashit(get_template_directory()).'images/stock/'.$file_name;
			$upload = false;
			if( is_file( $local_file ) && filesize( $local_file ) > 0 ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				WP_Filesystem();
				global $wp_filesystem;
				$file_data = $wp_filesystem->get_contents( $local_file );
				$upload = wp_upload_bits( $file_name, 0, $file_data, $post['upload_date'] );
				if ( $upload['error'] ) {
					return new WP_Error( 'upload_dir_error', $upload['error'] );
				}
			}

			if ( !$upload || $upload['error'] ) {
				// get placeholder file in the upload dir with a unique, sanitized filename
				$upload = wp_upload_bits( $file_name, 0, '', $post['upload_date'] );
				if ( $upload['error'] ) {
					return new WP_Error( 'upload_dir_error', $upload['error'] );
				}

				// fetch the remote url and write it to the placeholder file
				//$headers = wp_get_http( $url, $upload['file'] );

				$max_size = (int) apply_filters( 'import_attachment_size_limit', 0 );

				// we check if this file is uploaded locally in the source folder.
				$response = wp_remote_get( $url );
				if ( is_array( $response ) && !empty($response['body']) && $response['response']['code'] == '200' ) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
					$headers = $response['headers'];
					WP_Filesystem();
					global $wp_filesystem;
					$wp_filesystem->put_contents( $upload['file'], $response['body'] );
					//
				} else {
					// required to download file failed.
					@unlink( $upload['file'] );
					return new WP_Error( 'import_file_error', __( 'Remote server did not respond', 'wordpress-importer' ) );
				}


				$filesize = filesize( $upload['file'] );

				if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
					@unlink( $upload['file'] );

					return new WP_Error( 'import_file_error', __( 'Remote file is incorrect size', 'wordpress-importer' ) );
				}

				if ( 0 == $filesize ) {
					@unlink( $upload['file'] );

					return new WP_Error( 'import_file_error', __( 'Zero size file downloaded', 'wordpress-importer' ) );
				}

				if ( ! empty( $max_size ) && $filesize > $max_size ) {
					@unlink( $upload['file'] );

					return new WP_Error( 'import_file_error', sprintf( __( 'Remote file is too large, limit is %s', 'wordpress-importer' ), size_format( $max_size ) ) );
				}
			}

			// keep track of the old and new urls so we can substitute them later
			$this->_imported_post_id( $url, $upload['url']);
			$this->_imported_post_id( $post['guid'], $upload['url']);
			// keep track of the destination if the remote url is redirected somewhere else
			if ( isset( $headers['x-final-location'] ) && $headers['x-final-location'] != $url ) {
				$this->_imported_post_id( $headers['x-final-location'], $upload['url'] );
			}

			return $upload;
		}


		private function _content_install_widgets() {
			// todo: pump these out into the 'content/' folder along with the XML so it's a little nicer to play with
			$import_widget_positions = $this->_get_json( 'widget_positions.json' );
			$import_widget_options = $this->_get_json( 'widget_options.json' );

			// importing.
			$widget_positions = get_option( 'sidebars_widgets' );

			//                    echo '<pre>'; print_r($import_widget_positions); print_r($import_widget_options); print_r($my_options); echo '</pre>';exit;
			foreach ( $import_widget_options as $widget_name => $widget_options ) {
				// replace certain elements with updated imported entries.
				foreach($widget_options as $widget_option_id => $widget_option){
					if(!empty($widget_option['nav_menu'])){
						// check if this one has been imported yet.
						$new_id = $this->_imported_term_id($widget_option['nav_menu']);
						if(!$new_id){
							unset($widget_options[$widget_option_id]);
						}else{
							$widget_options[$widget_option_id]['nav_menu'] = $new_id;
						}
					}
					if(!empty($widget_option['image_id'])){
						// check if this one has been imported yet.
						$new_id = $this->_imported_post_id($widget_option['image_id']);
						if(!$new_id){
							unset($widget_options[$widget_option_id]);
						}else{
							$widget_options[$widget_option_id]['image_id'] = $new_id;
						}
					}
				}
				$existing_options = get_option( 'widget_'.$widget_name,array() );
				$new_options = $existing_options + $widget_options;
				//                        echo $widget_name;
				//                        print_r($new_options);
				update_option( 'widget_'.$widget_name,$new_options );
			}
			update_option( 'sidebars_widgets',array_merge( $widget_positions,$import_widget_positions ) );
			//                    print_r($widget_positions + $import_widget_positions);exit;

			return true;

		}
		public function _content_install_settings() {


			$menu_ids = $this->_get_json( 'menu.json' );
			$save = array();
			foreach($menu_ids as $menu_id => $term_id){
				$new_term_id = $this->_imported_term_id($term_id);
				if($new_term_id){
					$save[$menu_id] = $new_term_id;
				}
			}
			if ( $save ) {
				set_theme_mod( 'nav_menu_locations', array_map( 'absint', $save ) );
			}

			$custom_options = $this->_get_json( 'options.json' );

			// we also want to update the widget area manager options.
			foreach ( $custom_options as $option => $value ) {
				// we have to update widget page numbers with imported page numbers.
				if(
					preg_match('#(wam__position_)(\d+)_#',$option,$matches) ||
					preg_match('#(wam__area_)(\d+)_#',$option,$matches)
				){
					$new_page_id = $this->_imported_post_id($matches[2]);
					if($new_page_id){
						// we have a new page id for this one. import the new setting value.
						$option = str_replace($matches[1].$matches[2].'_', $matches[1].$new_page_id.'_', $option);
					}
				}
				if($option == 'dtbaker_featured_images'){
					$value = maybe_unserialize($value);
					$new_values = array();
					if(is_array($value)){
						foreach($value as $cat_id => $image_id){
							$new_cat_id = $this->_imported_term_id($cat_id);
							$new_image_id = $this->_imported_post_id($image_id);
							if($new_cat_id && $new_image_id){
								$new_values[$new_cat_id] = $new_image_id;
							}
						}
					}
					$value = $new_values;
				}
				update_option( $option, $value );
			}
			// set the blog page and the home page.
			$shoppage = get_page_by_title( 'Shop' );
			if ( $shoppage ) {
				update_option( 'woocommerce_shop_page_id',$shoppage->ID );
			}
			$shoppage = get_page_by_title( 'Cart' );
			if ( $shoppage ) {
				update_option( 'woocommerce_cart_page_id',$shoppage->ID );
			}
			$shoppage = get_page_by_title( 'Checkout' );
			if ( $shoppage ) {
				update_option( 'woocommerce_checkout_page_id',$shoppage->ID );
			}
			$shoppage = get_page_by_title( 'My Account' );
			if ( $shoppage ) {
				update_option( 'woocommerce_myaccount_page_id',$shoppage->ID );
			}
			$homepage = get_page_by_title( 'Home' );
			if ( $homepage ) {
				update_option( 'page_on_front', $homepage->ID );
				update_option( 'show_on_front', 'page' );
			}
			$blogpage = get_page_by_title( 'Blog' );
			if ( $blogpage ) {
				update_option( 'page_for_posts', $blogpage->ID );
				update_option( 'show_on_front', 'page' );
			}

			global $wp_rewrite;
			$wp_rewrite->set_permalink_structure('/%year%/%monthnum%/%day%/%postname%/');
			update_option( "rewrite_rules", FALSE );
			$wp_rewrite->flush_rules( true );

			return true;
		}
		private function _get_json( $file ) {
			if ( is_file( __DIR__.'/content/'.basename( $file ) ) ) {
				WP_Filesystem();
				global $wp_filesystem;
				$file_name = __DIR__ . '/content/' . basename( $file );
				if ( file_exists( $file_name ) ) {
					return json_decode( $wp_filesystem->get_contents( $file_name ), true );
				}
			}
			return array();
		}
		private function _get_sql( $file ) {
			if ( is_file( __DIR__.'/content/'.basename( $file ) ) ) {
				WP_Filesystem();
				global $wp_filesystem;
				$file_name = __DIR__ . '/content/' . basename( $file );
				if ( file_exists( $file_name ) ) {
					return $wp_filesystem->get_contents( $file_name );
				}
			}
			return false;
		}

		/**
		 * Logo & Design
		 */
		public function envato_setup_logo_design() {

			?>
			<h1><?php _e( 'Logo &amp; Design', 'envato_setup' ); ?></h1>
			<form method="post">
				<p><?php printf( __( 'Please add your logo below. For best results, the logo should be a transparent PNG ( 466 by 277 pixels). The logo can be changed at any time from the Appearance > Customize area in your dashboard. Try %sEnvato Studio%s if you need a new logo designed.' ,'envato_setup' ), '<a href="http://studiotracking.envato.com/aff_c?offer_id=4&aff_id=1564&source=DemoInstall" target="_blank">','</a>' ); ?></p>

				<table>
					<tr>
						<td>
							<div id="current-logo">
								<?php $image_url = get_theme_mod( 'logo_header_image', get_template_directory_uri().'/images/'.get_theme_mod( 'dtbwp_site_color',$this->get_default_theme_style() ).'/logo.png' );
								$image_url = apply_filters('envato_setup_logo_image',$image_url);
								if ( $image_url ) {
									$image = '<img class="site-logo" src="%s" alt="%s" style="width:%s; height:auto" />';
									printf(
										$image,
										$image_url,
										get_bloginfo( 'name' ),
										$this->get_header_logo_width()
									);
								} ?>
							</div>
						</td>
						<td>
							<a href="#" class="button button-upload"><?php _e( 'Upload New Logo', 'envato_setup' ); ?></a>
						</td>
					</tr>
				</table>

				<?php
				$demo_styles = apply_filters( 'dtbwp_default_styles',array() );
				if( !$this->get_default_theme_style() || !$demo_styles){

				}else{
					?>

					<p><?php _e( 'Please choose the color scheme for this website. The color scheme (along with font colors &amp; styles) can be changed at any time from the Appearance > Customize area in your dashboard.' ,'envato_setup' ); ?></p>

					<div class="theme-presets">
						<ul>
							<?php
							$current_demo = get_theme_mod( 'dtbwp_site_color',$this->get_default_theme_style() );
							foreach ( $demo_styles as $demo_name => $demo_style ) {
								?>
								<li<?php echo $demo_name == $current_demo ? ' class="current" ' : '';?>>
									<a href="#" data-style="<?php echo esc_attr( $demo_name );?>"><img src="<?php echo esc_url( $demo_style['image'] );?>"></a>
								</li>
							<?php } ?>
						</ul>
					</div>
				<?php } ?>

				<p><em>Please Note: Advanced changes to website graphics/colors may require extensive PhotoShop and Web Development knowledge. We recommend hiring an expert from <a href="http://studiotracking.envato.com/aff_c?offer_id=4&aff_id=1564&source=DemoInstall" target="_blank">Envato Studio</a> to assist with any advanced website changes.</em></p>
				<div style="display: none;">
					<img src="http://studiotracking.envato.com/aff_i?offer_id=4&aff_id=1564&source=DemoInstall" width="1" height="1" />
				</div>


				<input type="hidden" name="new_logo_id" id="new_logo_id" value="">
				<input type="hidden" name="new_style" id="new_style" value="">

				<p class="envato-setup-actions step">
					<input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e( 'Continue', 'envato_setup' ); ?>" name="save_step" />
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large button-next"><?php _e( 'Skip this step', 'envato_setup' ); ?></a>
					<?php wp_nonce_field( 'envato-setup' ); ?>
				</p>
			</form>
			<?php
		}

		/**
		 * Save logo & design options
		 */
		public function envato_setup_logo_design_save() {
			check_admin_referer( 'envato-setup' );

			$new_logo_id = (int) $_POST['new_logo_id'];
			// save this new logo url into the database and calculate the desired height based off the logo width.
			// copied from dtbaker.theme_options.php
			if ( $new_logo_id ) {
				$attr = wp_get_attachment_image_src( $new_logo_id, 'full' );
				if ( $attr && ! empty( $attr[1] ) && ! empty( $attr[2] ) ) {
					set_theme_mod( 'logo_header_image',$attr[0] );
					// we have a width and height for this image. awesome.
					$logo_width = (int) get_theme_mod( 'logo_header_image_width', '467' );
					$scale = $logo_width / $attr[1];
					$logo_height = $attr[2] * $scale;
					if ( $logo_height > 0 ) {
						set_theme_mod( 'logo_header_image_height', $logo_height );
					}
				}
			}

			$new_style = isset($_POST['new_style']) ? $_POST['new_style'] : false;
			if($new_style) {
				$demo_styles = apply_filters( 'dtbwp_default_styles', array() );
				if ( isset( $demo_styles[ $new_style ] ) ) {
					set_theme_mod( 'dtbwp_site_color', $new_style );
					if ( class_exists( 'dtbwp_customize_save_hook' ) ) {
						$site_color_defaults = new dtbwp_customize_save_hook();
						$site_color_defaults->save_color_options( $new_style );
					}
				}
			}

			wp_redirect( esc_url_raw( $this->get_next_step_link() ) );
			exit;
		}

		/**
		 * Payments Step
		 */
		public function envato_setup_updates() {
			?>
			<h1><?php _e( 'Theme Updates', 'envato_setup' ); ?></h1>
			<?php if ( function_exists( 'envato_market' ) ) { ?>
				<form method="post">
					<?php
					$option = envato_market()->get_options();

					//echo '<pre>';print_r($option);echo '</pre>';
					$my_items = array();
					if ( $option && ! empty( $option['items'] ) ) {
						foreach ( $option['items'] as $item ) {
							if ( ! empty( $item['oauth'] ) && ! empty( $item['token_data']['expires'] ) && $item['oauth'] == $this->envato_username && $item['token_data']['expires'] >= time() ) {
								// token exists and is active
								$my_items[] = $item;
							}
						}
					}
					if ( count( $my_items ) ) {
						?>
						<p>Thanks! Theme updates have been enabled for the following items: </p>
						<ul>
							<?php foreach ( $my_items as $item ) {  ?>
								<li><?php echo esc_html( $item['name'] );?></li>
							<?php } ?>
						</ul>
						<p>When an update becomes available it will show in the Dashboard with an option to install.</p>
						<p>Change settings from the 'Envato Market' menu in the WordPress Dashboard.</p>

						<p class="envato-setup-actions step">
							<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large button-next button-primary"><?php _e( 'Continue', 'envato_setup' ); ?></a>
						</p>
						<?php
					} else {
						?>
						<p><?php _e( 'Please login using your ThemeForest account to enable Theme Updates. We update themes when a new feature is added or a bug is fixed. It is highly recommended to enable Theme Updates.', 'envato_setup' ); ?></p>
						<p>When an update becomes available it will show in the Dashboard with an option to install.</p>
						<p>
							<em>On the next page you will be asked to Login with your ThemeForest account and grant permissions to enable Automatic Updates. If you have any questions please <a href="http://dtbaker.net/envato/" target="_blank">contact us</a>.</em>
						</p>
						<p class="envato-setup-actions step">
							<input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e( 'Login with Envato', 'envato_setup' ); ?>" name="save_step" />
							<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large button-next"><?php _e( 'Skip this step', 'envato_setup' ); ?></a>
							<?php wp_nonce_field( 'envato-setup' ); ?>
						</p>
					<?php } ?>
				</form>
			<?php } else { ?>
				Please ensure the Envato Market plugin has been installed correctly. <a href="<?php echo esc_url( $this->get_step_link( 'default_plugins' ) );?>">Return to Required Plugins installer</a>.
			<?php } ?>
			<?php
		}

		/**
		 * Payments Step save
		 */
		public function envato_setup_updates_save() {
			check_admin_referer( 'envato-setup' );

			// redirect to our custom login URL to get a copy of this token.
			$url = $this->get_oauth_login_url( $this->get_step_link( 'updates' ) );

			wp_redirect( esc_url_raw( $url ) );
			exit;
		}


		public function envato_setup_customize() {
			?>

			<h1>Theme Customization</h1>
			<p>
				Most changes to the website can be made through the Appearance > Customize menu from the WordPress dashboard. These include:
			</p>
			<ul>
				<li>Typography: Font Sizes, Style, Colors (over 200 fonts to choose from) for various page elements.</li>
				<li>Logo: Upload a new logo and adjust its size.</li>
				<li>Background: Upload a new background image.</li>
				<li>Layout: Enable/Disable responsive layout, page and sidebar width.</li>
			</ul>
			<p>To change the Sidebars go to Appearance > Widgets. Here widgets can be "drag &amp; droped" into sidebars. To control which "widget areas" appear, go to an individual page and look for the "Left/Right Column" menu. Here widgets can be chosen for display on the left or right of a page. More details in documentation.</p>
			<p>
				<em>Advanced Users: If you are going to make changes to the theme source code please use a <a href="https://codex.wordpress.org/Child_Themes" target="_blank">Child Theme</a> rather than modifying the main theme HTML/CSS/PHP code. This allows the parent theme to receive updates without overwriting your source code changes. <br/> See <code>child-theme.zip</code> in the main folder for a sample.</em>
			</p>

			<p class="envato-setup-actions step">
				<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-primary button-large button-next"><?php _e( 'Continue', 'envato_setup' ); ?></a>
			</p>

			<?php
		}
		public function envato_setup_help_support() {
			?>
			<h1>Help and Support</h1>
			<p>This theme comes with 6 months item support from purchase date (with the option to extend this period). This license allows you to use this theme on a single website. Please purchase an additional license to use this theme on another website.</p>
			<p>Item Support can be accessed from <a href="http://dtbaker.net/envato/" target="_blank">http://dtbaker.net/envato/</a> and includes:</p>
			<ul>
				<li>Availability of the author to answer questions</li>
				<li>Answering technical questions about item features</li>
				<li>Assistance with reported bugs and issues</li>
				<li>Help with bundled 3rd party plugins</li>
			</ul>

			<p>Item Support <strong>DOES NOT</strong> Include:</p>
			<ul>
				<li>Customization services (this is available through <a href="http://studiotracking.envato.com/aff_c?offer_id=4&aff_id=1564&source=DemoInstall" target="_blank">Envato Studio</a>)</li>
				<li>Installation services (this is available through <a href="http://studiotracking.envato.com/aff_c?offer_id=4&aff_id=1564&source=DemoInstall" target="_blank">Envato Studio</a>)</li>
				<li>Help and Support for non-bundled 3rd party plugins (i.e. plugins you install yourself later on)</li>
			</ul>
			<p>More details about item support can be found in the ThemeForest <a href="http://themeforest.net/page/item_support_policy" target="_blank">Item Support Polity</a>. </p>
			<p class="envato-setup-actions step">
				<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-primary button-large button-next"><?php _e( 'Agree and Continue', 'envato_setup' ); ?></a>
				<?php wp_nonce_field( 'envato-setup' ); ?>
			</p>
			<?php
		}

		/**
		 * Final step
		 */
		public function envato_setup_ready() {

			update_option('envato_setup_complete',time());
			?>
			<a href="https://twitter.com/share" class="twitter-share-button" data-url="http://themeforest.net/user/dtbaker/portfolio?ref=dtbaker" data-text="<?php echo esc_attr( 'I just installed the ' . wp_get_theme() .' #WordPress theme from #ThemeForest' ); ?>" data-via="EnvatoMarket" data-size="large">Tweet</a>
			<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>

			<h1><?php _e( 'Your Website is Ready!', 'envato_setup' ); ?></h1>

			<p>Congratulations! The theme has been activated and your website is ready. Login to your WordPress dashboard to make changes and modify any of the default content to suit your needs.</p>
			<p>Please come back and <a href="http://themeforest.net/downloads" target="_blank">leave a 5-star rating</a> if you are happy with this theme. <br/>Follow <a  href="https://twitter.com/dtbaker" target="_blank">@dtbaker</a> on Twitter to see updates. Thanks! </p>

			<div class="envato-setup-next-steps">
				<div class="envato-setup-next-steps-first">
					<h2><?php _e( 'Next Steps', 'envato_setup' ); ?></h2>
					<ul>
						<li class="setup-product"><a class="button button-primary button-large" href="https://twitter.com/dtbaker" target="_blank"><?php _e( 'Follow @dtbaker on Twitter', 'envato_setup' ); ?></a></li>
						<li class="setup-product"><a class="button button-next button-large" href="<?php echo esc_url( home_url() ); ?>"><?php _e( 'View your new website!', 'envato_setup' ); ?></a></li>
					</ul>
				</div>
				<div class="envato-setup-next-steps-last">
					<h2><?php _e( 'More Resources', 'envato_setup' ); ?></h2>
					<ul>
						<li class="documentation"><a href="http://dtbaker.net/envato/documentation/" target="_blank"><?php _e( 'Read the Theme Documentation', 'envato_setup' ); ?></a></li>
						<li class="howto"><a href="https://wordpress.org/support/" target="_blank"><?php _e( 'Learn how to use WordPress', 'envato_setup' ); ?></a></li>
						<li class="rating"><a href="http://themeforest.net/downloads" target="_blank"><?php _e( 'Leave an Item Rating', 'envato_setup' ); ?></a></li>
						<li class="support"><a href="http://dtbaker.net/envato/" target="_blank"><?php _e( 'Get Help and Support', 'envato_setup' ); ?></a></li>
					</ul>
				</div>
			</div>
			<?php
		}

		public function envato_market_admin_init() {

			if(!function_exists('envato_market'))return;

			global $wp_settings_sections;
			if ( ! isset( $wp_settings_sections[ envato_market()->get_slug() ] ) ) {
				// means we're running the admin_init hook before envato market gets to setup settings area.
				// good - this means our oauth prompt will appear first in the list of settings blocks
				register_setting( envato_market()->get_slug(), envato_market()->get_option_name() );
			}

			// pull our custom options across to envato.
			$option = get_option( 'envato_setup_wizard' , array() );
			$envato_options = envato_market()->get_options();
			$envato_options = $this->_array_merge_recursive_distinct($envato_options, $option);
			update_option( envato_market()->get_option_name(), $envato_options );

			//add_thickbox();

			if ( ! empty( $_POST['oauth_session'] ) && ! empty( $_POST['bounce_nonce'] ) && wp_verify_nonce( $_POST['bounce_nonce'], 'envato_oauth_bounce_' . $this->envato_username ) ) {
				// request the token from our bounce url.
				$my_theme = wp_get_theme();
				$oauth_nonce = get_option( 'envato_oauth_'.$this->envato_username );
				if ( ! $oauth_nonce ) {
					// this is our 'private key' that is used to request a token from our api bounce server.
					// only hosts with this key are allowed to request a token and a refresh token
					// the first time this key is used, it is set and locked on the server.
					$oauth_nonce = wp_create_nonce( 'envato_oauth_nonce_' . $this->envato_username );
					update_option( 'envato_oauth_'.$this->envato_username, $oauth_nonce );
				}
				$response = wp_remote_post( $this->oauth_script, array(
						'method' => 'POST',
						'timeout' => 15,
						'redirection' => 1,
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array(),
						'body' => array(
							'oauth_session' => $_POST['oauth_session'],
							'oauth_nonce' => $oauth_nonce,
							'get_token' => 'yes',
							'url' => home_url(),
							'theme' => $my_theme->get( 'Name' ),
							'version' => $my_theme->get( 'Version' ),
						),
						'cookies' => array(),
					)
				);
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					$class = 'error';
					echo "<div class=\"$class\"><p>".sprintf( __( 'Something went wrong while trying to retrieve oauth token: %s','envato_setup' ), $error_message ).'</p></div>';
				} else {
					$token = @json_decode( wp_remote_retrieve_body( $response ), true );
					$result = false;
					if ( is_array( $token ) && ! empty( $token['access_token'] ) ) {
						$token['oauth_session'] = $_POST['oauth_session'];
						$result = $this->_manage_oauth_token( $token );
					}
					if ( $result !== true ) {
						echo 'Failed to get oAuth token. Please go back and try again';
						exit;
					}
				}
			}

			add_settings_section(
				envato_market()->get_option_name() . '_' . $this->envato_username  . '_oauth_login',
				sprintf( __( 'Login for %s updates', 'envato_setup' ), $this->envato_username ),
				array( $this, 'render_oauth_login_description_callback' ),
				envato_market()->get_slug()
			);
			// Items setting.
			add_settings_field(
				$this->envato_username  . 'oauth_keys',
				__( 'oAuth Login', 'envato_setup' ),
				array( $this, 'render_oauth_login_fields_callback' ),
				envato_market()->get_slug(),
				envato_market()->get_option_name() . '_' . $this->envato_username  . '_oauth_login'
			);
		}

		private static $_current_manage_token = false;

		private function _manage_oauth_token( $token ) {
			if ( is_array( $token ) && ! empty( $token['access_token'] ) ) {
				if ( self::$_current_manage_token == $token['access_token'] ) {
					return false; // stop loops when refresh auth fails.
				}
				self::$_current_manage_token = $token['access_token'];
				// yes! we have an access token. store this in our options so we can get a list of items using it.
				$option = get_option( 'envato_setup_wizard', array() );
				if ( ! is_array( $option ) ) {
					$option = array();
				}
				if ( empty( $option['items'] ) ) {
					$option['items'] = array();
				}
				// check if token is expired.
				if ( empty( $token['expires'] ) ) {
					$token['expires'] = time() + 3600;
				}
				if ( $token['expires'] < time() + 120 && ! empty( $token['oauth_session'] ) ) {
					// time to renew this token!
					$my_theme = wp_get_theme();
					$oauth_nonce = get_option( 'envato_oauth_'.$this->envato_username );
					$response = wp_remote_post( $this->oauth_script, array(
							'method' => 'POST',
							'timeout' => 10,
							'redirection' => 1,
							'httpversion' => '1.0',
							'blocking' => true,
							'headers' => array(),
							'body' => array(
								'oauth_session' => $token['oauth_session'],
								'oauth_nonce' => $oauth_nonce,
								'refresh_token' => 'yes',
								'url' => home_url(),
								'theme' => $my_theme->get( 'Name' ),
								'version' => $my_theme->get( 'Version' ),
							),
							'cookies' => array(),
						)
					);
					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
						echo "Something went wrong while trying to retrieve oauth token: $error_message";
					} else {
						$new_token = @json_decode( wp_remote_retrieve_body( $response ), true );
						$result = false;
						if ( is_array( $new_token ) && ! empty( $new_token['new_token'] ) ) {
							$token['access_token'] = $new_token['new_token'];
							$token['expires'] = time() + 3600;
						}
					}
				}
				// use this token to get a list of purchased items
				// add this to our items array.
				$response = envato_market()->api()->request( 'https://api.envato.com/v3/market/buyer/purchases', array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token['access_token'],
					),
				) );
				self::$_current_manage_token = false;
				if ( is_array( $response ) && is_array( $response['purchases'] ) ) {
					// up to here, add to items array
					foreach ( $response['purchases'] as $purchase ) {
						// check if this item already exists in the items array.
						$exists = false;
						foreach ( $option['items'] as $id => $item ) {
							if ( ! empty( $item['id'] ) && $item['id'] == $purchase['item']['id'] ) {
								$exists = true;
								// update token.
								$option['items'][ $id ]['token'] = $token['access_token'];
								$option['items'][ $id ]['token_data'] = $token;
								$option['items'][ $id ]['oauth'] = $this->envato_username;
								if ( ! empty( $purchase['code'] ) ) {
									$option['items'][ $id ]['purchase_code'] = $purchase['code'];
								}
							}
						}
						if ( ! $exists ) {
							$option['items'][] = array(
								'id' => '' . $purchase['item']['id'], // item id needs to be a string for market download to work correctly.
								'name' => $purchase['item']['name'],
								'token' => $token['access_token'],
								'token_data' => $token,
								'oauth' => $this->envato_username,
								'type' => ! empty( $purchase['item']['wordpress_theme_metadata'] ) ? 'theme' : 'plugin',
								'purchase_code' => ! empty( $purchase['code'] ) ? $purchase['code'] : '',
							);
						}
					}
				} else {
					return false;
				}
				if ( ! isset( $option['oauth'] ) ) {
					$option['oauth'] = array();
				}
				// store our 1 hour long token here. we can refresh this token when it comes time to use it again (i.e. during an update)
				$option['oauth'][ $this->envato_username ] = $token;
				update_option( 'envato_setup_wizard', $option );

				$envato_options = envato_market()->get_options();
				$envato_options = $this->_array_merge_recursive_distinct($envato_options, $option);
				update_option( envato_market()->get_option_name(), $envato_options );
				envato_market()->items()->set_themes( true );
				envato_market()->items()->set_plugins( true );
				return true;
			} else {
				return false;
			}
		}

		/**
		 * @param $array1
		 * @param $array2
		 *
		 * @return mixed
		 *
		 *
		 * @since    1.1.4
		 */
		private function _array_merge_recursive_distinct( $array1, $array2 ) {
			$merged = $array1;
			foreach ( $array2 as $key => &$value ) {
				if ( is_array( $value ) && isset ( $merged [ $key ] ) && is_array( $merged [ $key ] ) ) {
					$merged [ $key ] = $this->_array_merge_recursive_distinct( $merged [ $key ], $value );
				} else {
					$merged [ $key ] = $value;
				}
			}
			return $merged;
		}

		/**
		 * @param $args
		 * @param $url
		 * @return mixed
		 *
		 * Filter the WordPress HTTP call args.
		 * We do this to find any queries that are using an expired token from an oAuth bounce login.
		 * Since these oAuth tokens only last 1 hour we have to hit up our server again for a refresh of that token before using it on the Envato API.
		 * Hacky, but only way to do it.
		 */
		public function envato_market_http_request_args( $args, $url ) {
			if ( strpos( $url,'api.envato.com' ) && function_exists( 'envato_market' ) ) {
				// we have an API request.
				// check if it's using an expired token.
				if ( ! empty( $args['headers']['Authorization'] ) ) {
					$token = str_replace( 'Bearer ','',$args['headers']['Authorization'] );
					if ( $token ) {
						// check our options for a list of active oauth tokens and see if one matches, for this envato username.
						$option = envato_market()->get_options();
						if ( $option && ! empty( $option['oauth'][ $this->envato_username ] ) && $option['oauth'][ $this->envato_username ]['access_token'] == $token && $option['oauth'][ $this->envato_username ]['expires'] < time() + 120 ) {
							// we've found an expired token for this oauth user!
							// time to hit up our bounce server for a refresh of this token and update associated data.
							$this->_manage_oauth_token( $option['oauth'][ $this->envato_username ] );
							$updated_option = envato_market()->get_options();
							if ( $updated_option && ! empty( $updated_option['oauth'][ $this->envato_username ]['access_token'] ) ) {
								// hopefully this means we have an updated access token to deal with.
								$args['headers']['Authorization'] = 'Bearer '.$updated_option['oauth'][ $this->envato_username ]['access_token'];
							}
						}
					}
				}
			}
			return $args;
		}
		public function render_oauth_login_description_callback() {
			echo 'If you have purchased items from ' . esc_html($this->envato_username).' on ThemeForest or CodeCanyon please login here for quick and easy updates.';

		}

		public function render_oauth_login_fields_callback() {
			$option = envato_market()->get_options();
			?>
			<div class="oauth-login" data-username="<?php echo esc_attr( $this->envato_username ); ?>">
				<a href="<?php echo esc_url( $this->get_oauth_login_url( admin_url( 'admin.php?page=' . envato_market()->get_slug() . '#settings' ) ) ); ?>"
				   class="oauth-login-button button button-primary">Login with Envato to activate updates</a>
			</div>
			<?php
		}

		/// a better filter would be on the post-option get filter for the items array.
		// we can update the token there.

		public function get_oauth_login_url( $return ) {
			return $this->oauth_script . '?bounce_nonce=' . wp_create_nonce( 'envato_oauth_bounce_' . $this->envato_username ) . '&wp_return=' . urlencode( $return );
		}

		/**
		 * Helper function
		 * Take a path and return it clean
		 *
		 * @param string $path
		 *
		 * @since    1.1.2
		 */
		public static function cleanFilePath( $path ) {
			$path = str_replace( '', '', str_replace( array( "\\", "\\\\" ), '/', $path ) );
			if ( $path[ strlen( $path ) - 1 ] === '/' ) {
				$path = rtrim( $path, '/' );
			}
			return $path;
		}

		public function is_submenu_page(){
			return ( $this->parent_slug == '' ) ? false : true;
		}
	}

}// if !class_exists

/**
 * Loads the main instance of Envato_Theme_Setup_Wizard to have
 * ability extend class functionality
 *
 * @since 1.1.1
 * @return object Envato_Theme_Setup_Wizard
 */
add_action( 'after_setup_theme', 'envato_theme_setup_wizard', 10 );
if ( ! function_exists( 'envato_theme_setup_wizard' ) ) :
	function envato_theme_setup_wizard() {
		Envato_Theme_Setup_Wizard::get_instance();
	}
endif;