<?php
/**
 * Gateway class.
 *
 * @package Virtuaria/ERede/Classes/Gateway
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gateway.
 */
class Virtuaria_ERede_Gateway_Credit extends WC_Payment_Gateway {
	use Virtuaria_ERede_Trait_Common_Functions;

	/**
	 * Definition environment.
	 *
	 * @var string
	 */
	public $environment;

	/**
	 * Definition debug.
	 *
	 * @var string
	 */
	public $debug;

	/**
	 * Definition installments.
	 *
	 * @var string
	 */
	public $installments;

	/**
	 * Definition tax.
	 *
	 * @var string
	 */
	public $tax;

	/**
	 * Definition min installment.
	 *
	 * @var string
	 */
	public $min_installment;

	/**
	 * Definition fee from.
	 *
	 * @var string
	 */
	public $fee_from;

	/**
	 * Definition soft descriptor.
	 *
	 * @var string
	 */
	public $soft_descriptor;

	/**
	 * Definition process mode.
	 *
	 * @var string
	 */
	public $process_mode;

	/**
	 * Definition pv.
	 *
	 * @var string
	 */
	public $pv;

	/**
	 * Definition token.
	 *
	 * @var string
	 */
	public $token;

	/**
	 * Definition autorize.
	 *
	 * @var string
	 */
	public $autorize;

	/**
	 * Definition log instance.
	 *
	 * @var WC_Logger
	 */
	public $log;

	/**
	 * Definition api instance.
	 *
	 * @var WC_Virtuaria_ERede_API
	 */
	private $api;


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'virtuaria_erede_credit';
		$this->icon               = apply_filters(
			'woocommerce_erede_virt_icon',
			VIRTUARIA_EREDE_URL . '/public/images/erede.png'
		);
		$this->has_fields         = false;
		$this->method_title       = __( 'eRede Crédito', 'virtuaria-eredeitau' );
		$this->method_description = __( 'Pague com cartão de crédito.', 'virtuaria-eredeitau' );

		$this->supports = array( 'products', 'refunds' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$settings_global = Virtuaria_ERede_Settings::get_settings();

		// Define user set variables.
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->environment     = isset( $settings_global['environment'] )
			? $settings_global['environment']
			: 'sandbox';
		$this->debug           = isset( $settings_global['debug'] )
			? $settings_global['debug']
			: 'no';
		$this->installments    = $this->get_option( 'installments' );
		$this->tax             = $this->get_option( 'tax' );
		$this->min_installment = $this->get_option( 'min_installment' );
		$this->fee_from        = $this->get_option( 'fee_from' );
		$this->soft_descriptor = $this->get_option( 'soft_descriptor' );
		$this->process_mode    = isset( $settings_global['process_mode'] )
			? $settings_global['process_mode']
			: 'sync';
		$this->pv              = isset( $settings_global['pv'] )
			? $settings_global['pv']
			: '';
		$this->token           = isset( $settings_global['integration_key'] )
			? $settings_global['integration_key']
			: '';
		$this->autorize        = $this->get_option( 'autorize' );

		// Active logs.
		if ( 'yes' === $this->debug ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				$this->log = wc_get_logger();
			} else {
				$this->log = new WC_Logger();
			}
		}

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);

		// Set the API.
		$this->api = new Virtuaria_ERede_API(
			$this->pv,
			$this->token,
			$this->environment,
			'yes' === $this->debug
				? $this->log
				: null
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_checkout_scripts' ) );

		add_filter(
			'woocommerce_get_order_item_totals',
			array( $this, 'order_items_payment_details' ),
			10,
			2
		);

		if ( 'manual' === $this->autorize ) {
			add_action(
				'woocommerce_order_status_processing',
				array(
					$this,
					'process_capture',
				)
			);

			add_action(
				'add_meta_boxes_' . $this->get_meta_boxes_screen(),
				array(
					$this,
					'capture_meta_box',
				)
			);
			add_action(
				'virt_rede_capture_transaction',
				array(
					$this,
					'process_capture',
				)
			);
			add_action(
				'woocommerce_process_shop_order_meta',
				array(
					$this,
					'trigger_transaction_capture',
				)
			);
		}
	}

	/**
	 * Checkout scripts.
	 */
	public function checkout_scripts() {
		if ( is_checkout() && $this->is_available()
			&& ! get_query_var( 'order-received' ) ) {
			wp_enqueue_script(
				'erede-virt',
				VIRTUARIA_EREDE_URL . 'public/js/checkout.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_EREDE_DIR . 'public/js/checkout.js' ),
				true
			);

			wp_enqueue_style(
				'erede-virt',
				VIRTUARIA_EREDE_URL . 'public/css/checkout.css',
				'',
				filemtime( VIRTUARIA_EREDE_DIR . 'public/css/checkout.css' )
			);
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Habilitar', 'virtuaria-eredeitau' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilita o método de Pagamento Virtuaria eRede', 'virtuaria-eredeitau' ),
				'default' => 'yes',
			),
			'title'           => array(
				'title'       => __( 'Título', 'virtuaria-eredeitau' ),
				'type'        => 'text',
				'description' => __( 'Isto controla o título exibido ao usuário durante o checkout.', 'virtuaria-eredeitau' ),
				'desc_tip'    => true,
				'default'     => __( 'eRede crédito', 'virtuaria-eredeitau' ),
			),
			'description'     => array(
				'title'       => __( 'Descrição', 'virtuaria-eredeitau' ),
				'type'        => 'textarea',
				'description' => __( 'Controla a descrição exibida ao usuário durante o checkout.', 'virtuaria-eredeitau' ),
				'default'     => __( 'Pague com em até 12x.', 'virtuaria-eredeitau' ),
			),
			'autorize'        => array(
				'title'       => __( 'Autorização e captura', 'virtuaria-eredeitau' ),
				'type'        => 'select',
				'description' => __( 'Define a forma de captura da transação. No modo de captura automático, todo o processo é realizado no checkout. Quando "Apenas autorize", o checkout reserva o valor da compra no cartão do cliente, porém a cobrança só será efetivada ao altenar, manualmente, o status do pedido para processando ou pelo botão especifico de captura na página do pedido.', 'virtuaria-eredeitau' ),
				'options'     => array(
					'automatic' => __( 'Autorize e capture automaticamente', 'virtuaria-eredeitau' ),
					'manual'    => __( 'Apenas autorize', 'virtuaria-eredeitau' ),
				),
				'default'     => 'automatic',
			),
			'installments'    => array(
				'title'       => __( 'Número de parcelas', 'virtuaria-eredeitau' ),
				'type'        => 'select',
				'description' => __( 'Selecione o número máximo de parcelas disponíveis para seus clientes.', 'virtuaria-eredeitau' ),
				'options'     => array(
					'1'  => '1x',
					'2'  => '2x',
					'3'  => '3x',
					'4'  => '4x',
					'5'  => '5x',
					'6'  => '6x',
					'7'  => '7x',
					'8'  => '8x',
					'9'  => '9x',
					'10' => '10x',
					'11' => '11x',
					'12' => '12x',
				),
				'default'     => 12,
			),
			'min_installment' => array(
				'title'             => __( 'Valor mínimo da parcela (R$)', 'virtuaria-eredeitau' ),
				'type'              => 'number',
				'description'       => __( 'Define o valor mínimo que uma parcela pode receber.', 'virtuaria-eredeitau' ),
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 'any',
				),
			),
			'tax'             => array(
				'title'             => __( 'Taxa de juros (%)', 'virtuaria-eredeitau' ),
				'type'              => 'number',
				'description'       => __( 'Define o percentual de juros aplicado ao parcelamento.', 'virtuaria-eredeitau' ),
				'custom_attributes' => array(
					'min'  => 0,
					'step' => '0.01',
				),
			),
			'fee_from'        => array(
				'title'       => __( 'Parcelamento com juros ', 'virtuaria-eredeitau' ),
				'type'        => 'select',
				'description' => __( 'Define a partir de qual parcela os juros serão aplicados.', 'virtuaria-eredeitau' ),
				'options'     => array(
					'2'  => '2x',
					'3'  => '3x',
					'4'  => '4x',
					'5'  => '5x',
					'6'  => '6x',
					'7'  => '7x',
					'8'  => '8x',
					'9'  => '9x',
					'10' => '10x',
					'11' => '11x',
					'12' => '12x',
				),
			),
			'soft_descriptor' => array(
				'title'       => __( 'Nome na fatura', 'virtuaria-eredeitau' ),
				'type'        => 'text',
				'description' => 'Texto exibido na fatura do cartão para identificar a loja.',
			),
			/**
			'save_card_info'  => array(
				'title'       => __( 'Salvar dados de pagamento?', 'virtuaria-eredeitau' ),
				'type'        => 'select',
				'description' => __( 'Define se será possível memorizar as informações de pagamento do cliente para compras futuras', 'virtuaria-eredeitau' ),
				'desc_tip'    => true,
				'default'     => 'do_not_store',
				'class'       => 'wc-enhanced-select',
				'options'     => array(
					'do_not_store'     => __( 'Não memorizar (padrão)', 'virtuaria-eredeitau' ),
					'customer_defines' => __( 'O cliente decide sobre o armazenamento', 'virtuaria-eredeitau' ),
					'always_store'     => __( 'Sempre memorizar', 'virtuaria-eredeitau' ),
				),
			),
			*/
		);

		$this->form_fields['tecvirtuaria'] = array(
			'title' => __( 'Tecnologia Virtuaria', 'virtuaria-eredeitau' ),
			'type'  => 'title',
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( isset( $_POST['rede_charge_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rede_charge_nonce'] ) ), 'rede_new_charge' ) ) {
			$order = wc_get_order( $order_id );

			$paid = $this->api->new_charge_credit(
				$order,
				$_POST,
				array(
					'fee_from'        => $this->fee_from,
					'capture'         => 'automatic' === $this->autorize,
					'soft_descriptor' => $this->soft_descriptor,
					'tax'             => $this->tax,
				)
			);

			$order = wc_get_order( $order->get_id() );

			if ( ! isset( $paid['error'] ) ) {
				$charge_amount = $order->get_meta( '_charge_total' );
				if ( $this->tax && ( ( $charge_amount / 100 ) - $order->get_total() ) > 0 ) {
					$fee = new WC_Order_Item_Fee();
					$fee->set_name( __( 'Parcelamento erede', 'virtuaria-eredeitau' ) );
					$fee->set_total( ( $charge_amount / 100 ) - $order->get_total() );

					$order->add_item( $fee );
					$order->calculate_totals();
					$order->save();
				}
				if ( $paid && 'automatic' === $this->autorize ) {
					if ( 'async' !== $this->process_mode ) {
						$order->update_status(
							'processing',
							__( 'eRede: Pagamento aprovado.', 'virtuaria-eredeitau' )
						);
					} else {
						$args = array( $order_id, 'processing' );
						if ( ! wp_next_scheduled( 'erede_process_update_order_status', $args ) ) {
							wp_schedule_single_event(
								strtotime( 'now' ) + 60,
								'erede_process_update_order_status',
								$args
							);
						}
					}
				} elseif ( 'async' !== $this->process_mode ) {
					$order->update_status(
						'on-hold',
						__( 'eRede: Aguardando confirmação de pagamento.', 'virtuaria-eredeitau' )
					);
				} else {
					$args = array( $order_id, 'on-hold' );
					if ( ! wp_next_scheduled( 'erede_process_update_order_status', $args ) ) {
						wp_schedule_single_event(
							strtotime( 'now' ) + 60,
							'erede_process_update_order_status',
							$args
						);
					}
				}

				wc_reduce_stock_levels( $order_id );
				// Remove cart.
				WC()->cart->empty_cart();

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			} else {
				wc_add_notice( '<b>eRede:</b> ' . $paid['error'], 'error' );

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} else {
			wc_add_notice( 'Não foi possível processar a sua compra. Por favor, tente novamente mais tarde.', 'error' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Process refund order.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount Refund amount.
	 * @param  string $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( $amount && 'processing' === $order->get_status() && $order->get_transaction_id() ) {
			if ( $this->api->refund_order( $order, $amount ) ) {
				$order->add_order_note( 'ERede: Reembolso de R$' . $amount . ' bem sucedido.', 0, true );
				return true;
			}
		}

		$order->add_order_note( 'ERede: Não foi possível reembolsar R$' . $amount . '. Verifique o status da transação e o valor a ser reembolsado e tente novamente.', 0, true );

		return false;
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		$comments = $this->get_option( 'comments' );
		if ( $comments ) {
			echo '<span class="erede-info">' . wp_kses_post( $comments ) . '</span>';
		}

		$cart_total = $this->get_order_total();

		$combo_installments = array();
		foreach ( range( 1, $this->installments ) as $installment ) {
			if ( $this->fee_from > $installment ) {
				$combo_installments[] = $cart_total;
				continue;
			}

			$combo_installments[] = $this->get_installment_value(
				$cart_total,
				$installment,
				$this->tax
			);
		}

		wc_get_template(
			'transparent-checkout.php',
			array(
				'cart_total'      => $cart_total,
				'flag'            => plugins_url( 'assets/images/brazilian-flag.png', plugin_dir_path( __FILE__ ) ),
				'installments'    => $combo_installments,
				'has_tax'         => floatval( $this->tax ) > 0,
				'min_installment' => floatval( $this->min_installment ),
				'fee_from'        => $this->fee_from,
				'pv'              => $this->pv,
			),
			'woocommerce/erede/',
			Virtuaria_ERede::get_templates_path()
		);
	}

	/**
	 * Capture autorized transaction.
	 *
	 * @param int $order_id the order id.
	 */
	public function process_capture( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() || $this->id !== $order->get_payment_method() ) {
			if ( $order
				&& $this->id === $order->get_payment_method()
				&& ! $order->get_transaction_id() ) {
				$order->add_order_note(
					__( 'eREDE Crédito: Falha na captura do pedido, tente novamente usando o botão "Capturar Transação".', 'virtuaria-eredeitau' ),
					0,
					true
				);
			}
			return false;
		}

		if ( empty( $order->get_meta( '_wc_virt_rede_captured' ) ) ) {
			$tid    = $order->get_transaction_id();
			$amount = number_format( $order->get_total(), 2, '', '' );

			if ( $this->api->capture( $tid, $amount ) ) {
				$order->update_meta_data( '_wc_virt_rede_captured', true );

				$order->add_order_note(
					sprintf(
						/* Translators: %s is the amount. */
						__( 'eREDE Crédito: Cobrança de R$ %s efetivada.', 'virtuaria-eredeitau' ),
						number_format( $amount / 100, 2, ',', '.' )
					)
				);
				$order->save();
			} else {
				$order->add_order_note(
					__( 'eREDE Crédito: Falha na captura do pedido.', 'virtuaria-eredeitau' )
				);
			}
		}
	}

	/**
	 * Capture box.
	 *
	 * @param WP_Post $post the post.
	 */
	public function capture_meta_box( $post ) {
		$order = $post instanceof WP_Post
			? wc_get_order( $post->ID )
			: $post;
		if ( $order
			&& $this->id === $order->get_payment_method()
			&& empty( $order->get_meta( '_wc_virt_rede_captured' ) ) ) {
			add_meta_box(
				'rede-capture-transaction',
				'Rede: Captura Manual',
				array( $this, 'trigger_action_capture_transaction' ),
				$this->get_meta_boxes_screen(),
				'side'
			);
		}
	}

	/**
	 * Action button to hook to capture transaction.
	 *
	 * @param WP_Post $post the post.
	 */
	public function trigger_action_capture_transaction( $post ) {
		$order_id = $post instanceof WP_Post
			? $post->ID
			: $post->get_id();
		?>
		<button class="button capture_transaction button-primary" data-id="<?php echo esc_attr( $order_id ); ?>">Capturar Pedido</button>
		<input type="hidden" name="virt_rede_transaction_id" id="virt_rede_transaction_id" />
		<?php
		wp_nonce_field( 'do_capture_transaction_erede', 'erede_nonce' );
	}

	/**
	 *  Trigger hook to capture transaction.
	 *
	 * @param int $order_id the order id.
	 */
	public function trigger_transaction_capture( $order_id ) {
		if ( isset( $_POST['virt_rede_transaction_id'], $_POST['erede_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['erede_nonce'] ) ), 'do_capture_transaction_erede' )
			&& intval( $_POST['virt_rede_transaction_id'] ) === $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order && ! $order->get_transaction_id() ) {
				$notes = wc_get_order_notes( array( 'order_id' => $order_id ) );
				if ( $notes ) {
					foreach ( $notes as $note ) {
						if ( isset( $note->content )
							&& false !== strpos( $note->content, 'eREDE: Pré-autorização bem sucedida' ) ) {
							$matches = array();
							preg_match(
								'/\d{10,}/',
								$note->content,
								$matches
							);

							if ( isset( $matches[0] ) ) {
								$order->set_transaction_id( $matches[0] );
								$_POST['_transaction_id'] = wc_clean( wp_unslash( $matches[0] ) );
								$order->save();
							}
							break;
						}
					}
				}
			}
			do_action( 'virt_rede_capture_transaction', $order_id );
		}
	}

	/**
	 * Display installments in thank you page and mail.
	 *
	 * @param array    $items the itens.
	 * @param wc_order $order the order.
	 */
	public function order_items_payment_details( $items, $order ) {
		if ( $this->id === $order->get_payment_method() ) {
			$installments                  = $order->get_meta( '_virt_erede_installments' );
			$items['payment_installments'] = array(
				'label' => __( 'Pagamento:', 'virtuaria-eredeitau' ),
				'value' => sprintf(
					/* Translators: %s is the number of installments. */
					_n(
						'Parcelado em %s vez (à vista)',
						'Parcelado em %s vezes',
						intval( $installments ),
						'virtuaria-eredeitau'
					),
					$installments
				),
			);
		}
		return $items;
	}

	/**
	 * Checks if the payment gateway is available for use.
	 *
	 * This function checks if the payment gateway is enabled and if the cart contains an order total that is greater than zero and less than the maximum amount.
	 * If the serial option is enabled and the gateway is available and the user is authenticated or is a premium user, the function returns true.
	 * Otherwise, it returns false.
	 *
	 * @return bool True if the payment gateway is available for use, false otherwise.
	 */
	public function is_available() {
		$is_available = ( 'yes' === $this->enabled );

		if ( WC()->cart
			&& 0 < $this->get_order_total()
			&& 0 < $this->max_amount
			&& $this->max_amount < $this->get_order_total() ) {
			$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Enqueue the script for capturing eRede transactions on the admin checkout page.
	 *
	 * This function checks if the necessary conditions are met to enqueue the script for capturing eRede transactions on the admin checkout page.
	 * If the conditions are met, it enqueues the script 'virtuaria-erede-capture' for handling the capture process.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function admin_checkout_scripts( $hook ) {
		if ( isset( $_GET['post'] )
			&& 'automatic' !== $this->autorize
			&& 'shop_order' === get_post_type( sanitize_text_field( wp_unslash( $_GET['post'] ) ) ) ) {
			wp_enqueue_script(
				'virtuaria-erede-capture',
				VIRTUARIA_EREDE_URL . 'admin/js/capture.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_EREDE_DIR . 'admin/js/capture.js' ),
				true
			);
		}
	}
}
