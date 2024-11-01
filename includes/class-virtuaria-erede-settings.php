<?php
/**
 * Handle Settings.
 *
 * @package Virtuaria/ERede/Classes/Settings
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 */
class Virtuaria_ERede_Settings {
	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Initialize the class.
	 *
	 * Adds the menu page for eRede settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->settings = self::get_settings();
		add_action( 'admin_menu', array( $this, 'add_menu_erede' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_setup_styles' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_action( 'in_admin_footer', array( $this, 'display_review_info' ) );
	}

	/**
	 * Add submenu erede.
	 */
	public function add_menu_erede() {
		add_menu_page(
			__( 'Virtuaria eRede', 'virtuaria-eredeitau' ),
			__( 'Virtuaria eRede', 'virtuaria-eredeitau' ),
			'remove_users',
			'virtuaria-erede',
			array( $this, 'menu_content' ),
			VIRTUARIA_EREDE_URL . 'admin/images/virtuaria.png'
		);

		add_submenu_page(
			'virtuaria-erede',
			__( 'Integração', 'virtuaria-eredeitau' ),
			__( 'Integração', 'virtuaria-eredeitau' ),
			'remove_users',
			'virtuaria-erede'
		);

		add_submenu_page(
			'virtuaria-erede',
			__( 'Pix', 'virtuaria-eredeitau' ),
			__( 'Pix', 'virtuaria-eredeitau' ),
			'remove_users',
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virtuaria_erede_pix' )
		);

		add_submenu_page(
			'virtuaria-erede',
			__( 'Crédito', 'virtuaria-eredeitau' ),
			__( 'Crédito', 'virtuaria-eredeitau' ),
			'remove_users',
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=virtuaria_erede_credit' )
		);

		// add_submenu_page(
		// 	'virtuaria-erede',
		// 	__( 'Premium', 'virtuaria-eredeitau' ),
		// 	__( 'Premium', 'virtuaria-eredeitau' ),
		// 	'remove_users',
		// 	'virtuaria-erede-premium',
		// 	array( $this, 'premium_menu_content' )
		// );
	}

	/**
	 * Outputs the HTML content for the erede menu.
	 *
	 * @since 1.0.0
	 */
	public function menu_content() {
		$options = $this->settings;
		require_once VIRTUARIA_EREDE_DIR . 'templates/integration-settings.php';
	}

	/**
	 * Outputs the HTML content for the erede premium menu.
	 *
	 * @since 1.0.0
	 */
	public function premium_menu_content() {
		$options = $this->settings;
		require_once VIRTUARIA_EREDE_DIR . 'templates/premium-settings.php';
	}

	/**
	 * Get a setting value by key.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = array();
		$default  = array(
			'enviroment' => 'production',
			'debug'      => 'yes',
		);

		$settings = get_option( 'virtuaria_erede_settings', $default );

		$settings['authenticated'] = get_transient( 'virtuaria_erede_authenticated' );
		$settings['domain']        = str_replace(
			array( 'http://', 'https://' ),
			'',
			get_option( 'siteurl' )
		);

		return $settings;
	}

	/**
	 * Enqueue admin styles for plugin settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page.
	 */
	public function admin_setup_styles( $hook ) {
		if ( in_array(
			$hook,
			array(
				'toplevel_page_virtuaria-erede',
				'virtuaria-erede_page_virtuaria-erede-premium',
			),
			true
		) ) {
			wp_enqueue_style(
				'virtuaria-erede-admin',
				VIRTUARIA_EREDE_URL . 'admin/css/setup.css',
				array(),
				filemtime( VIRTUARIA_EREDE_DIR . 'admin/css/setup.css' )
			);
		}

		if ( isset( $_GET['post'] ) ) {
			$order = wc_get_order( sanitize_text_field( wp_unslash( $_GET['post'] ) ) );
		} elseif ( isset( $_GET['id'] ) ) {
			$order = wc_get_order( sanitize_text_field( wp_unslash( $_GET['id'] ) ) );
		}

		if ( isset( $order ) && $order && 'virtuaria_erede_pix' === $order->get_payment_method() ) {
			wp_enqueue_script(
				'copy-qr',
				VIRTUARIA_EREDE_URL . 'admin/js/copy-code.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_EREDE_DIR . 'admin/js/copy-code.js' ),
				true
			);

			wp_enqueue_style(
				'copy-qr',
				VIRTUARIA_EREDE_URL . 'admin/css/pix-code.css',
				array(),
				filemtime( VIRTUARIA_EREDE_DIR . 'admin/css/pix-code.css' )
			);
		}

		if ( isset( $_GET['section'] ) && 'virtuaria_erede_pix' === $_GET['section'] ) {
			wp_enqueue_style(
				'pix-setup',
				VIRTUARIA_EREDE_URL . 'admin/css/pix-setup.css',
				array(),
				filemtime( VIRTUARIA_EREDE_DIR . 'admin/css/pix-setup.css' )
			);
		}
	}

	/**
	 * Saves the plugin settings.
	 *
	 * Verifies the nonce and sanitizes the input values before saving them
	 * to the database.
	 *
	 * @since 1.0.0
	 */
	public function save_settings() {
		if ( isset( $_GET['page'], $_POST['erede_nonce'] )
			&& in_array( $_GET['page'], array( 'virtuaria-erede', 'virtuaria-erede-premium' ), true )
			&& wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['erede_nonce'] ) ),
				'update-erede-settings'
			)
		) {
			if ( isset( $_POST['woocommerce_virt_erede_environment'] ) ) {
				$this->settings['environment'] = sanitize_text_field(
					wp_unslash(
						$_POST['woocommerce_virt_erede_environment']
					)
				);
			}

			if ( isset( $_POST['woocommerce_virt_erede_pv'] ) ) {
				$this->settings['pv'] = sanitize_text_field(
					wp_unslash(
						$_POST['woocommerce_virt_erede_pv']
					)
				);
			}

			if ( isset( $_POST['woocommerce_virt_erede_integration_key'] ) ) {
				$this->settings['integration_key'] = sanitize_text_field(
					wp_unslash(
						$_POST['woocommerce_virt_erede_integration_key']
					)
				);
			}

			if ( isset( $_POST['woocommerce_virt_erede_process_mode'] ) ) {
				$this->settings['process_mode'] = sanitize_text_field(
					wp_unslash(
						$_POST['woocommerce_virt_erede_process_mode']
					)
				);
			}

			if ( isset( $_POST['woocommerce_virt_correios_debug'] ) ) {
				$this->settings['debug'] = 'yes';
			} else {
				unset( $this->settings['debug'] );
			}

			if ( isset( $_POST['woocommerce_virt_erede_serial'] ) ) {
				$this->settings['serial'] = sanitize_text_field(
					wp_unslash(
						$_POST['woocommerce_virt_erede_serial']
					)
				);
			}

			// Virtuaria_ERede::get_instance()->is_premium();

			update_option( 'virtuaria_erede_settings', $this->settings );
		}
	}

	/**
	 * Review info.
	 */
	public function display_review_info() {
		global $hook_suffix;

		$methods = array(
			'virtuaria_erede_credit',
			'virtuaria_erede_pix',
		);

		$pages = array(
			'toplevel_page_virtuaria-erede',
			'virtuaria-erede_page_virtuaria-erede-premium',
		);

		if ( in_array( $hook_suffix, $pages, true )
			|| ( 'woocommerce_page_wc-settings' === $hook_suffix
			&& isset( $_GET['section'] )
			&& in_array( $_GET['section'], $methods, true ) ) ) {
			echo '<div id="footer-virtuaria">';
			echo '<h4 class="stars">Avalie nosso trabalho ⭐</h4>';
			echo '<p class="review-us">Apoie o nosso trabalho. Se gostou do plugin, deixe uma avaliação positiva clicando <a href="https://wordpress.org/support/plugin/virtuaria-erede/reviews?rate=5#new-post " target="_blank">aqui</a>. Desde já, nossos agradecimentos.</p>';
			echo '<h4 class="stars">Tecnologia Virtuaria ✨</h4>';
			echo '<p class="disclaimer">Desenvolvimento, implantação e manutenção de e-commerces e marketplaces para atacado e varejo. Soluções personalizadas para cada cliente. <a target="_blank" href="https://virtuaria.com.br">Saiba mais</a>.</p>';
			echo '</div>';
		}
	}
}


new Virtuaria_ERede_Settings();
