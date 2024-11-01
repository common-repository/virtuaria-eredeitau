<?php
/**
 * Plugin Name: Virtuaria eRede - Pix e Crédito
 * Plugin URI: https://virtuaria.com.br/politica-de-privacidade-para-plugins-erede
 * Description: Adiciona o método de pagamento eRede a sua loja virtual.
 * Author: Virtuaria
 * Author URI: https://virtuaria.com.br/
 * Version: 1.0.0
 * License: GPLv2 or later
 *
 * @package virtuaria
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Virtuaria_ERede' ) ) :
	define( 'VIRTUARIA_EREDE_DIR', plugin_dir_path( __FILE__ ) );
	define( 'VIRTUARIA_EREDE_URL', plugin_dir_url( __FILE__ ) );
	/**
	 * Class definition.
	 */
	class Virtuaria_ERede {
		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Singleton constructor.
		 *
		 * @throws Exception Corrupted plugin.
		 */
		private function __construct() {
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				$this->load_dependecys();
				add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
				// add_action(
				// 	'woocommerce_before_checkout_billing_form',
				// 	array( $this, 'authenticate_premium' )
				// );
			} else {
				add_action( 'admin_notices', array( $this, 'missing_dependency' ) );
			}
		}

		/**
		 * Display warning about missing dependency.
		 */
		public function missing_dependency() {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_attr_e( 'Virtuaria eRede need Woocommerce 4.0+ to work!', 'virtuaria-eredeitau' ); ?></p>
			</div>
			<?php
		}

		/**
		 * Load file dependencys.
		 */
		private function load_dependecys() {
			require_once 'includes/traits/trait-commom-functions.php';
			require_once 'includes/class-virtuaria-erede-api.php';
			require_once 'includes/class-virtuaria-erede-settings.php';
			require_once 'includes/class-virtuaria-erede-gateway-credit.php';
			require_once 'includes/class-virtuaria-erede-gateway-pix.php';
			require_once 'includes/class-virtuaria-erede-encryptation.php';
			require_once 'includes/class-virtuaria-erede-events.php';

			// $plugin_data = get_plugin_data( __FILE__ );
			// require_once 'includes/integrity-check.php';
		}

		/**
		 * Add Payment method.
		 *
		 * @param array $methods the current methods.
		 */
		public function add_gateway( $methods ) {
			$methods[] = 'Virtuaria_ERede_Gateway_Credit';
			$methods[] = 'Virtuaria_ERede_Gateway_Pix';
			return $methods;
		}

		/**
		 * Get templates path.
		 *
		 * @return string
		 */
		public static function get_templates_path() {
			return VIRTUARIA_EREDE_DIR . 'templates/';
		}

		/**
		 * Load the plugin text domain for translation.
		 */
		public function load_plugin_textdomain() {
			load_plugin_textdomain( 'virtuaria-eredeitau', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Check if the premium version is active for the user based on certain criteria.
		 *
		 * @return bool Returns true if the user is premium, false otherwise.
		 */
		public function is_premium() {
			$settings = get_option( 'woocommerce_virt_erede_settings' );

			if ( isset( $settings['serial'] ) ) {
				$plugin = get_plugin_data( __FILE__ );

				$domain = str_replace(
					array( 'http://', 'https://' ),
					'',
					home_url()
				);

				$response = wp_remote_get(
					'https://premium.virtuaria.com.br/wp-json/v1/auth/premium/plugins?request_id=' . time(),
					array(
						'headers' => array(
							'domain'         => $domain,
							'serial'         => isset( $settings['serial'] ) ? $settings['serial'] : '',
							'version'        => isset( $plugin['Version'] ) ? $plugin['Version'] : '',
							'mode'           => 'Premium',
							'module'         => 'virtuaria-erede',
							'Content-Length' => 0,
						),
						'timeout' => 15,
					)
				);

				if ( ! is_wp_error( $response )
					&& 200 === wp_remote_retrieve_response_code( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( isset( $body['authenticated'], $body['auth_date'] )
						&& $body['authenticated']
						&& $body['auth_date'] ) {
						set_transient(
							'virtuaria_erede_authenticated',
							true,
							MONTH_IN_SECONDS * 1
						);
						if ( isset( $settings['global'] ) ) {
							restore_current_blog();
						}
						return true;
					}
				} else {
					delete_transient( 'virtuaria_erede_authenticated' );
				}
			}

			wc_get_logger()->add( 'virt_erede', 'The plugin is not authenticated.', WC_Log_Levels::INFO );
			return false;
		}

		/**
		 * Authenticate premium user.
		 */
		public function authenticate_premium() {
			if ( ! get_transient( 'virtuaria_erede_checkout_authenticated' ) ) {
				self::get_instance()->is_premium();
				set_transient(
					'virtuaria_erede_checkout_authenticated',
					true,
					HOUR_IN_SECONDS * 6
				);
			}
		}
	}

	add_action( 'plugins_loaded', array( 'Virtuaria_ERede', 'get_instance' ) );

endif;
