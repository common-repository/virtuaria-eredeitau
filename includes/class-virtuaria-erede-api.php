<?php
/**
 * Handle API ERede.
 *
 * @package virtuaria.
 * @since 1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Definition.
 */
class Virtuaria_ERede_API {
	use Virtuaria_ERede_Trait_Common_Functions;

	/**
	 * PV.
	 *
	 * @var String
	 */
	private $pv;

	/**
	 * Integration key.
	 *
	 * @var String
	 */
	private $integration_key;

	/**
	 * Endpoint to API.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * Debug tag.
	 *
	 * @var string
	 */
	private $tag;

	/**
	 * Debug log instance.
	 *
	 * @var WC_Logger|null
	 */
	private $log;

	/**
	 * Timetou to comunication with API.
	 *
	 * @var int
	 */
	private const TIMEOUT = 25;

	/**
	 * Initialize class.
	 *
	 * @param String         $pv              PV.
	 * @param String         $integration_key Integration key.
	 * @param String         $environment     Environment.
	 * @param WC_Logger|null $log Log.
	 */
	public function __construct( $pv, $integration_key, $environment = 'sandbox', $log = null ) {

		if ( 'sandbox' === $environment ) {
			$this->endpoint = 'https://sandbox-erede.useredecloud.com.br/v1/transactions/';
		} else {
			$this->endpoint = 'https://api.userede.com.br/erede/v1/transactions/';
		}

		$this->tag             = 'virtuaria-eredeitau';
		$this->log             = $log;
		$this->pv              = $pv;
		$this->integration_key = $integration_key;
	}

	/**
	 * Return basic auth header value.
	 *
	 * @return string
	 */
	private function get_basic_auth() {
		return 'Basic ' . base64_encode( $this->pv . ':' . $this->integration_key );
	}

	/**
	 * Create new charge.
	 *
	 * @param wc_order $order       the order.
	 * @param array    $posted      the data to charge.
	 * @param array    $credit_data the credit data.
	 */
	public function new_charge_credit( $order, $posted, $credit_data ) {
		if ( isset( $credit_data['fee_from'] )
			&& $credit_data['fee_from'] <= intval( $posted['erede_installments'] ) ) {
			$total = $this->get_installment_value(
				$order->get_total(),
				intval( $posted['erede_installments'] ),
				isset( $credit_data['tax'] )
					? $credit_data['tax']
					: 0
			);
		} else {
			$total = $order->get_total();
		}
		$total = number_format( $total, 2, '', '' );

		$customer_id      = get_current_user_id();
		$blog_id          = get_current_blog_id();
		$credit_card_info = get_user_meta(
			$customer_id,
			'_virt_erede_credit_info_store_' . $blog_id,
			true
		);
		$card_loaded      = false;
		$settings         = Virtuaria_ERede_Settings::get_settings();
		$crypt_key        = base64_encode( $this->pv . ':' . $customer_id );

		if ( is_user_logged_in()
			&& isset( $credit_card_info['cardNumber'] )
			&& ! isset( $posted['erede_use_other_card'] )
			&& 'do_not_store' !== $settings['save_card_info'] ) {
			foreach ( $credit_card_info as $index => $value ) {
				$credit_card_info[ $index ] = Virtuaria_Erede_Encryptation::decrypt(
					$value,
					$crypt_key,
					true
				);
			}

			if ( isset( $credit_card_info['integrity'] )
				&& intval( $credit_card_info['integrity'] ) === $customer_id ) {
				$card_loaded = true;
			}
		}

		if ( ! $card_loaded ) {
			$expiration = preg_replace(
				'/\D/',
				'',
				sanitize_text_field( wp_unslash( $posted['erede_card_validate'] ) )
			);

			$credit_card_info = array();

			$credit_card_info['cardholderName']  = sanitize_text_field(
				wp_unslash( $posted['erede_card_holder_name'] )
			);
			$credit_card_info['cardNumber']      = preg_replace(
				'/\D/',
				'',
				sanitize_text_field( wp_unslash( $posted['erede_card_number'] ) )
			);
			$credit_card_info['expirationMonth'] = substr( $expiration, 0, 2 );
			$credit_card_info['expirationYear']  = substr( $expiration, 2 );
			$credit_card_info['securityCode']    = preg_replace(
				'/\D/',
				'',
				sanitize_text_field( wp_unslash( $posted['erede_card_cvc'] ) )
			);
		}

		$data = array(
			'headers' => array(
				'Authorization' => $this->get_basic_auth(),
				'Content-Type'  => 'application/json',
			),
			'body'    => array(
				'reference'              => strval( $order->get_id() + time() ),
				'capture'                => isset( $credit_data['capture'] )
					? $credit_data['capture']
					: true,
				'kind'                   => 'credit',
				'amount'                 => $total,
				'installments'           => intval( $posted['erede_installments'] ),
				'cardholderName'         => $credit_card_info['cardholderName'],
				'cardNumber'             => $credit_card_info['cardNumber'],
				'expirationMonth'        => $credit_card_info['expirationMonth'],
				'expirationYear'         => $credit_card_info['expirationYear'],
				'securityCode'           => $credit_card_info['securityCode'],
				'softDescriptor'         => isset( $credit_data['soft_descriptor'] )
					? $credit_data['soft_descriptor']
					: '',
				'distributorAffiliation' => $this->pv,
			),
			'timeout' => self::TIMEOUT,
		);

		if ( $this->log ) {
			$to_log = $data;

			$to_log['body']['cardNumber']   = preg_replace(
				'/\d/',
				'x',
				$to_log['body']['cardNumber']
			);
			$to_log['body']['securityCode'] = preg_replace(
				'/\d/',
				'x',
				$to_log['body']['securityCode']
			);
			$this->log->add(
				$this->tag,
				'Enviando novo pedido: ' . wp_json_encode( $to_log ),
				WC_Log_Levels::DEBUG
			);
		}

		$data['body'] = wp_json_encode( $data['body'] );

		$request = wp_remote_post(
			$this->endpoint,
			$data
		);

		if ( is_wp_error( $request ) ) {
			if ( $this->log ) {
				$this->log->add(
					$this->tag,
					'Erro ao criar pedido: ' . $request->get_error_message(),
					WC_Log_Levels::ERROR
				);
			}
			return array( 'error' => $request->get_error_message() );
		}

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				'Resposta do servidor ao tentar criar novo pedido: ' . wp_json_encode( $request ),
				WC_Log_Levels::DEBUG
			);
		}

		$response  = json_decode( wp_remote_retrieve_body( $request ), true );
		$resp_code = intval( wp_remote_retrieve_response_code( $request ) );
		if ( 200 !== $resp_code ) {
			$error = $this->get_translated_error(
				$response['returnCode'],
				$response['returnMessage']
			);
			$order->add_order_note( $error );
			return array(
				'error' => $error !== $response['returnMessage'] ? $error : 'Falha ao processar seu pedido. Por favor, Tente novamente.',
			);
		}

		$card_holder = $credit_card_info['cardholderName'];
		if ( is_user_logged_in()
			&& ! is_admin()
			&& isset( $posted['erede_save_hash_card'] )
			&& ( ! $card_loaded || isset( $posted['erede_use_other_card'] ) ) ) {
			$credit_card_info['card_bin']   = $response['cardBin'];
			$credit_card_info['card_last']  = $response['last4'];
			$credit_card_info['card_brand'] = $this->get_card_brand( $credit_card_info['cardNumber'] );
			$credit_card_info['integrity']  = $customer_id;

			foreach ( $credit_card_info as $index => $value ) {
				$credit_card_info[ $index ] = Virtuaria_Erede_Encryptation::encrypt(
					$value,
					$crypt_key,
					true
				);
			}

			update_user_meta(
				$customer_id,
				'_virt_erede_credit_info_store_' . $blog_id,
				$credit_card_info
			);
		}

		$order->set_transaction_id( $response['tid'] );
		$order->add_meta_data( '_charge_total', $response['amount'] );
		$order->add_meta_data( '_virt_erede_installments', $posted['erede_installments'] );
		$order->add_order_note(
			sprintf(
				'eREDE: %1$s<br>
				%2$s<br>
				Total: R$ %3$s<br>
				Titular: %4$s <br>
				ID da Transação: %5$s',
				isset( $credit_data['capture'] )
					&& $credit_data['capture']
					? 'Pagamento aprovado'
					: 'Pré-autorização bem sucedida',
				1 === intval( $posted['erede_installments'] ) ? 'Pagamento à vista' : 'Parcelado em ' . intval( $posted['erede_installments'] ) . ' vezes',
				number_format( $total / 100, 2, ',', '.' ),
				$card_holder,
				$response['tid']
			)
		);
		$order->save();
		return true;
	}

	/**
	 * Create new charge.
	 *
	 * @param wc_order $order    the order.
	 * @param array    $posted   the data to charge.
	 * @param array    $pix_info the data to charge.
	 */
	public function new_charge_pix( $order, $posted, $pix_info ) {

		$expiration = new DateTime(
			wp_date(
				'Y-m-d H:i:s',
				isset( $pix_info['pix_validate'] )
					? strtotime( '+' . $pix_info['pix_validate'] . ' seconds' )
					: strtotime( '+30 minutes' ),
			),
			new DateTimeZone( 'America/Sao_Paulo' )
		);

		$total_discounted = 0;
		if ( isset( $pix_info['pix_discount'] )
			&& floatval( $pix_info['pix_discount'] ) > 0
			&& $this->discount_enable( $order, $pix_info ) ) {
			$total_discounted = $this->get_total_after_discount(
				$order,
				$order->get_total(),
				$pix_info
			);
		}

		$total = 0 !== $total_discounted ? $total_discounted : $order->get_total();

		$data = array(
			'headers' => array(
				'Authorization' => $this->get_basic_auth(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'kind'      => 'Pix',
					'reference' => strval( $order->get_id() ),
					'qrCode'    => array(
						'dateTimeExpiration' => $expiration->format( 'Y-m-d\TH:i:s' ),
					),
					'amount'    => $total * 100, // Convert to int.
				)
			),
			'timeout' => self::TIMEOUT,
		);

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				'Enviando pedido PIX: ' . wp_json_encode( $data ),
				WC_Log_Levels::INFO
			);
		}

		$request = wp_remote_post(
			$this->endpoint,
			$data
		);

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				'Resposta do servidor ao tentar criar PIX: ' . wp_json_encode( $request ),
				200 !== wp_remote_retrieve_response_code( $request )
					? WC_Log_Levels::ERROR
					: WC_Log_Levels::INFO
			);
		}

		if ( is_wp_error( $request ) ) {
			return array(
				'error' => $request->get_error_message(),
			);
		}

		$request = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( isset( $request['qrCodeResponse'] )
			&& $request['qrCodeResponse'] ) {
			$order->update_meta_data(
				'_erede_order_id',
				$request['tid']
			);

			$order->update_meta_data(
				'_erede_qrcode',
				$request['qrCodeResponse']['qrCodeData']
			);

			$qr_code_url = $this->upload_qr_code(
				$request['qrCodeResponse']['qrCodeImage'],
				$order->get_id()
			);

			$order->update_meta_data(
				'_erede_qrcode_png',
				$qr_code_url
			);

			$this->add_qrcode_in_note(
				$order,
				$request['tid'],
				$request['qrCodeResponse']['qrCodeData'],
				$qr_code_url
			);

			$order->set_transaction_id( $request['tid'] );

			if ( isset( $pix_info['pix_discount'] )
				&& floatval( $pix_info['pix_discount'] ) > 0
				&& $this->discount_enable( $order, $pix_info ) ) {
				$this->apply_discount_fee( $order, $pix_info );
			}

			$order->save();
		} else {
			return array(
				'error' => __( 'Falha ao criar pagamento Pix', 'virtuaria-eredeitau' ),
			);
		}

		return false;
	}

	/**
	 * Upload QR code image and generate link to print.
	 *
	 * @param string $image    the image data.
	 * @param string $filename file name.
	 */
	private function upload_qr_code( $image, $filename ) {
		$uploads_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'virtuaria_erede';
		wp_mkdir_p( $uploads_dir );

		$bin = base64_decode( $image, true );

		$file_path = $uploads_dir . '/' . $filename . '.png';

		if ( ! function_exists( 'WP_Filesystem ' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();
		global $wp_filesystem;

		$wp_filesystem->put_contents( $file_path, $bin );

		return wp_upload_dir()['baseurl'] . '/virtuaria_erede/' . $filename . '.png';
	}

	/**
	 * Add QR Code in order note.
	 *
	 * @param wc_order $order   the order.
	 * @param string   $transaction_id the transaction id.
	 * @param string   $qr_code        the qr code.
	 * @param string   $qr_code_url    the qr code url.
	 */
	private function add_qrcode_in_note( $order, $transaction_id, $qr_code, $qr_code_url ) {
		if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
			remove_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
			$order->add_order_note(
				wp_sprintf(
					/* translators: %1$s ID da transação, %2$s Código Copia e Cola, %3$s Link para imprimir.*/
					__( 'Erede: ID da transação: %1$s<br /><br/>Erede Pix Copia e Cola: <div class="pix">%2$s</div><a href="#" id="copy-qr" class="button button-primary">Copiar</a><br /><br/><a href="%3$s" target="_blank">Clique aqui</a> para imprimir.', 'virtuaria-eredeitau' ),
					$transaction_id,
					$qr_code,
					$qr_code_url
				)
			);
			add_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
		} else {
			$order->add_order_note(
				wp_sprintf(
					/* translators: %1$s ID da transação, %2$s Código Copia e Cola, %3$s Link para imprimir.*/
					__( 'Erede: ID da transação: %1$s<br /><br/>Erede Pix Copia e Cola: <div class="pix">%2$s</div><a href="#" id="copy-qr" class="button button-primary">Copiar</a><br /><br/><a href="%3$s" target="_blank">Clique aqui</a> para imprimir.', 'virtuaria-eredeitau' ),
					$transaction_id,
					$qr_code,
					$qr_code_url
				)
			);
		}
	}

	/**
	 * Do refund order.
	 *
	 * @param wc_order $order  the order id.
	 * @param float    $amount the refund amount.
	 */
	public function refund_order( $order, $amount ) {
		$data = array(
			'headers' => array(
				'Authorization' => $this->get_basic_auth(),
				'Content-Type'  => 'application/json',
			),
			'body'    => array(
				'amount' => preg_replace( '/\D/', '', $amount ),
				'urls'   => array(
					array(
						'kind' => 'callback',
						'url'  => home_url( 'wc-api/WC_Virtuaria_eRede_Gateway' ),
					),
				),
			),
			'timeout' => self::TIMEOUT,
		);

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				'Reembolso para o pedido - ' . $order->get_id() . wp_json_encode( $data ),
				WC_Log_Levels::DEBUG
			);
		}

		$data['body'] = wp_json_encode( $data['body'] );

		$request = wp_remote_post(
			$this->endpoint . $order->get_transaction_id() . '/refunds',
			$data
		);

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				'Resposta do reembolso: ' . wp_json_encode( $request ),
				WC_Log_Levels::DEBUG
			);
		}

		$resp_code = wp_remote_retrieve_response_code( $request );
		$response  = json_decode( $request['body'], true );
		if ( in_array( $resp_code, array( 200, 201, 202 ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Do additional charge to credit card.
	 *
	 * @param wc_order $order  the order.
	 * @param int      $amount the quantity from additional charge.
	 * @param string   $reason the reson from charge.
	 * @param array    $credit_data the credit data.
	 */
	public function additional_charge( $order, $amount, $reason, $credit_data = array() ) {
		if ( $amount <= 0 ) {
			if ( $this->log ) {
				$order->add_order_note(
					'eRede: Cobrança Adicional com valor inválido.',
					0,
					true
				);
				$this->log->add(
					$this->tag,
					'Valor inválido ou pedido não encontrado para cobrança adicional.',
					WC_Log_Levels::ERROR
				);
			}
			return;
		}

		$customer_id      = $order->get_customer_id();
		$blog_id          = get_current_blog_id();
		$credit_card_info = get_user_meta(
			$customer_id,
			'_virt_erede_credit_info_store_' . $blog_id,
			true
		);

		if ( ! $credit_card_info || ! isset( $credit_card_info['cardNumber'] ) ) {
			if ( $this->log ) {
				$order->add_order_note(
					'eRede: Cobrança Adicional, método de pagamento do cliente ausente.',
					0,
					true
				);
				$this->log->add(
					$this->tag,
					'Cobrança Adicional: método de pagamento do cliente ausente',
					WC_Log_Levels::ERROR
				);
			}
			return;
		}

		$card_loaded = false;
		$crypt_key   = base64_encode( $this->pv . ':' . $customer_id );

		if ( isset( $credit_card_info['cardNumber'] ) ) {
			foreach ( $credit_card_info as $index => $value ) {
				$credit_card_info[ $index ] = Virtuaria_Erede_Encryptation::decrypt(
					$value,
					$crypt_key,
					true
				);
			}

			if ( isset( $credit_card_info['integrity'] )
				&& intval( $credit_card_info['integrity'] ) === $customer_id ) {
				$card_loaded = true;
			}
		}

		if ( ! $card_loaded ) {
			if ( $this->log ) {
				$order->add_order_note(
					'eRede: Cobrança Adicional, método de pagamento do cliente ausente.',
					0,
					true
				);
				$this->log->add(
					$this->tag,
					'Cobrança Adicional: método de pagamento do cliente ausente',
					WC_Log_Levels::ERROR
				);
			}
			return;
		}

		$data = array(
			'headers' => array(
				'Authorization' => $this->get_basic_auth(),
				'Content-Type'  => 'application/json',
			),
			'body'    => array(
				'reference'              => strval( $order->get_id() + time() ),
				'capture'                => true,
				'kind'                   => 'credit',
				'amount'                 => $amount,
				'installments'           => 1,
				'cardholderName'         => $credit_card_info['cardholderName'],
				'cardNumber'             => $credit_card_info['cardNumber'],
				'expirationMonth'        => $credit_card_info['expirationMonth'],
				'expirationYear'         => $credit_card_info['expirationYear'],
				'securityCode'           => $credit_card_info['securityCode'],
				'softDescriptor'         => isset( $credit_data['soft_descriptor'] )
					? $credit_data['soft_descriptor']
					: '',
				'distributorAffiliation' => $this->pv,
			),
			'timeout' => self::TIMEOUT,
		);

		if ( $this->log ) {
			$to_log = $data;

			$to_log['body']['cardNumber']   = preg_replace(
				'/\d/',
				'x',
				$to_log['body']['cardNumber']
			);
			$to_log['body']['securityCode'] = preg_replace(
				'/\d/',
				'x',
				$to_log['body']['securityCode']
			);
			$this->log->add(
				$this->tag,
				'Cobrança adicional: ' . wp_json_encode( $to_log ),
				WC_Log_Levels::DEBUG
			);
		}

		$data['body'] = wp_json_encode( $data['body'] );

		$request = wp_remote_post(
			$this->endpoint,
			$data
		);

		if ( is_wp_error( $request ) ) {
			if ( $this->log ) {
				$this->log->add(
					$this->tag,
					'Erro ao criar cobrança adicional: ' . $request->get_error_message(),
					WC_Log_Levels::ERROR
				);
			}
			return array( 'error' => $request->get_error_message() );
		}

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				'Resposta do servidor ao tentar criar cobrança adicional: ' . wp_json_encode( $request ),
				WC_Log_Levels::DEBUG
			);
		}

		$response  = json_decode( wp_remote_retrieve_body( $request ), true );
		$resp_code = intval( wp_remote_retrieve_response_code( $request ) );
		if ( 200 !== $resp_code ) {
			$error = $this->get_translated_error(
				$response['returnCode'],
				$response['returnMessage']
			);
			$order->add_order_note( $error );
			return;
		}

		$order->add_order_note(
			sprintf(
				'eREDE: cobrança adicional efetivada<br>
				%1$s
				Total: R$ %2$s<br>
				ID da Transação: %3$s',
				$reason ? 'Motivo: ' . $reason . '<br>' : '',
				number_format( $amount / 100, 2, ',', '.' ),
				$response['tid']
			)
		);
	}

	/**
	 * Capture transaction.
	 *
	 * @param int $transaction_id the transaction id.
	 * @param int $amount         amount.
	 */
	public function capture( $transaction_id, $amount ) {
		$data = array(
			'headers' => array(
				'Content-type'  => 'application/json',
				'Authorization' => $this->get_basic_auth(),
			),
			'body'    => array(
				'amount' => $amount,
			),
			'method'  => 'PUT',
			'timeout' => self::TIMEOUT,
		);

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				'Capturando transação ' . $transaction_id . ':' . wp_json_encode( $data ),
				WC_Log_Levels::DEBUG
			);
		}

		$data['body'] = wp_json_encode( $data['body'] );

		$request = wp_remote_request(
			$this->endpoint . $transaction_id,
			$data
		);

		if ( $this->log ) {
			$this->log->add(
				$this->tag,
				"Respotas da captura da transação $transaction_id:" . wp_json_encode( $request ),
				WC_Log_Levels::DEBUG
			);
		}

		if ( 200 === wp_remote_retrieve_response_code( $request ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get translated message error.
	 *
	 * @param int    $code    the error code.
	 * @param string $default english error message.
	 */
	private function get_translated_error( $code, $default ) {
		$errors = array(
			1   => __( 'O campo Validade do cartão está inválido.', 'virtuaria-eredeitau' ),
			2   => __( 'O campo Validade do cartão está inválido', 'virtuaria-eredeitau' ),
			3   => __( 'O campo Validade do cartão está inválido.', 'virtuaria-eredeitau' ),
			15  => __( 'Código de Segurança do cartão inválido.', 'virtuaria-eredeitau' ),
			16  => __( 'Código de Segurança do cartão inválido.', 'virtuaria-eredeitau' ),
			17  => __( 'Identificação do vendedor(PV) inválida.', 'virtuaria-eredeitau' ),
			24  => __( 'Identificação do vendedor(PV) inválida.', 'virtuaria-eredeitau' ),
			25  => __( 'Identificação do vendedor(PV) inválida.', 'virtuaria-eredeitau' ),
			26  => __( 'Identificação do vendedor(PV) inválida.', 'virtuaria-eredeitau' ),
			31  => __( 'Nome na fatura inválido.', 'virtuaria-eredeitau' ),
			32  => __( 'Nome na fatura inválido.', 'virtuaria-eredeitau' ),
			33  => __( 'O campo Validade do cartão está inválido.', 'virtuaria-eredeitau' ),
			35  => __( 'O campo Validade do cartão está inválido.', 'virtuaria-eredeitau' ),
			36  => __( 'O campo Número do cartão está inválido.', 'virtuaria-eredeitau' ),
			37  => __( 'O campo Número do cartão está inválido.', 'virtuaria-eredeitau' ),
			38  => __( 'O campo Número do cartão está inválido.', 'virtuaria-eredeitau' ),
			51  => __( 'Produto ou serviço desabilitado para este vendedor.', 'virtuaria-eredeitau' ),
			53  => __( 'Transação não permitida.', 'virtuaria-eredeitau' ),
			55  => __( 'Campo títular do cartão inválido.', 'virtuaria-eredeitau' ),
			58  => __( 'Transação não autorizada.', 'virtuaria-eredeitau' ),
			59  => __( 'Campo títular do cartão inválido.', 'virtuaria-eredeitau' ),
			63  => __( 'Nome na fatura inválido.', 'virtuaria-eredeitau' ),
			64  => __( 'Transação não processada.', 'virtuaria-eredeitau' ),
			65  => __( 'Token inválido.', 'virtuaria-eredeitau' ),
			69  => __( 'Transação não permitada para este produto.', 'virtuaria-eredeitau' ),
			83  => __( 'Transação não autorizada.', 'virtuaria-eredeitau' ),
			86  => __( 'Cartão expirado.', 'virtuaria-eredeitau' ),
			158 => __( 'Captura não permitida para esta transação.', 'virtuaria-eredeitau' ),
			171 => __( 'Operação não permitida para esta transação.', 'virtuaria-eredeitau' ),
			173 => __( 'Autorização expirada.', 'virtuaria-eredeitau' ),
			899 => __( 'Transação malsucedida.', 'virtuaria-eredeitau' ),
		);

		if ( isset( $errors[ $code ] ) ) {
			return $errors[ $code ];
		}
		return $default;
	}

	/**
	 * Get credit card brand.
	 *
	 * @param string $number the card number.
	 *
	 * @return string
	 */
	private function get_card_brand( $number ) {
		$number = preg_replace( '/\D/', '', $number );
		$brand  = '';

		// https://gist.github.com/arlm/ceb14a05efd076b4fae5 .
		$supported_brands = array(
			'VISA'       => '/^4\d{12}(\d{3})?$/',
			'MASTERCARD' => '/^(5[1-5]\d{4}|677189)\d{10}$/',
			'DINERS'     => '/^3(0[0-5]|[68]\d)\d{11}$/',
			'discover'   => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
			'elo'        => '/^((((636368)|(438935)|(504175)|(451416)|(636297))\d{0,10})|((5067)|(4576)|(4011))\d{0,12})$/',
			'AMEX'       => '/^3[47]\d{13}$/',
			'jcb'        => '/^(?:2131|1800|35\d{3})\d{11}$/',
			'AURA'       => '/^(5078\d{2})(\d{2})(\d{11})$/',
			'hipercard'  => '/^(606282\d{10}(\d{3})?)|(3841\d{15})$/',
			'maestro'    => '/^(?:5[0678]\d\d|6304|6390|67\d\d)\d{8,15}$/',
			'sorocred'   => '/^(627892|636414)\d{10}$/',
			'cabal'      => '/^(60420[1-9]|6042[1-9][0-9]|6043[0-9]{2}|604400)\d{10}$/',
			'credsystem' => '/^(628028)\d{10}$/',
			'banescard'  => '/^(603182)\d{10}$/',
			'credz'      => '/^(636760)\d{10}$/',
			'hiper'      => '/^(637095)\d{10}$/',
		);

		foreach ( $supported_brands as $key => $value ) {
			if ( preg_match( $value, $number ) ) {
				$brand = $key;
				break;
			}
		}

		return $brand;
	}

	/**
	 * Apply discount fee to the order.
	 *
	 * @param wc_order $order  The order object.
	 * @param array    $pix_info pix info.
	 */
	private function apply_discount_fee( $order, $pix_info ) {
		$fee = new WC_Order_Item_Fee();
		$fee->set_name(
			__(
				'Desconto do Pix',
				'virtuaria-eredeitau'
			),
		);

		$discountable_total = $order->get_total() - $order->get_shipping_total();
		$discount_reduce    = 0;

		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item['product_id'] );
			if ( $product && apply_filters( 'virtuaria_erede_disable_discount', false, $product, $pix_info ) ) {
				$discount_reduce += $item->get_total();
			}
		}

		$percentual = ( floatval(
			isset( $pix_info['pix_discount'] )
				? $pix_info['pix_discount']
				: 0
		) / 100 );

		$discountable_total -= $discount_reduce;
		if ( $discountable_total > 0 && $percentual > 0 ) {
			$fee->set_total( - $discountable_total * $percentual );

			$order->add_item( $fee );
			$order->calculate_totals();
		}
	}

	/**
	 * A function to calculate the total after applying a discount.
	 *
	 * @param wc_order $order    the order object.
	 * @param int      $total    the total amount before discount.
	 * @param array    $pix_info the method of discount application.
	 * @return float the total after discount applied
	 */
	private function get_total_after_discount( $order, $total, $pix_info ) {
		$discount  = $total / 100;
		$discount -= $order->get_shipping_total();

		$discount_reduce = 0;

		foreach ( $order->get_items() as $item ) {
			$product = wc_get_product( $item['product_id'] );
			if ( $product && apply_filters( 'virtuaria_erede_disable_discount', false, $product, $method ) ) {
				$discount_reduce += $item->get_total();
			}
		}

		$percentual = ( floatval(
			isset( $pix_info['pix_discount'] )
				? $pix_info['pix_discount']
				: 0
		) / 100 );

		$discount -= $discount_reduce;
		$total    /= 100;
		$total    -= $discount * $percentual;
		$total     = number_format( $total, 2, '', '' );

		return $total;
	}

	/**
	 * Check if discount is enable.
	 *
	 * @param wc_order $order    the order.
	 * @param array    $pix_info the method of discount application.
	 */
	private function discount_enable( $order, $pix_info ) {
		$allow_discount = (
			( ! isset( $pix_info['pix_discount_coupon'] )
			|| ! $pix_info['pix_discount_coupon'] )
			|| count( $order->get_coupon_codes() ) === 0 );
		return ! apply_filters(
			'virtuaria_erede_disable_discount_by_cart',
			false,
			WC()->cart
		) && $allow_discount;
	}
}
