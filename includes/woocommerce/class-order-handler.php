<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\WooCommerce;

/**
 * Handles WooCommerce order completion for commission products.
 *
 * When a commission product is purchased, updates the commission status
 * and links the order to the commission record.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Order_Handler {

	/**
	 * Initialise order handler hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'woocommerce_order_status_completed', [ self::class, 'handle_order_complete' ] );
		add_action( 'woocommerce_order_status_processing', [ self::class, 'handle_order_complete' ] );
	}

	/**
	 * Handle order completion — update commission status and link order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public static function handle_order_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id    = $item->get_product_id();
			$commission_id = (int) get_post_meta( $product_id, '_wcat_commission_id', true );

			if ( ! $commission_id ) {
				continue;
			}

			$current_status = get_post_meta( $commission_id, '_wcat_status', true );

			// Only update if commission is in 'accepted' status (not yet paid).
			if ( 'accepted' !== $current_status ) {
				continue;
			}

			update_post_meta( $commission_id, '_wcat_wc_order_id', $order_id );
			update_post_meta( $commission_id, '_wcat_status', 'in_progress' );

			delete_transient( 'wcat_pending_count' );
		}
	}
}
