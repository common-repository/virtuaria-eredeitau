<?php
/**
 * Handle common functions.
 *
 * @package virtuaria/ERede
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Handle common functions.
 */
trait Virtuaria_ERede_Trait_Common_Functions {
	/**
	 * Retrieve the screen ID for meta boxes.
	 *
	 * @return string
	 */
	private function get_meta_boxes_screen() {
		return class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			&& function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	/**
	 * Get installment value with tax.
	 *
	 * @param float $total       the total from cart.
	 * @param int   $installment the installment selected.
	 * @param float $tax         the tax.
	 */
	public function get_installment_value( $total, $installment, $tax ) {
		if ( ! $tax ) {
			return $total;
		}
		$tax        = floatval( $tax ) / 100;
		$subtotal   = $total;
		$n_parcelas = range( 1, $installment );
		foreach ( $n_parcelas as $installment ) {
			$subtotal += ( $subtotal * $tax );
		}
		return $subtotal;
	}
}
