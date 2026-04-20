<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Commission;

use WC_Artisan_Tools\Core\Config;
use WC_Artisan_Tools\WooCommerce\Product_Manager;

/**
 * Handles the full commission lifecycle: creation, quoting, acceptance,
 * decline, and completion.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Commission_Handler {

	/**
	 * Initialise commission hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		// Handle accept/decline via query string (guest token flow).
		add_action( 'template_redirect', [ self::class, 'handle_token_action' ] );
	}

	/**
	 * Create a commission from form submission data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Sanitized form data.
	 * @return int|\WP_Error Commission post ID or error.
	 */
	public static function create( array $data ): int|\WP_Error {
		$name        = sanitize_text_field( $data['customer_name'] ?? '' );
		$email       = sanitize_email( $data['customer_email'] ?? '' );
		$craft_type  = sanitize_text_field( $data['craft_type'] ?? '' );
		$material    = sanitize_text_field( $data['material_pref'] ?? '' );
		$description = sanitize_textarea_field( $data['description'] ?? '' );
		$budget      = sanitize_text_field( $data['budget_range'] ?? '' );
		$deadline    = sanitize_text_field( $data['deadline'] ?? '' );
		$display     = ! empty( $data['display_name'] );

		if ( empty( $name ) || empty( $email ) || empty( $description ) ) {
			return new \WP_Error( 'missing_fields', __( 'Name, email, and description are required.', 'wc-artisan-tools' ) );
		}

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'wc-artisan-tools' ) );
		}

		// Rate limiting: one request per IP per hour.
		$ip       = self::get_client_ip();
		$rate_key = 'wcat_commission_' . md5( $ip );

		if ( get_transient( $rate_key ) ) {
			return new \WP_Error( 'rate_limited', __( 'Please wait before submitting another request.', 'wc-artisan-tools' ) );
		}

		$post_id = wp_insert_post( [
			'post_type'   => 'wcat_commission',
			'post_title'  => sprintf( '%s — %s', $name, $craft_type ?: __( 'Custom Piece', 'wc-artisan-tools' ) ),
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save meta fields.
		update_post_meta( $post_id, '_wcat_customer_name', $name );
		update_post_meta( $post_id, '_wcat_customer_email', $email );
		update_post_meta( $post_id, '_wcat_craft_type', $craft_type );
		update_post_meta( $post_id, '_wcat_material_pref', $material );
		update_post_meta( $post_id, '_wcat_description', $description );
		update_post_meta( $post_id, '_wcat_budget_range', $budget );
		update_post_meta( $post_id, '_wcat_deadline', $deadline );
		update_post_meta( $post_id, '_wcat_display_name', $display ? '1' : '0' );
		update_post_meta( $post_id, '_wcat_status', 'new' );

		// Set rate limit transient (1 hour).
		set_transient( $rate_key, '1', HOUR_IN_SECONDS );

		// Clear pending count cache.
		delete_transient( 'wcat_pending_count' );

		/**
		 * Fires after a commission is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int $post_id Commission post ID.
		 */
		do_action( 'wcat_commission_created', $post_id );

		return $post_id;
	}

	/**
	 * Send a quote to the customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $commission_id Commission post ID.
	 * @param float  $price         Quoted price.
	 * @param string $date          Estimated completion date.
	 * @param string $note          Note to customer.
	 * @return true|\WP_Error
	 */
	public static function send_quote( int $commission_id, float $price, string $date, string $note ): true|\WP_Error {
		$post = get_post( $commission_id );
		if ( ! $post || 'wcat_commission' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Commission not found.', 'wc-artisan-tools' ) );
		}

		// Generate secure token.
		$token   = wp_generate_password( 32, false );
		$settings = get_option( 'wcat_settings', [] );
		$expiry_days = $settings['commission_expiry_days'] ?? 30;
		$expires = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );

		update_post_meta( $commission_id, '_wcat_quoted_price', $price );
		update_post_meta( $commission_id, '_wcat_estimated_date', $date );
		update_post_meta( $commission_id, '_wcat_maker_note', $note );
		update_post_meta( $commission_id, '_wcat_quote_token', $token );
		update_post_meta( $commission_id, '_wcat_quote_token_expires', $expires );
		update_post_meta( $commission_id, '_wcat_status', 'quoted' );

		delete_transient( 'wcat_pending_count' );

		/**
		 * Fires after a quote is sent.
		 *
		 * @since 1.0.0
		 *
		 * @param int $commission_id Commission post ID.
		 */
		do_action( 'wcat_quote_sent', $commission_id );

		return true;
	}

	/**
	 * Handle accept/decline via tokenized URL.
	 *
	 * Runs on template_redirect for guests. My Account handles logged-in users.
	 *
	 * @since 1.0.0
	 */
	public static function handle_token_action(): void {
		if ( ! isset( $_GET['wcat_action'], $_GET['token'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['wcat_action'] ) );
		$token  = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		if ( ! in_array( $action, [ 'accept', 'decline' ], true ) || empty( $token ) ) {
			return;
		}

		$commission_id = self::find_by_token( $token );

		if ( ! $commission_id ) {
			wp_die(
				esc_html__( 'This quote link is invalid or has expired.', 'wc-artisan-tools' ),
				esc_html__( 'Quote Not Found', 'wc-artisan-tools' ),
				[ 'response' => 404 ]
			);
		}

		// Show confirmation form on GET, process on POST.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			if ( ! wp_verify_nonce( $_POST['wcat_token_nonce'] ?? '', 'wcat_token_action_' . $token ) ) {
				wp_die( esc_html__( 'Invalid request.', 'wc-artisan-tools' ) );
			}

			if ( 'accept' === $action ) {
				$result = self::accept_quote( $commission_id );
			} else {
				$result = self::decline_quote( $commission_id );
			}

			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}

			// On accept, redirect to checkout with the commission product.
			if ( 'accept' === $action ) {
				$product_id = get_post_meta( $commission_id, '_wcat_wc_product_id', true );
				if ( $product_id ) {
					WC()->cart->empty_cart();
					WC()->cart->add_to_cart( (int) $product_id );
					wp_safe_redirect( wc_get_checkout_url() );
					exit;
				}
			}

			// On decline, show thank you message.
			wp_die(
				esc_html__( 'Thank you for letting us know. We appreciate your interest!', 'wc-artisan-tools' ),
				esc_html__( 'Quote Declined', 'wc-artisan-tools' ),
				[ 'response' => 200 ]
			);
		}

		// GET: Show confirmation page.
		self::render_token_page( $commission_id, $action, $token );
		exit;
	}

	/**
	 * Accept a commission quote — create WooCommerce product.
	 *
	 * @since 1.0.0
	 *
	 * @param int $commission_id Commission post ID.
	 * @return true|\WP_Error
	 */
	public static function accept_quote( int $commission_id ): true|\WP_Error {
		$status = get_post_meta( $commission_id, '_wcat_status', true );

		if ( 'quoted' !== $status ) {
			return new \WP_Error( 'invalid_status', __( 'This quote has already been responded to.', 'wc-artisan-tools' ) );
		}

		$price        = (float) get_post_meta( $commission_id, '_wcat_quoted_price', true );
		$customer     = get_post_meta( $commission_id, '_wcat_customer_name', true );
		$craft_type   = get_post_meta( $commission_id, '_wcat_craft_type', true );
		$display_name = get_post_meta( $commission_id, '_wcat_display_name', true );

		// Build product title.
		$title_parts = [ 'Custom' ];
		if ( $craft_type ) {
			$title_parts[] = $craft_type;
		}
		if ( $display_name ) {
			$title_parts[] = 'for ' . $customer;
		}
		$title = implode( ' ', $title_parts );

		$product_id = Product_Manager::create_commission_product( $commission_id, $price, $title );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		update_post_meta( $commission_id, '_wcat_wc_product_id', $product_id );
		update_post_meta( $commission_id, '_wcat_status', 'accepted' );

		// Invalidate token (one-time use for accept).
		delete_post_meta( $commission_id, '_wcat_quote_token' );
		delete_post_meta( $commission_id, '_wcat_quote_token_expires' );

		delete_transient( 'wcat_pending_count' );

		/**
		 * Fires after a quote is accepted.
		 *
		 * @since 1.0.0
		 *
		 * @param int $commission_id Commission post ID.
		 */
		do_action( 'wcat_quote_accepted', $commission_id );

		return true;
	}

	/**
	 * Decline a commission quote.
	 *
	 * @since 1.0.0
	 *
	 * @param int $commission_id Commission post ID.
	 * @return true|\WP_Error
	 */
	public static function decline_quote( int $commission_id ): true|\WP_Error {
		$status = get_post_meta( $commission_id, '_wcat_status', true );

		if ( 'quoted' !== $status ) {
			return new \WP_Error( 'invalid_status', __( 'This quote has already been responded to.', 'wc-artisan-tools' ) );
		}

		update_post_meta( $commission_id, '_wcat_status', 'declined' );

		delete_post_meta( $commission_id, '_wcat_quote_token' );
		delete_post_meta( $commission_id, '_wcat_quote_token_expires' );

		delete_transient( 'wcat_pending_count' );

		/**
		 * Fires after a quote is declined.
		 *
		 * @since 1.0.0
		 *
		 * @param int $commission_id Commission post ID.
		 */
		do_action( 'wcat_quote_declined', $commission_id );

		return true;
	}

	/**
	 * Mark a commission as complete and make the product visible.
	 *
	 * @since 1.0.0
	 *
	 * @param int $commission_id Commission post ID.
	 */
	public static function mark_complete( int $commission_id ): void {
		update_post_meta( $commission_id, '_wcat_status', 'complete' );

		$product_id = (int) get_post_meta( $commission_id, '_wcat_wc_product_id', true );

		if ( $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				// Make visible in shop catalog.
				$product->set_catalog_visibility( 'visible' );
				$product->save();

				// Ensure commission origin taxonomy is set.
				wp_set_object_terms( $product_id, 'commission', 'wcat_product_origin' );
			}
		}

		delete_transient( 'wcat_pending_count' );

		/**
		 * Fires after a commission is marked complete.
		 *
		 * @since 1.0.0
		 *
		 * @param int $commission_id Commission post ID.
		 */
		do_action( 'wcat_commission_completed', $commission_id );
	}

	/**
	 * Find a commission by its quote token.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Quote token.
	 * @return int|null Commission post ID or null.
	 */
	public static function find_by_token( string $token ): ?int {
		if ( empty( $token ) ) {
			return null;
		}

		$query = new \WP_Query( [
			'post_type'      => 'wcat_commission',
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'   => '_wcat_quote_token',
					'value' => $token,
				],
			],
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		if ( empty( $query->posts ) ) {
			return null;
		}

		$commission_id = (int) $query->posts[0];

		// Check expiration.
		$expires = get_post_meta( $commission_id, '_wcat_quote_token_expires', true );
		if ( $expires && strtotime( $expires ) < time() ) {
			return null;
		}

		return $commission_id;
	}

	/**
	 * Get the client's IP address, aware of common proxy headers.
	 *
	 * @since 1.0.0
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$raw   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts = explode( ',', $raw );
			$ip    = trim( $parts[0] );
		} else {
			$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Render the token-based accept/decline confirmation page.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $commission_id Commission post ID.
	 * @param string $action        'accept' or 'decline'.
	 * @param string $token         Quote token.
	 */
	private static function render_token_page( int $commission_id, string $action, string $token ): void {
		$customer    = get_post_meta( $commission_id, '_wcat_customer_name', true );
		$craft_type  = get_post_meta( $commission_id, '_wcat_craft_type', true );
		$price       = (float) get_post_meta( $commission_id, '_wcat_quoted_price', true );
		$est_date    = get_post_meta( $commission_id, '_wcat_estimated_date', true );
		$maker_note  = get_post_meta( $commission_id, '_wcat_maker_note', true );
		$site_name   = get_bloginfo( 'name' );

		// Render a minimal, styled page.
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>
				<?php echo 'accept' === $action
					? esc_html__( 'Accept Quote', 'wc-artisan-tools' )
					: esc_html__( 'Decline Quote', 'wc-artisan-tools' ); ?>
				— <?php echo esc_html( $site_name ); ?>
			</title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 600px; margin: 60px auto; padding: 0 20px; color: #1d2327; }
				.wcat-quote-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 32px; }
				.wcat-quote-card h1 { margin: 0 0 8px; font-size: 24px; }
				.wcat-quote-card p { color: #50575e; line-height: 1.6; }
				.wcat-quote-detail { margin: 24px 0; padding: 16px; background: #f6f7f7; border-radius: 4px; }
				.wcat-quote-detail dt { font-weight: 600; margin-top: 12px; }
				.wcat-quote-detail dt:first-child { margin-top: 0; }
				.wcat-quote-detail dd { margin: 4px 0 0 0; }
				.wcat-quote-price { font-size: 28px; font-weight: 700; color: #1d2327; }
				.wcat-actions { margin-top: 24px; display: flex; gap: 12px; }
				.wcat-btn { display: inline-block; padding: 12px 24px; border-radius: 4px; font-size: 14px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
				.wcat-btn--primary { background: #2271b1; color: #fff; }
				.wcat-btn--primary:hover { background: #135e96; }
				.wcat-btn--secondary { background: #f6f7f7; color: #50575e; border: 1px solid #dcdcde; }
				.wcat-btn--secondary:hover { background: #f0f0f1; }
				.wcat-btn--danger { background: #d63638; color: #fff; }
				.wcat-btn--danger:hover { background: #b32d2e; }
			</style>
		</head>
		<body>
			<div class="wcat-quote-card">
				<h1>
					<?php echo 'accept' === $action
						? esc_html__( 'Accept Your Quote', 'wc-artisan-tools' )
						: esc_html__( 'Decline Your Quote', 'wc-artisan-tools' ); ?>
				</h1>
				<p>
					<?php printf(
						/* translators: %s: site name */
						esc_html__( 'Hi %1$s, here are the details of your custom %2$s quote from %3$s:', 'wc-artisan-tools' ),
						esc_html( $customer ),
						esc_html( $craft_type ?: __( 'piece', 'wc-artisan-tools' ) ),
						esc_html( $site_name )
					); ?>
				</p>

				<div class="wcat-quote-detail">
					<dl>
						<dt><?php esc_html_e( 'Price', 'wc-artisan-tools' ); ?></dt>
						<dd class="wcat-quote-price"><?php echo wp_kses_post( wc_price( $price ) ); ?></dd>

						<?php if ( $est_date ) : ?>
							<dt><?php esc_html_e( 'Estimated Completion', 'wc-artisan-tools' ); ?></dt>
							<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $est_date ) ) ); ?></dd>
						<?php endif; ?>

						<?php if ( $maker_note ) : ?>
							<dt><?php esc_html_e( 'Note from Maker', 'wc-artisan-tools' ); ?></dt>
							<dd><?php echo esc_html( $maker_note ); ?></dd>
						<?php endif; ?>
					</dl>
				</div>

				<form method="post" class="wcat-actions">
					<?php wp_nonce_field( 'wcat_token_action_' . $token, 'wcat_token_nonce' ); ?>

					<?php if ( 'accept' === $action ) : ?>
						<button type="submit" class="wcat-btn wcat-btn--primary">
							<?php esc_html_e( 'Accept & Proceed to Payment', 'wc-artisan-tools' ); ?>
						</button>
						<a href="<?php echo esc_url( add_query_arg( [ 'wcat_action' => 'decline', 'token' => $token ] ) ); ?>"
						   class="wcat-btn wcat-btn--secondary">
							<?php esc_html_e( 'Decline Instead', 'wc-artisan-tools' ); ?>
						</a>
					<?php else : ?>
						<button type="submit" class="wcat-btn wcat-btn--danger">
							<?php esc_html_e( 'Decline Quote', 'wc-artisan-tools' ); ?>
						</button>
						<a href="<?php echo esc_url( add_query_arg( [ 'wcat_action' => 'accept', 'token' => $token ] ) ); ?>"
						   class="wcat-btn wcat-btn--primary">
							<?php esc_html_e( 'Accept Instead', 'wc-artisan-tools' ); ?>
						</a>
					<?php endif; ?>
				</form>
			</div>
		</body>
		</html>
		<?php
	}
}
