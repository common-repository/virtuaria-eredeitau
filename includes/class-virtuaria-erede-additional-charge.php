<?php
/**
 * Handle Additional charge.
 *
 * @package virtuaria/ERede
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle Additional charge.
 */
class Virtuaria_ERede_Additional_Charge {
	use Virtuaria_ERede_Trait_Common_Functions;

	/**
	 * Definition api instance.
	 *
	 * @var WC_Virtuaria_ERede_API
	 */
	private $api;

	/**
	 * Initialize functions.
	 */
	public function __construct() {
		$settings  = Virtuaria_ERede_Settings::get_settings();
		$this->api = new Virtuaria_ERede_API(
			isset( $settings['pv'] )
				? $settings['pv']
				: '',
			isset( $settings['integration_key'] )
				? $settings['integration_key']
				: '',
			isset( $settings['environment'] )
				? $settings['environment']
				: 'sandbox',
			wc_get_logger()
		);

		add_action(
			'add_meta_boxes_' . $this->get_meta_boxes_screen(),
			array( $this, 'additional_charge_metabox' ),
		);
		add_action(
			'woocommerce_process_shop_order_meta',
			array( $this, 'do_additional_charge_erede' )
		);
	}

	/**
	 * Metabox to additional charge.
	 *
	 * @param WP_Post $post the post.
	 */
	public function additional_charge_metabox( $post ) {
		$order   = $post instanceof WP_Post
			? wc_get_order( $post->ID )
			: $post;
		$options = get_option( 'woocommerce_virt_erede_settings' );
		$credit  = get_user_meta(
			$order->get_customer_id(),
			'_virt_erede_credit_info_store_' . get_current_blog_id(),
			true
		);

		if ( ! $order
			|| 'processing' !== $order->get_status()
			|| 'virt_erede' !== $order->get_payment_method()
			|| 'yes' !== $options['enabled']
			|| ! isset( $credit['cardNumber'] ) ) {
			return;
		}

		add_meta_box(
			'erede-additional-charge',
			__( 'Cobrança Adicional', 'virtuaria-eredeitau' ),
			array( $this, 'display_additional_charge_content' ),
			$this->get_meta_boxes_screen(),
			'side',
			'high'
		);
	}

	/**
	 * Content to additional charge metabox.
	 *
	 * @param WP_Post $post the post.
	 */
	public function display_additional_charge_content( $post ) {
		?>
		<label for="additional-value">Informe o valor a ser cobrado (R$):</label>
		<input type="number" style="width:calc(100% - 36px)" name="erede_additional_value" id="additional-value" step="0.01" min="0.1"/>
		<button
			id="submit-additional-charge"
			style="padding: 3px 4px;vertical-align:middle;color:green;cursor:pointer;border-color: #0071a1;
				color: #0071a1;
				font-size: 16px;
				border-width: 1px;
				border-radius: 5px;">
			<span class="dashicons dashicons-money-alt"></span>
		</button>
		<label for="reason-charge" style="margin-top: 5px;">Motivo:</label>
		<input
			type="text"
			name="erede_credit_charge_reason"
			id="reason-charge"
			style="display:block;max-width:219px;"
		/>
		<?php
		wp_nonce_field( 'do_additional_charge_erede', 'erede_additional_charge_nonce' );
	}

	/**
	 * Do additional charge.
	 *
	 * @param int $order_id the order id.
	 */
	public function do_additional_charge_erede( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! in_array( $order->get_status(), array( 'on-hold', 'processing' ), true ) ) {
			return;
		}

		if ( isset( $_POST['erede_additional_value'] )
			&& isset( $_POST['erede_additional_charge_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['erede_additional_charge_nonce'] ) ), 'do_additional_charge_erede' )
			&& floatval( $_POST['erede_additional_value'] ) > 0 ) {
			$amount = number_format(
				sanitize_text_field( wp_unslash( $_POST['erede_additional_value'] ) ),
				2,
				'',
				''
			);

			$this->api->additional_charge(
				$order,
				$amount,
				isset( $_POST['erede_credit_charge_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['erede_credit_charge_reason'] ) ) : ''
			);
		}
	}
}

new Virtuaria_ERede_Additional_Charge();
