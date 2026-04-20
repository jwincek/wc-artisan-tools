<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Admin;

use WC_Artisan_Tools\Core\Config;
use WC_Artisan_Tools\Core\Entity_Hydrator;
use WC_Artisan_Tools\Commission\Commission_Handler;

/**
 * Admin interface for managing commissions — list view and quote sending.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Commission_Admin {

	/**
	 * Initialise commission admin hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_init', [ self::class, 'handle_actions' ] );
	}

	/**
	 * Enqueue admin assets on commission pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'my-crafts_page_wcat-commissions' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wcat-admin-commission',
			WCAT_URL . 'assets/css/admin-commission.css',
			[],
			WCAT_VERSION
		);
	}

	/**
	 * Handle admin actions (send quote, update status).
	 *
	 * @since 1.0.0
	 */
	public static function handle_actions(): void {
		if ( ! isset( $_POST['wcat_commission_action'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['wcat_commission_nonce'] ?? '', 'wcat_commission_action' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-artisan-tools' ) );
		}

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-artisan-tools' ) );
		}

		$action        = sanitize_text_field( wp_unslash( $_POST['wcat_commission_action'] ) );
		$commission_id = absint( $_POST['commission_id'] ?? 0 );

		if ( ! $commission_id ) {
			wp_die( esc_html__( 'Invalid commission.', 'wc-artisan-tools' ) );
		}

		switch ( $action ) {
			case 'send_quote':
				$price = (float) ( $_POST['quoted_price'] ?? 0 );
				$date  = sanitize_text_field( wp_unslash( $_POST['estimated_date'] ?? '' ) );
				$note  = sanitize_textarea_field( wp_unslash( $_POST['maker_note'] ?? '' ) );

				if ( $price <= 0 ) {
					wp_safe_redirect( add_query_arg( [
						'page'          => 'wcat-commissions',
						'commission_id' => $commission_id,
						'msg'           => 'invalid_price',
					], admin_url( 'admin.php' ) ) );
					exit;
				}

				$result = Commission_Handler::send_quote( $commission_id, $price, $date, $note );
				$msg    = is_wp_error( $result ) ? 'quote_error' : 'quote_sent';
				break;

			case 'mark_in_progress':
				update_post_meta( $commission_id, '_wcat_status', 'in_progress' );
				delete_transient( 'wcat_pending_count' );
				$msg = 'status_updated';
				break;

			case 'mark_complete':
				Commission_Handler::mark_complete( $commission_id );
				$msg = 'marked_complete';
				break;

			case 'archive':
				update_post_meta( $commission_id, '_wcat_status', 'archived' );
				delete_transient( 'wcat_pending_count' );
				$msg = 'archived';
				break;

			default:
				wp_die( esc_html__( 'Unknown action.', 'wc-artisan-tools' ) );
		}

		wp_safe_redirect( add_query_arg( [
			'page' => 'wcat-commissions',
			'msg'  => $msg,
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render commissions page — list or detail view.
	 *
	 * @since 1.0.0
	 */
	public static function render_page(): void {
		$commission_id = absint( $_GET['commission_id'] ?? 0 );

		if ( $commission_id ) {
			self::render_detail( $commission_id );
			return;
		}

		self::render_list();
	}

	/**
	 * Render commission list view.
	 *
	 * @since 1.0.0
	 */
	private static function render_list(): void {
		$filter_status = sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) );
		$current_page  = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page      = 20;

		$args = [
			'post_type'      => 'wcat_commission',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( $filter_status ) {
			$args['meta_query'] = [ [
				'key'   => '_wcat_status',
				'value' => $filter_status,
			] ];
		} else {
			// Hide archived by default.
			$args['meta_query'] = [ [
				'key'     => '_wcat_status',
				'value'   => 'archived',
				'compare' => '!=',
			] ];
		}

		$query       = new \WP_Query( $args );
		$commissions = $query->posts;
		$total       = (int) $query->found_posts;
		$pages       = (int) $query->max_num_pages;

		$statuses = Config::get_item( 'settings', 'commission_statuses', [] );
		$msg      = sanitize_text_field( wp_unslash( $_GET['msg'] ?? '' ) );

		?>
		<div class="wrap wcat-dashboard">
			<h1><?php esc_html_e( 'Commissions', 'wc-artisan-tools' ); ?></h1>

			<?php self::render_notice( $msg ); ?>

			<!-- Status Filter -->
			<div class="wcat-filters">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-commissions' ) ); ?>"
				   class="<?php echo empty( $filter_status ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'Active', 'wc-artisan-tools' ); ?>
				</a>
				<?php foreach ( $statuses as $status_def ) : ?>
					| <a href="<?php echo esc_url( add_query_arg( 'status', $status_def['value'], admin_url( 'admin.php?page=wcat-commissions' ) ) ); ?>"
					     class="<?php echo $filter_status === $status_def['value'] ? 'current' : ''; ?>">
						<?php echo esc_html( $status_def['label'] ); ?>
					</a>
				<?php endforeach; ?>

				<span class="wcat-count">
					<?php printf( esc_html( _n( '%d commission', '%d commissions', $total, 'wc-artisan-tools' ) ), $total ); ?>
				</span>
			</div>

			<?php if ( empty( $commissions ) ) : ?>
				<div class="wcat-empty">
					<p><?php esc_html_e( 'No commissions found.', 'wc-artisan-tools' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped wcat-commission-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Customer', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Request', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Budget', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Date', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wc-artisan-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $commissions as $post ) :
							$status       = get_post_meta( $post->ID, '_wcat_status', true ) ?: 'new';
							$customer     = get_post_meta( $post->ID, '_wcat_customer_name', true );
							$craft_type   = get_post_meta( $post->ID, '_wcat_craft_type', true );
							$material     = get_post_meta( $post->ID, '_wcat_material_pref', true );
							$budget       = get_post_meta( $post->ID, '_wcat_budget_range', true );
							$status_def   = self::get_status_def( $status );
							$detail_url   = admin_url( 'admin.php?page=wcat-commissions&commission_id=' . $post->ID );
							?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( $detail_url ); ?>">
											<?php echo esc_html( $customer ); ?>
										</a>
									</strong>
								</td>
								<td>
									<?php echo esc_html( $craft_type ); ?>
									<?php if ( $material ) : ?>
										&mdash; <?php echo esc_html( $material ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $budget ); ?></td>
								<td>
									<span class="wcat-status-badge" style="background:<?php echo esc_attr( $status_def['color'] ?? '#787c82' ); ?>">
										<?php echo esc_html( $status_def['label'] ?? $status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( get_the_date( '', $post ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small">
										<?php esc_html_e( 'View', 'wc-artisan-tools' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $pages > 1 ) : ?>
					<div class="wcat-pagination">
						<?php echo paginate_links( [ 'base' => add_query_arg( 'paged', '%#%' ), 'current' => $current_page, 'total' => $pages ] ); ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render commission detail/quote view.
	 *
	 * @since 1.0.0
	 *
	 * @param int $commission_id Commission post ID.
	 */
	private static function render_detail( int $commission_id ): void {
		$post = get_post( $commission_id );
		if ( ! $post || 'wcat_commission' !== $post->post_type ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Commission not found.', 'wc-artisan-tools' ) . '</p></div>';
			return;
		}

		$status       = get_post_meta( $commission_id, '_wcat_status', true ) ?: 'new';
		$customer     = get_post_meta( $commission_id, '_wcat_customer_name', true );
		$email        = get_post_meta( $commission_id, '_wcat_customer_email', true );
		$craft_type   = get_post_meta( $commission_id, '_wcat_craft_type', true );
		$material     = get_post_meta( $commission_id, '_wcat_material_pref', true );
		$description  = get_post_meta( $commission_id, '_wcat_description', true );
		$budget       = get_post_meta( $commission_id, '_wcat_budget_range', true );
		$deadline     = get_post_meta( $commission_id, '_wcat_deadline', true );
		$display_name = get_post_meta( $commission_id, '_wcat_display_name', true );
		$quoted_price = get_post_meta( $commission_id, '_wcat_quoted_price', true );
		$est_date     = get_post_meta( $commission_id, '_wcat_estimated_date', true );
		$maker_note   = get_post_meta( $commission_id, '_wcat_maker_note', true );
		$status_def   = self::get_status_def( $status );

		$msg = sanitize_text_field( wp_unslash( $_GET['msg'] ?? '' ) );

		?>
		<div class="wrap wcat-dashboard">
			<h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-commissions' ) ); ?>">
					&larr; <?php esc_html_e( 'Commissions', 'wc-artisan-tools' ); ?>
				</a>
			</h1>

			<?php self::render_notice( $msg ); ?>

			<div class="wcat-commission-detail">
				<!-- Header -->
				<div class="wcat-commission-header">
					<h2>
						<?php echo esc_html( $customer ); ?>
						<span class="wcat-status-badge" style="background:<?php echo esc_attr( $status_def['color'] ?? '#787c82' ); ?>">
							<?php echo esc_html( $status_def['label'] ?? $status ); ?>
						</span>
					</h2>
					<p class="wcat-commission-date">
						<?php
						printf(
							/* translators: %s: date */
							esc_html__( 'Submitted %s', 'wc-artisan-tools' ),
							esc_html( get_the_date( '', $post ) )
						);
						?>
					</p>
				</div>

				<!-- Request Details -->
				<div class="wcat-commission-section">
					<h3><?php esc_html_e( 'Request Details', 'wc-artisan-tools' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Email', 'wc-artisan-tools' ); ?></th>
							<td><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Type', 'wc-artisan-tools' ); ?></th>
							<td><?php echo esc_html( $craft_type ); ?></td>
						</tr>
						<?php if ( $material ) : ?>
							<tr>
								<th><?php esc_html_e( 'Material Preference', 'wc-artisan-tools' ); ?></th>
								<td><?php echo esc_html( $material ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'Description', 'wc-artisan-tools' ); ?></th>
							<td><?php echo esc_html( $description ); ?></td>
						</tr>
						<?php if ( $budget ) : ?>
							<tr>
								<th><?php esc_html_e( 'Budget Range', 'wc-artisan-tools' ); ?></th>
								<td><?php echo esc_html( $budget ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( $deadline ) : ?>
							<tr>
								<th><?php esc_html_e( 'Occasion / Deadline', 'wc-artisan-tools' ); ?></th>
								<td><?php echo esc_html( $deadline ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'Display Name on Listing', 'wc-artisan-tools' ); ?></th>
							<td><?php echo $display_name ? esc_html__( 'Yes', 'wc-artisan-tools' ) : esc_html__( 'No', 'wc-artisan-tools' ); ?></td>
						</tr>
					</table>
				</div>

				<!-- Quote Form (only for new commissions) -->
				<?php if ( 'new' === $status ) : ?>
					<div class="wcat-commission-section">
						<h3><?php esc_html_e( 'Send Quote', 'wc-artisan-tools' ); ?></h3>
						<form method="post">
							<?php wp_nonce_field( 'wcat_commission_action', 'wcat_commission_nonce' ); ?>
							<input type="hidden" name="wcat_commission_action" value="send_quote">
							<input type="hidden" name="commission_id" value="<?php echo esc_attr( (string) $commission_id ); ?>">

							<table class="form-table">
								<tr>
									<th><label for="wcat-quoted-price"><?php esc_html_e( 'Price', 'wc-artisan-tools' ); ?></label></th>
									<td>
										<input type="number" id="wcat-quoted-price" name="quoted_price"
											   min="0" step="0.01" class="small-text" required
											   value="<?php echo esc_attr( $quoted_price ); ?>">
									</td>
								</tr>
								<tr>
									<th><label for="wcat-est-date"><?php esc_html_e( 'Estimated Completion', 'wc-artisan-tools' ); ?></label></th>
									<td>
										<input type="date" id="wcat-est-date" name="estimated_date"
											   value="<?php echo esc_attr( $est_date ); ?>">
									</td>
								</tr>
								<tr>
									<th><label for="wcat-maker-note"><?php esc_html_e( 'Note to Customer', 'wc-artisan-tools' ); ?></label></th>
									<td>
										<textarea id="wcat-maker-note" name="maker_note" rows="4" class="large-text"><?php echo esc_textarea( $maker_note ); ?></textarea>
									</td>
								</tr>
							</table>

							<p class="submit">
								<button type="submit" class="button button-primary">
									<?php esc_html_e( 'Send Quote', 'wc-artisan-tools' ); ?>
								</button>
							</p>
						</form>
					</div>
				<?php endif; ?>

				<!-- Quoted Info (for already-quoted commissions) -->
				<?php if ( in_array( $status, [ 'quoted', 'accepted', 'in_progress', 'complete' ], true ) && $quoted_price ) : ?>
					<div class="wcat-commission-section">
						<h3><?php esc_html_e( 'Quote Details', 'wc-artisan-tools' ); ?></h3>
						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Quoted Price', 'wc-artisan-tools' ); ?></th>
								<td><?php echo wp_kses_post( wc_price( (float) $quoted_price ) ); ?></td>
							</tr>
							<?php if ( $est_date ) : ?>
								<tr>
									<th><?php esc_html_e( 'Estimated Completion', 'wc-artisan-tools' ); ?></th>
									<td><?php echo esc_html( $est_date ); ?></td>
								</tr>
							<?php endif; ?>
							<?php if ( $maker_note ) : ?>
								<tr>
									<th><?php esc_html_e( 'Note to Customer', 'wc-artisan-tools' ); ?></th>
									<td><?php echo esc_html( $maker_note ); ?></td>
								</tr>
							<?php endif; ?>
						</table>
					</div>
				<?php endif; ?>

				<!-- Status Actions -->
				<div class="wcat-commission-section">
					<h3><?php esc_html_e( 'Actions', 'wc-artisan-tools' ); ?></h3>
					<form method="post" class="wcat-status-actions">
						<?php wp_nonce_field( 'wcat_commission_action', 'wcat_commission_nonce' ); ?>
						<input type="hidden" name="commission_id" value="<?php echo esc_attr( (string) $commission_id ); ?>">

						<?php if ( 'accepted' === $status ) : ?>
							<button type="submit" name="wcat_commission_action" value="mark_in_progress" class="button button-primary">
								<?php esc_html_e( 'Mark In Progress', 'wc-artisan-tools' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( 'in_progress' === $status ) : ?>
							<button type="submit" name="wcat_commission_action" value="mark_complete" class="button button-primary">
								<?php esc_html_e( 'Mark Complete', 'wc-artisan-tools' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( in_array( $status, [ 'complete', 'declined' ], true ) ) : ?>
							<button type="submit" name="wcat_commission_action" value="archive" class="button">
								<?php esc_html_e( 'Archive', 'wc-artisan-tools' ); ?>
							</button>
						<?php endif; ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get status definition from settings config.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Status value.
	 * @return array Status definition with label and color.
	 */
	private static function get_status_def( string $status ): array {
		$statuses = Config::get_item( 'settings', 'commission_statuses', [] );

		foreach ( $statuses as $def ) {
			if ( ( $def['value'] ?? '' ) === $status ) {
				return $def;
			}
		}

		return [ 'label' => ucfirst( $status ), 'color' => '#787c82' ];
	}

	/**
	 * Render admin notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $msg Message key.
	 */
	private static function render_notice( string $msg ): void {
		$messages = [
			'quote_sent'     => __( 'Quote sent to customer.', 'wc-artisan-tools' ),
			'quote_error'    => __( 'Failed to send quote.', 'wc-artisan-tools' ),
			'invalid_price'  => __( 'Please enter a valid price.', 'wc-artisan-tools' ),
			'status_updated' => __( 'Commission status updated.', 'wc-artisan-tools' ),
			'marked_complete'=> __( 'Commission marked as complete. Product is now visible in your shop.', 'wc-artisan-tools' ),
			'archived'       => __( 'Commission archived.', 'wc-artisan-tools' ),
		];

		if ( isset( $messages[ $msg ] ) ) {
			$type = in_array( $msg, [ 'quote_error', 'invalid_price' ], true ) ? 'error' : 'success';
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				esc_html( $messages[ $msg ] )
			);
		}
	}
}
