<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\WooCommerce;

use WC_Artisan_Tools\Commission\Commission_Handler;

/**
 * Adds a "My Commissions" endpoint to WooCommerce My Account.
 *
 * Logged-in customers can view their commission history and status.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class My_Account {

	private const ENDPOINT = 'commissions';

	/**
	 * Initialise My Account hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'register_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ self::class, 'add_menu_item' ] );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ self::class, 'render_endpoint' ] );
		add_filter( 'query_vars', [ self::class, 'add_query_var' ] );
	}

	/**
	 * Register the commissions endpoint.
	 *
	 * @since 1.0.0
	 */
	public static function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add query var for endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function add_query_var( array $vars ): array {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Add menu item to My Account navigation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public static function add_menu_item( array $items ): array {
		// Insert before logout.
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );

		$items[ self::ENDPOINT ] = __( 'My Commissions', 'wc-artisan-tools' );

		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}

		return $items;
	}

	/**
	 * Render the commissions endpoint content.
	 *
	 * @since 1.0.0
	 */
	public static function render_endpoint(): void {
		$user  = wp_get_current_user();
		$email = $user->user_email;

		// Find commissions by customer email.
		$query = new \WP_Query( [
			'post_type'      => 'wcat_commission',
			'post_status'    => 'publish',
			'meta_query'     => [ [
				'key'   => '_wcat_customer_email',
				'value' => $email,
			] ],
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		if ( empty( $query->posts ) ) {
			echo '<p>' . esc_html__( 'You have no commission requests.', 'wc-artisan-tools' ) . '</p>';
			return;
		}

		?>
		<table class="woocommerce-orders-table shop_table shop_table_responsive">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Request', 'wc-artisan-tools' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wc-artisan-tools' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wc-artisan-tools' ); ?></th>
					<th><?php esc_html_e( 'Quote', 'wc-artisan-tools' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wc-artisan-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $query->posts as $post ) :
					$status     = get_post_meta( $post->ID, '_wcat_status', true ) ?: 'new';
					$craft_type = get_post_meta( $post->ID, '_wcat_craft_type', true );
					$price      = get_post_meta( $post->ID, '_wcat_quoted_price', true );
					$product_id = (int) get_post_meta( $post->ID, '_wcat_wc_product_id', true );
					$token      = get_post_meta( $post->ID, '_wcat_quote_token', true );
					?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Request', 'wc-artisan-tools' ); ?>">
							<?php echo esc_html( $craft_type ?: __( 'Custom Piece', 'wc-artisan-tools' ) ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Date', 'wc-artisan-tools' ); ?>">
							<?php echo esc_html( get_the_date( '', $post ) ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Status', 'wc-artisan-tools' ); ?>">
							<?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Quote', 'wc-artisan-tools' ); ?>">
							<?php echo $price ? wp_kses_post( wc_price( (float) $price ) ) : '&mdash;'; ?>
						</td>
						<td data-title="<?php esc_attr_e( 'Action', 'wc-artisan-tools' ); ?>">
							<?php if ( 'quoted' === $status && $token ) : ?>
								<a href="<?php echo esc_url( add_query_arg( [ 'wcat_action' => 'accept', 'token' => $token ], home_url() ) ); ?>"
								   class="woocommerce-button button">
									<?php esc_html_e( 'View Quote', 'wc-artisan-tools' ); ?>
								</a>
							<?php elseif ( 'accepted' === $status && $product_id ) : ?>
								<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>"
								   class="woocommerce-button button">
									<?php esc_html_e( 'Pay Now', 'wc-artisan-tools' ); ?>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
