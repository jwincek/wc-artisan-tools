<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\WooCommerce;

/**
 * Simplified WooCommerce product CRUD for artisan dashboard.
 *
 * Creates and updates standard WooCommerce simple products from
 * the streamlined dashboard form.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Product_Manager {

	/**
	 * Initialise product manager hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		// No hooks needed — called directly from Dashboard form handler.
	}

	/**
	 * Save a product from the simplified form data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data    Sanitized form data.
	 * @param int   $product_id Existing product ID (0 for new).
	 * @return int|\WP_Error Product ID or error.
	 */
	public static function save_from_form( array $data, int $product_id = 0 ): int|\WP_Error {
		$title       = sanitize_text_field( wp_unslash( $data['title'] ?? '' ) );
		$price       = (float) ( $data['price'] ?? 0 );
		$description = sanitize_textarea_field( wp_unslash( $data['description'] ?? '' ) );
		$is_sold     = ! empty( $data['is_sold'] );
		$is_featured = ! empty( $data['is_featured'] );
		$image_id    = absint( $data['featured_image'] ?? 0 );

		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', __( 'Product name is required.', 'wc-artisan-tools' ) );
		}

		if ( $price <= 0 ) {
			return new \WP_Error( 'invalid_price', __( 'Price must be greater than zero.', 'wc-artisan-tools' ) );
		}

		// Create or load product.
		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return new \WP_Error( 'not_found', __( 'Product not found.', 'wc-artisan-tools' ) );
			}
		} else {
			$product = new \WC_Product_Simple();
		}

		$product->set_name( $title );
		$product->set_regular_price( (string) $price );
		$product->set_short_description( $description );
		$product->set_stock_status( $is_sold ? 'outofstock' : 'instock' );
		$product->set_featured( $is_featured );
		$product->set_sold_individually( true );
		$product->set_catalog_visibility( 'visible' );

		if ( $image_id ) {
			$product->set_image_id( $image_id );
		} elseif ( ! $product_id ) {
			// Only clear image on new products if none set.
			$product->set_image_id( 0 );
		}

		$saved_id = $product->save();

		if ( ! $saved_id ) {
			return new \WP_Error( 'save_failed', __( 'Failed to save product.', 'wc-artisan-tools' ) );
		}

		// Set taxonomy terms.
		$taxonomies = [
			'wcat_product_type' => sanitize_text_field( wp_unslash( $data['product_type'] ?? '' ) ),
			'wcat_material'     => sanitize_text_field( wp_unslash( $data['material'] ?? '' ) ),
			'wcat_finish'       => sanitize_text_field( wp_unslash( $data['finish'] ?? '' ) ),
			'wcat_component'    => sanitize_text_field( wp_unslash( $data['component'] ?? '' ) ),
		];

		foreach ( $taxonomies as $taxonomy => $slug ) {
			if ( $slug ) {
				wp_set_object_terms( $saved_id, $slug, $taxonomy );
			} else {
				wp_set_object_terms( $saved_id, [], $taxonomy );
			}
		}

		// Set origin to "Shop" for products created via dashboard.
		if ( ! $product_id ) {
			wp_set_object_terms( $saved_id, 'shop', 'wcat_product_origin' );
		}

		return $saved_id;
	}

	/**
	 * Create a hidden WooCommerce product for an accepted commission.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $commission_id Commission post ID.
	 * @param float  $price         Quoted price.
	 * @param string $title         Product title.
	 * @return int|\WP_Error Product ID or error.
	 */
	public static function create_commission_product( int $commission_id, float $price, string $title ): int|\WP_Error {
		$product = new \WC_Product_Simple();

		$product->set_name( $title );
		$product->set_regular_price( (string) $price );
		$product->set_stock_status( 'instock' );
		$product->set_sold_individually( true );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_reviews_allowed( false );

		$saved_id = $product->save();

		if ( ! $saved_id ) {
			return new \WP_Error( 'product_creation_failed', __( 'Failed to create commission product.', 'wc-artisan-tools' ) );
		}

		// Flag as commission product.
		update_post_meta( $saved_id, '_wcat_commission', '1' );
		update_post_meta( $saved_id, '_wcat_commission_id', $commission_id );

		// Set origin taxonomy.
		wp_set_object_terms( $saved_id, 'commission', 'wcat_product_origin' );

		// Set craft type and material from commission if available.
		$craft_type    = get_post_meta( $commission_id, '_wcat_craft_type', true );
		$material_pref = get_post_meta( $commission_id, '_wcat_material_pref', true );

		if ( $craft_type ) {
			wp_set_object_terms( $saved_id, $craft_type, 'wcat_product_type' );
		}
		if ( $material_pref ) {
			wp_set_object_terms( $saved_id, $material_pref, 'wcat_material' );
		}

		return $saved_id;
	}
}
