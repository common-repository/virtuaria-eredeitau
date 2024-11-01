<?php
/**
 * Handle payment events.
 *
 * @package Virtuaria/ERede/Classes/Notifications
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Events.
 */
class Virtuaria_ERede_Events {
	/**
	 * Log instance.
	 *
	 * @var WC_Logger
	 */
	protected $log;

	private const TAG = 'virtuaria-eredeitau';

	/**
	 * Constructor for the notification handler.
	 *
	 * Registers an action to handle IPN (Instant Payment Notification) requests
	 * from the eRede gateway.
	 */
	public function __construct() {
		$this->log = wc_get_logger();

		add_action(
			'woocommerce_api_wc_virtuaria_erede_gateway',
			array( $this, 'ipn_handler' )
		);
		add_action(
			'erede_process_update_order_status',
			array( $this, 'process_order_status' ),
			10,
			2
		);

		add_action(
			'wp_ajax_erede_fetch_payment_order',
			array( $this, 'fetch_payment_order' )
		);
		add_action(
			'wp_ajax_nopriv_erede_fetch_payment_order',
			array( $this, 'fetch_payment_order' )
		);
	}

	/**
	 * IPN handler.
	 */
	public function ipn_handler() {
		$body = $this->get_raw_data();

		$settings = Virtuaria_ERede_Settings::get_settings();
		if ( $this->is_debug_enable( $settings ) ) {
			$this->log->add( self::TAG, 'IPN request...', WC_Log_Levels::INFO );
		}
		$request = json_decode( $body, true );
		if ( $this->is_debug_enable( $settings ) ) {
			$this->log->add(
				self::TAG,
				'Request to order ' . $body,
				WC_Log_Levels::INFO
			);
		}

		if ( isset( $settings['pv'], $request['companyNumber'] )
			&& $settings['pv'] === $request['companyNumber'] ) {

			if ( $this->is_debug_enable( $settings ) ) {
				$this->log->add(
					self::TAG,
					'IPN request VALID',
					WC_Log_Levels::INFO
				);
			}

			if ( isset( $request['data']['id'] ) ) {
				$order = $this->get_order_by_transaction_id(
					sanitize_text_field( wp_unslash( $request['data']['id'] ) )
				);

				if ( $order && isset( $request['events'][0] ) ) {
					$status = sanitize_text_field( wp_unslash( $request['events'][0] ) );

					if ( $status ) {
						switch ( $status ) {
							case 'PV.UPDATE_TRANSACTION_PIX':
								$order->update_status(
									'processing',
									__( 'ERede: Pagamento confirmado e status do pedido alterado.', 'virtuaria-eredeitau' )
								);
								break;
							case 'PV.REFUND_PIX':
								$order->add_order_note(
									__( 'ERede: Notificação de reembolso recebida. Acesse o painel da conta rede para mais informações.', 'virtuaria-eredeitau' )
								);
								break;
						}

						if ( $this->is_debug_enable( $settings ) ) {
							$this->log->add(
								self::TAG,
								'Notification processed. Order status updated.',
								WC_Log_Levels::INFO
							);
						}
					}
				}
			}
			header( 'HTTP/1.1 200 OK' );
			return;
		}

		if ( $this->is_debug_enable( $settings ) ) {
			$this->log->add( self::TAG, 'REJECT IPN request...', WC_Log_Levels::INFO );
		}
		$error = __( 'Requisição ERede Não autorizada', 'virtuaria-eredeitau' );
		wp_die( esc_html( $error ), esc_html( $error ), array( 'response' => 401 ) );
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @param array $settings Array of Virtuaria ERede settings.
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	private function is_debug_enable( $settings ) {
		return isset( $settings['debug'] )
			&& 'yes' === $settings['debug'];
	}

	/**
	 * Returns a WC_Order instance from a given transaction ID.
	 *
	 * @param string $transaction_id The transaction ID to search for.
	 *
	 * @return WC_Order|false The order associated with the transaction ID, or false if none is found.
	 */
	private function get_order_by_transaction_id( $transaction_id ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_transaction_id' AND meta_value = %s",
				$transaction_id
			)
		);

		if ( $result ) {
			$order = wc_get_order( $result );
			if ( $order ) {
				return $order;
			}
		}
		return false;
	}

	/**
	 * Retrieve the raw request entity (body).
	 *
	 * @return string
	 */
	private function get_raw_data() {
		if ( function_exists( 'phpversion' ) && version_compare( phpversion(), '5.6', '>=' ) ) {
			return file_get_contents( 'php://input' );
		}
	}

	/**
	 * Process schedule order status.
	 *
	 * @param int    $order_id the order id.
	 * @param string $status the status scheduled.
	 */
	public function process_order_status( $order_id, $status ) {
		$order = wc_get_order( $order_id );

		if ( $order ) {
			if ( 'on-hold' === $status ) {
				if ( $order->has_status( 'pending' ) ) {
					$order->update_status(
						'on-hold',
						__( 'ERede: Aguardando confirmação de pagamento.', 'virtuaria-eredeitau' )
					);
				}
			} else {
				$order->update_status(
					'processing',
					__( 'ERede: Pagamento aprovado.', 'virtuaria-eredeitau' )
				);
			}
		}
	}

	/**
	 * Check order status.
	 */
	public function fetch_payment_order() {
		if ( isset( $_POST['order_id'] )
			&& isset( $_POST['payment_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['payment_nonce'] ) ), 'fecth_order_status' ) ) {
			$order = wc_get_order(
				sanitize_text_field(
					wp_unslash(
						$_POST['order_id']
					)
				)
			);

			if ( $order && 'processing' === $order->get_status() ) {
				echo 'success';
			}
		}
		wp_die();
	}
}

new Virtuaria_ERede_Events();
