<?php
/**
 * Template form pix.
 *
 * @package Virtuaria/Payments/Erede.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="virt-erede-banking-pix-form" class="virt-erede-method-form payment-details">
	<div class="pix-desc">
		<?php
		echo '<span>' . esc_html( __( 'O pedido será confirmado apenas após a confirmação do pagamento.', 'virtuaria-eredeitau' ) ) . '</span>';
		echo '<span>' . esc_html(
			sprintf(
				/* translators: %s: pix validate */
				__( 'Pague com PIX. O código de pagamento tem validade de %s.', 'virtuaria-eredeitau' ),
				$pix_validate
			)
		) . '</span>';

		do_action( 'after_virtuaria_pix_validate_text', WC()->cart );
		?>
	</div>
	<i id="erede-icon-pix"></i>
	<div class="clear"></div>
</div>
