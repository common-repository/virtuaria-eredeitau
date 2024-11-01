<?php
/**
 * Transparent checkout.
 *
 * @package Virtuaria/Payments/Erede
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'woocommerce_virt_erede_settings' );
if ( is_user_logged_in()
	&& isset( $settings['save_card_info'] )
	&& 'do_not_store' !== $settings['save_card_info'] ) {
	$customer_id     = get_current_user_id();
	$erede_card_info = get_user_meta(
		$customer_id,
		'_virt_erede_credit_info_store_' . get_current_blog_id(),
		true
	);

	if ( $erede_card_info ) {
		foreach ( $erede_card_info as $index => $value ) {
			$erede_card_info[ $index ] = Virtuaria_Erede_Encryptation::decrypt(
				$value,
				base64_encode( $pv . ':' . $customer_id ),
				true
			);
		}

		if ( isset( $erede_card_info['integrity'] )
			&& intval( $erede_card_info['integrity'] ) === $customer_id ) {
			$card_loaded = true;
		}
	}
}

$class_card_loaded = isset( $card_loaded ) && $card_loaded ? 'card-loaded' : '';

$fields = array(
	'erede_holder_name'   => '',
	'erede_card_number'   => '',
	'erede_card_validate' => '',
	'erede_card_cvc'      => '',
);
if ( isset( $_POST['rede_charge_nonce'] )
	&& wp_verify_nonce(
		sanitize_text_field(
			wp_unslash( $_POST['rede_charge_nonce'] )
		),
		'rede_new_charge'
	)
) {
	$fields['erede_holder_name']   = isset( $_POST['erede_holder_name'] )
		? sanitize_text_field( wp_unslash( $_POST['erede_holder_name'] ) )
		: '';
	$fields['erede_card_number']   = isset( $_POST['erede_card_number'] )
		? sanitize_text_field( wp_unslash( $_POST['erede_card_number'] ) )
		: '';
	$fields['erede_card_validate'] = isset( $_POST['erede_card_validate'] )
		? sanitize_text_field( wp_unslash( $_POST['erede_card_validate'] ) )
		: '';
	$fields['erede_card_cvc']      = isset( $_POST['erede_card_cvc'] )
		? sanitize_text_field( wp_unslash( $_POST['erede_card_cvc'] ) )
		: '';
}

?>
<fieldset id="erede-payment" data-cart_total="<?php echo esc_attr( number_format( $cart_total, 2, '.', '' ) ); ?>" class="<?php echo esc_attr( $class_card_loaded ); ?>">
	<div id="erede-credit-card-form" class="erede-method-form">
		<p id="erede-card-holder-name-field" class="form-row form-row-first <?php echo esc_attr( $class_card_loaded ); ?>">
			<label for="erede-card-holder-name"><?php esc_html_e( 'Titular', 'virtuaria-eredeitau' ); ?> <small>(<?php esc_html_e( 'como no cartão', 'virtuaria-eredeitau' ); ?>)</small> <span class="required">*</span></label>
			<input id="erede-card-holder-name" name="erede_card_holder_name" class="input-text" type="text" autocomplete="off" style="font-size: 1.5em; padding: 8px;" value="<?php echo esc_html( sanitize_text_field( $fields['erede_holder_name'] ) ); ?>"/>
		</p>
		<p id="erede-card-number-field" class="form-row form-row-last <?php echo esc_attr( $class_card_loaded ); ?>">
			<label for="erede-card-number"><?php esc_html_e( 'Número do cartão', 'virtuaria-eredeitau' ); ?> <span class="required">*</span></label>
			<input id="erede-card-number" name="erede_card_number" maxlength="22" class="input-text wc-credit-card-form-card-number" type="tel" autocomplete="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" style="font-size: 1.5em; padding: 8px;"  value="<?php echo esc_html( $fields['erede_card_number'] ); ?>"/>
		</p>
		<div class="clear"></div>
		<p id="erede-card-expiry-field" class="form-row form-row-first <?php echo esc_attr( $class_card_loaded ); ?>">
			<label for="erede-card-expiry"><?php esc_html_e( 'Validade (MM / AAAA)', 'virtuaria-eredeitau' ); ?> <span class="required">*</span></label>
			<input id="erede-card-expiry" name="erede_card_validate" class="input-text wc-credit-card-form-card-expiry" type="tel" autocomplete="off" placeholder="<?php esc_html_e( 'MM / AAAA', 'virtuaria-eredeitau' ); ?>" style="font-size: 1.5em; padding: 8px;"  value="<?php echo esc_html( $fields['erede_card_validate'] ); ?>" maxlength="9"/>
		</p>
		<p id="erede-card-cvc-field" class="form-row form-row-last <?php echo esc_attr( $class_card_loaded ); ?>">
			<label for="erede-card-cvc"><?php esc_html_e( 'Código de segurança', 'virtuaria-eredeitau' ); ?> <span class="required">*</span></label>
			<input id="erede-card-cvc" name="erede_card_cvc" class="input-text wc-credit-card-form-card-cvc" type="tel" autocomplete="off" placeholder="<?php esc_html_e( 'CVV', 'virtuaria-eredeitau' ); ?>" style="font-size: 1.5em; padding: 8px;"  value="<?php echo esc_html( $fields['erede_card_cvc'] ); ?>"/>
		</p>
		<div class="clear"></div>
		<p id="erede-card-installments-field" class="form-row form-row-first">
			<label for="erede-card-installments">
				<?php
				esc_html_e( 'Parcelas', 'virtuaria-eredeitau' );

				if ( $min_installment ) :
					?>
					<small>(
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: amount */
								__( 'mínima de R$ %s', 'virtuaria-eredeitau' ),
								number_format( $min_installment, 2, ',', '.' )
							)
						);
						?>
						)
					</small>
					<?php
				endif;
				?>
				<span class="required">*</span>
			</label>
			<select id="erede-card-installments" name="erede_installments" style="font-size: 1.5em; padding: 4px; width: 100%;">
				<?php
				foreach ( $installments as $index => $installment ) {
					if ( 0 !== $index && $installment < 5 ) {
						// Mínimo de 5 reais por parcela.
						break;
					}
					$aux = $index + 1;
					if ( 1 === $aux ) {
						printf(
							'<option value="%d">%dx de %s sem juros</option>',
							esc_attr( $aux ),
							esc_attr( $aux ),
							wp_kses_post( wc_price( $installment ) )
						);
					} elseif ( ( $installment / $aux ) > $min_installment ) {
						printf(
							'<option value="%d">%dx de %s %s</option>',
							esc_attr( $aux ),
							esc_attr( $aux ),
							wp_kses_post( wc_price( $installment / $aux ) ),
							$has_tax && $fee_from <= $aux ? '(' . wp_kses_post( wc_price( $installment ) ) . ')' : ' sem juros'
						);
					}
				}
				?>
			</select>
			<?php
			if ( is_user_logged_in()
				&& isset( $settings['save_card_info'] )
				&& 'do_not_store' !== $settings['save_card_info']
				&& isset( $card_loaded )
				&& $card_loaded ) :
				?>
				<div class="card-in-use erede">
					<?php
					if ( $erede_card_info['card_last'] ) {
						echo wp_kses_post(
							sprintf(
								/* translators: %s: card itens */
								__( '<span class="card-brand"><img src="%1$s" alt="Cartão" /></i>%2$s</span><span class="number">**** **** **** %3$s</span><span class="holder">%4$s</span>', 'virtuaria-eredeitau' ),
								esc_url( VIRTUARIA_EREDE_URL ) . 'public/images/card.png',
								ucwords( mb_strtolower( $erede_card_info['card_brand'] ) ),
								$erede_card_info['card_last'],
								$erede_card_info['cardholderName']
							)
						);
					}
					?>
				</div>
				<?php
			endif;
			?>
		</p>
		<div class="clear after-installments"></div>
		<?php
		if ( is_user_logged_in()
			&& isset( $settings['save_card_info'] )
			&& 'do_not_store' !== $settings['save_card_info'] ) :
			if ( isset( $card_loaded ) && $card_loaded ) :
				?>
				<p id="erede-load-card" class="form-now form-wide">
					<label for="erede-use-other-card"><?php esc_attr_e( 'Usar outro cartão?', 'virtuaria-eredeitau' ); ?></label>
					<input type="checkbox" name="erede_use_other_card" id="erede-use-other-card" value="yes"/>
					<input type="hidden" name="erede_save_hash_card" id="erede-save-hash-card" value="yes"/>
				</p>
				<?php
			else :
				if ( isset( $settings['save_card_info'] )
					&& 'always_store' === $settings['save_card_info'] ) :
					?>
					<p id="erede-save-card" class="form-now form-wide">
						<label for="erede-save-hash-card" style="font-size: 12px;">
							<?php esc_html_e( 'Ao finalizar a compra, permito que a loja memorize esta forma de pagamento.', 'virtuaria-eredeitau' ); ?>
						</label>
						<input type="hidden" name="erede_save_hash_card" id="erede-save-hash-card" value="yes"/>
					</p>
					<?php
				else :
					?>
					<p id="erede-save-card" class="form-now form-wide">
						<label for="erede-save-hash-card"><?php esc_html_e( 'Salvar método de pagamento para compras futuras?', 'virtuaria-eredeitau' ); ?></label>
						<input type="checkbox" name="erede_save_hash_card" id="erede-save-hash-card" value="yes"/>
					</p>
					<?php
				endif;
			endif;
			?>
			<div class="clear"></div>
			<?php
		endif;
		?>
		<input type="hidden" name="erede_encrypted_card" id="erede_encrypted_card" />
	</div>
	<?php wp_nonce_field( 'rede_new_charge', 'rede_charge_nonce' ); ?>
</fieldset>
