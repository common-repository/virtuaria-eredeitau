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
class Virtuaria_ERede_Gateway_Pix extends WC_Payment_Gateway {
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
	 * Percentage from pix discount.
	 *
	 * @var float
	 */
	public $pix_discount;

	/**
	 * Message to confirm payment from pix.
	 *
	 * @var string
	 */
	public $pix_msg_payment;

	/**
	 * True if pix discount is disabled together coupons.
	 *
	 * @var bool
	 */
	public $pix_discount_coupon;

	/**
	 * Hours to valid payment from pix.
	 *
	 * @var int
	 */
	public $pix_validate;


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'virtuaria_erede_pix';
		$this->icon               = apply_filters(
			'woocommerce_erede_virt_icon_pix',
			VIRTUARIA_EREDE_URL . '/public/images/erede.png'
		);
		$this->has_fields         = false;
		$this->method_title       = __( 'eRede Pix', 'virtuaria-eredeitau' );
		$this->method_description = __( 'Pague com Pix.', 'virtuaria-eredeitau' );

		$this->supports = array( 'products', 'refunds' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		$settings_global = Virtuaria_ERede_Settings::get_settings();

		// Define user set variables.
		$this->title               = $this->get_option( 'title' );
		$this->description         = $this->get_option( 'description' );
		$this->environment         = isset( $settings_global['environment'] )
			? $settings_global['environment']
			: 'sandbox';
		$this->debug               = isset( $settings_global['debug'] )
			? $settings_global['debug']
			: 'no';
		$this->process_mode        = isset( $settings_global['process_mode'] )
			? $settings_global['process_mode']
			: 'sync';
		$this->pv                  = isset( $settings_global['pv'] )
			? $settings_global['pv']
			: '';
		$this->token               = isset( $settings_global['integration_key'] )
			? $settings_global['integration_key']
			: '';
		$this->pix_validate        = $this->get_option( 'pix_validate' );
		$this->pix_discount        = $this->get_option( 'pix_discount' );
		$this->pix_msg_payment     = $this->get_option( 'pix_msg_payment' );
		$this->pix_discount_coupon = 'yes' === $this->get_option( 'pix_discount_coupon' );

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

		add_action( 'admin_enqueu_scripts', array( $this, 'admin_pix_scripts' ) );
		add_action(
			'woocommerce_thankyou_' . $this->id,
			array( $this, 'pix_thankyou_page' )
		);
		add_action(
			'woocommerce_email_after_order_table',
			array( $this, 'pix_email_instructions' ),
			10,
			3
		);
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'public_pix_scripts_styles' )
		);

		add_filter(
			'virtuaria_erede_disable_discount',
			array( $this, 'disable_discount_by_product_categoria' ),
			10,
			3
		);
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'discount_text' ),
			10,
			2
		);
		add_action(
			'after_virtuaria_pix_validate_text',
			array( $this, 'display_total_discounted' )
		);
		add_action(
			'after_virtuaria_pix_validate_text',
			array( $this, 'info_about_categories' ),
			20
		);
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
			'enabled'             => array(
				'title'   => __( 'Habilitar', 'virtuaria-eredeitau' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilita o método de Pagamento Virtuaria eRede', 'virtuaria-eredeitau' ),
				'default' => 'yes',
			),
			'title'               => array(
				'title'       => __( 'Título', 'virtuaria-eredeitau' ),
				'type'        => 'text',
				'description' => __( 'Isto controla o título exibido ao usuário durante o checkout.', 'virtuaria-eredeitau' ),
				'desc_tip'    => true,
				'default'     => __( 'eRede Pix', 'virtuaria-eredeitau' ),
			),
			'description'         => array(
				'title'       => __( 'Descrição', 'virtuaria-eredeitau' ),
				'type'        => 'textarea',
				'description' => __( 'Controla a descrição exibida ao usuário durante o checkout.', 'virtuaria-eredeitau' ),
				'default'     => __( 'Pague com pix.', 'virtuaria-eredeitau' ),
			),
			'pix_validate'        => array(
				'title'       => __( 'Validade do Código PIX', 'virtuaria-eredeitau' ),
				'type'        => 'select',
				'description' => __( 'Define o limite de tempo para aceitar pagamentos com PIX.', 'virtuaria-eredeitau' ),
				'options'     => array(
					'1800'  => '30 Minutos',
					'3600'  => '1 hora',
					'5400'  => '1 hora e 30 minutos',
					'7200'  => '2 horas',
					'9000'  => '2 horas e 30 minutos',
					'10800' => '3 horas',
				),
				'default'     => '1800',
			),
			'pix_msg_payment'     => array(
				'title'       => __( 'Pagamento confirmado', 'virtuaria-eredeitau' ),
				'type'        => 'textarea',
				'description' => __( 'Define a mensagem que será exibida na tela de pedido após o pagamento do Pix. O pagamento é identificado automaticamente e a tela muda exibindo esta mensagem.', 'virtuaria-eredeitau' ),
				'default'     => 'Seu pagamento foi aprovado!',
			),
			'pix_discount'        => array(
				'title'             => __( 'Desconto (%)', 'virtuaria-eredeitau' ),
				'type'              => 'number',
				'description'       => __( 'Define um percentual de desconto a ser aplicado ao total do pedido, caso o pagamento seja realizado com Pix. O desconto não incide sobre o valor do frete.', 'virtuaria-eredeitau' ),
				'custom_attributes' => array(
					'min'  => 0,
					'step' => '0.01',
				),
			),
			'pix_discount_coupon' => array(
				'title'       => __( 'Desabilitar desconto em cupons', 'virtuaria-eredeitau' ),
				'type'        => 'checkbox',
				'label'       => __( 'Desabilita o desconto Pix em conjunto com cupons', 'virtuaria-eredeitau' ),
				'description' => __( 'Desabilita o desconto Pix, caso um cupom seja aplicado ao pedido.', 'virtuaria-eredeitau' ),
				'default'     => '',
			),
			'pix_discount_ignore' => array(
				'title'       => __( 'Desabilitar desconto em produtos das seguintes categorias', 'virtuaria-eredeitau' ),
				'type'        => 'ignore_discount',
				'description' => __( 'Define as categorias que serão ignoradas para o cálculo do desconto pix.', 'virtuaria-eredeitau' ),
				'default'     => '',
			),
		);

		$this->form_fields['tecvirtuaria'] = array(
			'title' => __( 'Tecnologia Virtuaria', 'virtuaria-eredeitau' ),
			'type'  => 'title',
		);
	}

	/**
	 * Thank You page message.
	 *
	 * @param int $order_id Order ID.
	 */
	public function pix_thankyou_page( $order_id ) {
		$order       = wc_get_order( $order_id );
		$qr_code     = $order->get_meta( '_erede_qrcode', true );
		$qr_code_png = $order->get_meta( '_erede_qrcode_png', true );

		if ( $qr_code && $qr_code_png ) {
			$validate = $this->format_pix_validate( $this->pix_validate );
			require Virtuaria_ERede::get_templates_path() . 'payment-instructions.php';
		}
	}

	/**
	 * Checkout scripts.
	 */
	public function public_pix_scripts_styles() {
		if ( is_checkout()
			&& $this->is_available()
			&& get_query_var( 'order-received' ) ) {
			global $wp;
			wp_enqueue_script(
				'erede-payment-on-hold',
				VIRTUARIA_EREDE_URL . 'public/js/on-hold-payment.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_EREDE_DIR . 'public/js/on-hold-payment.js' ),
				true
			);

			wp_localize_script(
				'erede-payment-on-hold',
				'payment',
				array(
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'order_id'        => $wp->query_vars['order-received'],
					'nonce'           => wp_create_nonce( 'fecth_order_status' ),
					'confirm_message' => $this->pix_msg_payment,
				)
			);

			wp_enqueue_style(
				'erede-payment-on-hold',
				VIRTUARIA_EREDE_URL . 'public/css/on-hold-payment.css',
				'',
				filemtime( VIRTUARIA_EREDE_DIR . 'public/css/on-hold-payment.css' )
			);
		}
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

			$paid = $this->api->new_charge_pix(
				$order,
				$_POST,
				array(
					'pix_validate'        => $this->pix_validate,
					'pix_discount'        => $this->pix_discount,
					'pix_discount_coupon' => $this->pix_discount_coupon,
				)
			);

			$order = wc_get_order( $order->get_id() );

			if ( ! isset( $paid['error'] ) ) {
				if ( 'async' !== $this->process_mode ) {
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

		$disable_discount = ( property_exists( $this, 'pix_discount_coupon' )
			&& $this->pix_discount_coupon
			&& WC()->cart
			&& count( WC()->cart->get_applied_coupons() ) > 0 )
			|| apply_filters( 'virtuaria_erede_disable_discount_by_cart', false, WC()->cart );

		$checkou_args = array(
			'cart_total'     => $cart_total,
			'flag'           => plugins_url(
				'assets/images/brazilian-flag.png',
				VIRTUARIA_EREDE_URL
			),
			'pix_validate'   => method_exists( $this, 'format_pix_validate' )
				? $this->format_pix_validate(
					$this->pix_validate
				)
				: '',
			'pix_discount'   => isset( $this->pix_discount )
				&& $this->pix_discount
				&& ! $disable_discount
					? $this->pix_discount / 100
					: 0,
			'pix_offer_text' => method_exists( $this, 'discount_text' )
				? $this->discount_text(
					'PIX',
					$this->id
				)
				: '',
		);

		wc_get_template(
			'pix-checkout.php',
			$checkou_args,
			'woocommerce/erede/',
			Virtuaria_ERede::get_templates_path()
		);
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
	 * Formatter pix validate
	 *
	 * @param string $validate the time of pix validate.
	 * @return string
	 */
	private function format_pix_validate( $validate ) {
		$format = intval( $validate ) / 3600;
		switch ( $format ) {
			case 0.5:
				$format = '30 minutos';
				break;
			case 1:
				$format = '1 hora';
				break;
			case 1.5:
				$format = '1 hora e 30 minutos';
				break;
			case 2:
				$format = '2 horas';
				break;
			case 2.5:
				$format = '2 horas e 30 minutos';
				break;
			default:
				$format = '3 horas';
				break;
		}
		return $format;
	}

	/**
	 * Add QR Code in order note.
	 *
	 * @param wc_order $order   the order.
	 * @param string   $qr_code the qr code.
	 */
	private function add_qrcode_in_note( $order, $qr_code ) {
		if ( function_exists( '\\order\\limit_characters_order_note' ) ) {
			remove_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
			$order->add_order_note(
				'Erede Pix Copia e Cola: <div class="pix">'
				. $qr_code
				. '</div><a href="#" id="copy-qr" class="button button-primary">Copiar</a>'
			);
			add_filter(
				'woocommerce_new_order_note_data',
				'\\order\\limit_characters_order_note'
			);
		} else {
			$order->add_order_note(
				'Erede Pix Copia e Cola: <div class="pix">'
				. $qr_code
				. '</div><a href="#" id="copy-qr" class="button button-primary">Copiar</a>'
			);
		}
	}

	/**
	 * Check payment pix.
	 *
	 * @param wc_order $order the order.
	 */
	public function check_payment_pix( $order ) {
		$qr_code = $order->get_meta(
			'_erede_qrcode',
			true
		);
		if ( $qr_code ) {
			$this->add_qrcode_in_note( $order, $qr_code );

			$args = array( $order->get_id() );
			if ( ! wp_next_scheduled( 'erede_pix_check_payment', $args ) ) {
				wp_schedule_single_event(
					strtotime( 'now' ) + $this->pix_validate + 1800,
					'erede_pix_check_payment',
					$args
				);
			}
		}
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param  WC_Order $order         Order object.
	 * @param  bool     $sent_to_admin Send to admin.
	 * @param  bool     $plain_text    Plain text or HTML.
	 * @return string
	 */
	public function pix_email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin
			|| 'on-hold' !== $order->get_status()
			|| $this->id !== $order->get_payment_method() ) {
			return;
		}

		$qr_code     = $order->get_meta( '_erede_qrcode', true );
		$qr_code_png = $order->get_meta( '_erede_qrcode_png', true );

		if ( $qr_code && $qr_code_png ) {
			$validate = $this->format_pix_validate( $this->pix_validate );
			$is_mail  = true;
			require Virtuaria_ERede::get_templates_path() . 'payment-instructions.php';
		}
	}

	/**
	 * Display ignore discount field.
	 *
	 * @param string $key  the name from field.
	 * @param array  $data the data.
	 */
	public function generate_ignore_discount_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );

		$this->save_categories_ignored_in_discount( $key );

		$selected_cats = $this->get_option( $key );

		$selected_cats = is_array( $selected_cats )
			? $selected_cats
			: explode( ',', $selected_cats );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>">
					<?php echo esc_html( $data['title'] ); ?>
					<span class="woocommerce-help-tip" data-tip="<?php echo esc_html( $data['description'] ); ?>"></span>
				</label>
			</th>
			<td class="forminp forminp-<?php echo esc_attr( $data['type'] ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" />
				<div id="product_cat-all" class="tabs-panel">
					<ul id="product_catchecklist" data-wp-lists="list:product_cat" class="categorychecklist form-no-clear">
						<?php
						wp_terms_checklist(
							0,
							array(
								'taxonomy'      => 'product_cat',
								'selected_cats' => $selected_cats,
							)
						);
						?>
					</ul>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save categories ignored in discount.
	 *
	 * @param string $key option key.
	 */
	private function save_categories_ignored_in_discount( $key ) {
		if ( isset( $_POST[ 'woocommerce_virt_erede_' . $key ] ) ) {
			$ignored = sanitize_text_field( wp_unslash( $_POST[ 'woocommerce_virt_erede_' . $key ] ) );
			$ignored = explode( ',', $ignored );
			$this->update_option(
				$key,
				$ignored
			);
		}
	}

	/**
	 * Ignore product from categorie to discount.
	 *
	 * @param boolean    $disable true if disable item otherwise false.
	 * @param wc_product $product the itens.
	 * @param string     $method  the method.
	 */
	public function disable_discount_by_product_categoria( $disable, $product, $method ) {
		$to_categories = $this->get_option( $method . '_discount_ignore', '' );

		$ignored_categories = is_array( $to_categories )
			? $to_categories
			: explode(
				',',
				$to_categories
			);

		if ( $ignored_categories
			&& is_array( $ignored_categories )
			&& count( $product->get_category_ids() ) > 0 ) {
			foreach ( $product->get_category_ids() as $category_id ) {
				if ( in_array( $category_id, $ignored_categories ) ) {
					$disable = true;
					break;
				}
			}
		}
		return $disable;
	}

	/**
	 * Display discount pix text.
	 *
	 * @param string $title      the gateway title.
	 * @param string $gateway_id the gateway id.
	 */
	public function discount_text( $title, $gateway_id ) {
		if ( is_checkout()
			&& isset( $_REQUEST['wc-ajax'] )
			&& 'update_order_review' === $_REQUEST['wc-ajax']
			&& ! apply_filters( 'virtuaria_erede_disable_discount_by_cart', false, WC()->cart ) ) {

			$has_discount = false;
			if ( 'virt_erede_pix' === $gateway_id ) {
				$has_discount = 'yes' === $this->is_available()
					&& $this->pix_discount > 0
					&& ( ! $this->pix_discount_coupon || count( WC()->cart->get_applied_coupons() ) === 0 );
			}

			if ( ! $has_discount ) {
				return $title;
			}

			$discount = $this->pix_discount;

			$title .= '<span class="pix-discount">(desconto de <span class="percentage">'
				. str_replace( '.', ',', $discount ) . '%</span>)';

			$title .= '</span>';
		}
		return $title;
	}

	/**
	 * Text about categories disable to pix discount.
	 *
	 * @param array $itens the cart itens.
	 */
	public function info_about_categories( $itens ) {
		$ignored_categories = $this->get_option( 'pix_discount_ignore', '' );

		if ( is_array( $ignored_categories ) ) {
			$ignored_categories = array_filter( $ignored_categories );
		}

		$enabled = 'yes' === $this->is_available() && $this->pix_discount > 0;

		if ( $enabled
			&& is_array( $ignored_categories )
			&& $ignored_categories ) {

			$category_disabled = array();
			foreach ( $ignored_categories as $index => $category ) {
				$term = get_term( $category );
				if ( $term && ! is_wp_error( $term ) ) {
					$category_disabled[] = ucwords( mb_strtolower( $term->name ) );
				}
			}

			if ( $category_disabled ) {
				echo '<div class="info-category">' . wp_kses_post(
					sprintf(
						/* translators: %s: categories */
						_nx(
							'O desconto do Pix não é válido para produtos da categoria <span class="categories">%$</span>.',
							'O desconto do Pix não é válido para produtos das categorias <span class="categories">%$</span>.',
							count( $category_disabled ),
							'Checkout',
							'virtuaria-eredeitau'
						),
						implode( ', ', $category_disabled )
					)
				) . '</div>';
			}
		}
	}

	/**
	 * Display the total discounted amount and the new total after applying discounts.
	 */
	public function display_total_discounted() {
		$disabled_with_coupon = $this->pix_discount_coupon;
		$discount_percentual  = $this->pix_discount
			? $this->pix_discount / 100
			: 0;

		if ( ( $disabled_with_coupon
			&& WC()->cart
			&& count( WC()->cart->get_applied_coupons() ) > 0 )
			|| apply_filters( 'virtuaria_erede_disable_discount_by_cart', false, WC()->cart ) ) {
			return;
		}

		if ( $discount_percentual > 0 ) {
			$shipping = 0;
			if ( isset( WC()->cart ) && WC()->cart->get_shipping_total() > 0 ) {
				$shipping = WC()->cart->get_shipping_total();
			}

			$cart_total      = $this->get_order_total();
			$discount_reduce = 0;
			$discount        = ( $cart_total - $shipping );
			foreach ( WC()->cart->get_cart() as $item ) {
				$product = wc_get_product( $item['product_id'] );
				if ( $product && apply_filters(
					'virtuaria_erede_disable_discount',
					false,
					$product,
					'pix'
				) ) {
					$discount_reduce += $product->get_price() * $item['quantity'];
				}
			}
			$discount -= $discount_reduce;
			$discount  = $discount * $discount_percentual;
			if ( $discount > 0 ) {
				echo '<span class="discount">Desconto: <b style="color:green;">R$ '
				. esc_html( number_format( $discount, 2, ',', '.' ) )
				. '</b></span>';
				echo '<span class="total">Novo total: <b style="color:green">R$ '
				. esc_html( number_format( $cart_total - $discount, 2, ',', '.' ) )
				. '</b></span>';
			}
		}
	}

	/**
	 * Enqueue scripts for the admin pix setup page.
	 *
	 * Conditionally load the script only when the page is the setup pix page.
	 */
	public function admin_pix_scripts() {
		if ( isset( $_GET['section'] ) && 'virtuaria_erede_pix' === $_GET['section'] ) {
			wp_enqueue_script(
				'setup-pix',
				VIRTUARIA_EREDE_URL . 'admin/js/setup-pix.js',
				array( 'jquery' ),
				filemtime( VIRTUARIA_EREDE_DIR . 'admin/js/setup-pix.js' ),
				true
			);
		}
	}
}
