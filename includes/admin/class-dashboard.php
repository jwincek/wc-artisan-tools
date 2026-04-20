<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Admin;

use WC_Artisan_Tools\Core\Config;
use WC_Artisan_Tools\WooCommerce\Product_Manager;

/**
 * Simplified product dashboard for artisans.
 *
 * Provides a clean grid view of products and a streamlined add/edit form
 * with only the fields artisans need.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Dashboard {

	/**
	 * Initialise dashboard hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_init', [ self::class, 'handle_actions' ] );
	}

	/**
	 * Enqueue admin assets on dashboard pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		$pages = [
			'toplevel_page_wcat-dashboard',
			'my-crafts_page_wcat-add-product',
		];

		if ( ! in_array( $hook, $pages, true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'wcat-admin-dashboard',
			WCAT_URL . 'assets/css/admin-dashboard.css',
			[],
			WCAT_VERSION
		);

		wp_enqueue_script(
			'wcat-admin-dashboard',
			WCAT_URL . 'assets/js/admin-dashboard.js',
			[ 'jquery', 'wp-util' ],
			WCAT_VERSION,
			true
		);

		wp_localize_script( 'wcat-admin-dashboard', 'wcatDashboard', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wcat_dashboard' ),
		] );
	}

	/**
	 * Handle dashboard quick actions (mark sold, delete).
	 *
	 * @since 1.0.0
	 */
	public static function handle_actions(): void {
		if ( ! isset( $_GET['wcat_action'] ) ) {
			return;
		}

		$action     = sanitize_text_field( wp_unslash( $_GET['wcat_action'] ) );
		$product_id = absint( $_GET['product_id'] ?? 0 );

		if ( ! $product_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'wcat_product_action_' . $product_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-artisan-tools' ) );
		}

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-artisan-tools' ) );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_die( esc_html__( 'Product not found.', 'wc-artisan-tools' ) );
		}

		switch ( $action ) {
			case 'mark_sold':
				$product->set_stock_status( 'outofstock' );
				$product->save();
				$redirect_msg = 'marked_sold';
				break;

			case 'mark_available':
				$product->set_stock_status( 'instock' );
				$product->save();
				$redirect_msg = 'marked_available';
				break;

			case 'delete':
				wp_trash_post( $product_id );
				$redirect_msg = 'deleted';
				break;

			default:
				wp_die( esc_html__( 'Unknown action.', 'wc-artisan-tools' ) );
		}

		wp_safe_redirect( add_query_arg( [
			'page' => 'wcat-dashboard',
			'msg'  => $redirect_msg,
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the product grid page.
	 *
	 * @since 1.0.0
	 */
	public static function render_page(): void {
		$current_filter = sanitize_text_field( wp_unslash( $_GET['filter_type'] ?? '' ) );
		$current_status = sanitize_text_field( wp_unslash( $_GET['filter_status'] ?? '' ) );
		$current_page   = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page       = 20;

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		// Filter by product type taxonomy.
		if ( $current_filter ) {
			$args['tax_query'] = [ [
				'taxonomy' => 'wcat_product_type',
				'field'    => 'slug',
				'terms'    => $current_filter,
			] ];
		}

		// Filter by stock status.
		if ( 'sold' === $current_status ) {
			$args['meta_query'] = [ [
				'key'   => '_stock_status',
				'value' => 'outofstock',
			] ];
		} elseif ( 'available' === $current_status ) {
			$args['meta_query'] = [ [
				'key'     => '_stock_status',
				'value'   => 'outofstock',
				'compare' => '!=',
			] ];
		}

		// Exclude commission products by default.
		if ( ! isset( $_GET['show_commissions'] ) ) {
			$args['tax_query'] = $args['tax_query'] ?? [];
			$args['tax_query'][] = [
				'taxonomy' => 'wcat_product_origin',
				'field'    => 'slug',
				'terms'    => 'commission',
				'operator' => 'NOT IN',
			];
		}

		$query    = new \WP_Query( $args );
		$products = $query->posts;
		$total    = (int) $query->found_posts;
		$pages    = (int) $query->max_num_pages;

		// Get product types for filter dropdown.
		$product_types = get_terms( [
			'taxonomy'   => 'wcat_product_type',
			'hide_empty' => false,
		] );

		$msg = sanitize_text_field( wp_unslash( $_GET['msg'] ?? '' ) );

		?>
		<div class="wrap wcat-dashboard">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'My Products', 'wc-artisan-tools' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-add-product' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'wc-artisan-tools' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php self::render_notice( $msg ); ?>

			<!-- Filters -->
			<div class="wcat-filters">
				<form method="get">
					<input type="hidden" name="page" value="wcat-dashboard">

					<select name="filter_type">
						<option value=""><?php esc_html_e( 'All Types', 'wc-artisan-tools' ); ?></option>
						<?php if ( ! is_wp_error( $product_types ) ) : ?>
							<?php foreach ( $product_types as $type ) : ?>
								<option value="<?php echo esc_attr( $type->slug ); ?>"
									<?php selected( $current_filter, $type->slug ); ?>>
									<?php echo esc_html( $type->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>

					<select name="filter_status">
						<option value=""><?php esc_html_e( 'All Statuses', 'wc-artisan-tools' ); ?></option>
						<option value="available" <?php selected( $current_status, 'available' ); ?>>
							<?php esc_html_e( 'Available', 'wc-artisan-tools' ); ?>
						</option>
						<option value="sold" <?php selected( $current_status, 'sold' ); ?>>
							<?php esc_html_e( 'Sold', 'wc-artisan-tools' ); ?>
						</option>
					</select>

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wc-artisan-tools' ); ?></button>
				</form>

				<span class="wcat-count">
					<?php
					printf(
						/* translators: %d: product count */
						esc_html( _n( '%d product', '%d products', $total, 'wc-artisan-tools' ) ),
						$total
					);
					?>
				</span>
			</div>

			<!-- Product Grid -->
			<?php if ( empty( $products ) ) : ?>
				<div class="wcat-empty">
					<p><?php esc_html_e( 'No products found. Add your first piece!', 'wc-artisan-tools' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-add-product' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Add Product', 'wc-artisan-tools' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="wcat-product-grid">
					<?php foreach ( $products as $post ) : ?>
						<?php
						$product     = wc_get_product( $post->ID );
						$thumb       = get_the_post_thumbnail_url( $post->ID, 'medium' );
						$price       = $product ? $product->get_price() : '';
						$stock       = $product ? $product->get_stock_status() : '';
						$is_sold     = 'outofstock' === $stock;
						$type_terms  = get_the_terms( $post->ID, 'wcat_product_type' );
						$type_label  = ( $type_terms && ! is_wp_error( $type_terms ) ) ? $type_terms[0]->name : '';
						$edit_url    = admin_url( 'admin.php?page=wcat-add-product&product_id=' . $post->ID );
						$action_base = wp_nonce_url(
							admin_url( 'admin.php?page=wcat-dashboard&product_id=' . $post->ID ),
							'wcat_product_action_' . $post->ID
						);
						?>
						<div class="wcat-product-card <?php echo $is_sold ? 'wcat-product-card--sold' : ''; ?>">
							<div class="wcat-product-card__image">
								<?php if ( $thumb ) : ?>
									<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $post->post_title ); ?>">
								<?php else : ?>
									<div class="wcat-product-card__placeholder">
										<span class="dashicons dashicons-format-image"></span>
									</div>
								<?php endif; ?>

								<?php if ( $is_sold ) : ?>
									<span class="wcat-badge wcat-badge--sold"><?php esc_html_e( 'Sold', 'wc-artisan-tools' ); ?></span>
								<?php endif; ?>
							</div>

							<div class="wcat-product-card__body">
								<h3 class="wcat-product-card__title"><?php echo esc_html( $post->post_title ); ?></h3>

								<div class="wcat-product-card__meta">
									<?php if ( $type_label ) : ?>
										<span class="wcat-product-card__type"><?php echo esc_html( $type_label ); ?></span>
									<?php endif; ?>
									<?php if ( $price ) : ?>
										<span class="wcat-product-card__price"><?php echo wp_kses_post( wc_price( (float) $price ) ); ?></span>
									<?php endif; ?>
								</div>
							</div>

							<div class="wcat-product-card__actions">
								<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
									<?php esc_html_e( 'Edit', 'wc-artisan-tools' ); ?>
								</a>

								<?php if ( $is_sold ) : ?>
									<a href="<?php echo esc_url( $action_base . '&wcat_action=mark_available' ); ?>" class="button button-small">
										<?php esc_html_e( 'Mark Available', 'wc-artisan-tools' ); ?>
									</a>
								<?php else : ?>
									<a href="<?php echo esc_url( $action_base . '&wcat_action=mark_sold' ); ?>" class="button button-small">
										<?php esc_html_e( 'Mark Sold', 'wc-artisan-tools' ); ?>
									</a>
								<?php endif; ?>

								<a href="<?php echo esc_url( $action_base . '&wcat_action=delete' ); ?>"
								   class="button button-small wcat-button--danger"
								   onclick="return confirm('<?php echo esc_js( __( 'Move this product to trash?', 'wc-artisan-tools' ) ); ?>');">
									<?php esc_html_e( 'Delete', 'wc-artisan-tools' ); ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $pages > 1 ) : ?>
					<div class="wcat-pagination">
						<?php
						echo paginate_links( [
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $current_page,
							'total'   => $pages,
						] );
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the add/edit product form.
	 *
	 * @since 1.0.0
	 */
	public static function render_add_page(): void {
		$product_id = absint( $_GET['product_id'] ?? 0 );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		$is_edit    = (bool) $product;

		// Handle form submission.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['wcat_product_nonce'] ) ) {
			if ( ! wp_verify_nonce( $_POST['wcat_product_nonce'], 'wcat_save_product' ) ) {
				wp_die( esc_html__( 'Invalid nonce.', 'wc-artisan-tools' ) );
			}

			if ( ! current_user_can( 'edit_products' ) ) {
				wp_die( esc_html__( 'Permission denied.', 'wc-artisan-tools' ) );
			}

			$saved_id = Product_Manager::save_from_form( $_POST, $product_id );

			if ( is_wp_error( $saved_id ) ) {
				$error_msg = $saved_id->get_error_message();
			} else {
				wp_safe_redirect( add_query_arg( [
					'page' => 'wcat-dashboard',
					'msg'  => $is_edit ? 'updated' : 'created',
				], admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		// Load current values for edit.
		$title       = $product ? $product->get_name() : '';
		$price       = $product ? $product->get_regular_price() : '';
		$description = $product ? $product->get_short_description() : '';
		$stock       = $product ? $product->get_stock_status() : 'instock';
		$featured    = $product ? $product->get_featured() : false;
		$thumb_id    = $product ? $product->get_image_id() : 0;
		$thumb_url   = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';

		// Current taxonomy terms.
		$current_type      = $product_id ? wp_get_post_terms( $product_id, 'wcat_product_type', [ 'fields' => 'slugs' ] ) : [];
		$current_material  = $product_id ? wp_get_post_terms( $product_id, 'wcat_material', [ 'fields' => 'slugs' ] ) : [];
		$current_finish    = $product_id ? wp_get_post_terms( $product_id, 'wcat_finish', [ 'fields' => 'slugs' ] ) : [];
		$current_component = $product_id ? wp_get_post_terms( $product_id, 'wcat_component', [ 'fields' => 'slugs' ] ) : [];

		$current_type      = is_wp_error( $current_type ) ? [] : $current_type;
		$current_material  = is_wp_error( $current_material ) ? [] : $current_material;
		$current_finish    = is_wp_error( $current_finish ) ? [] : $current_finish;
		$current_component = is_wp_error( $current_component ) ? [] : $current_component;

		// Get taxonomy terms for dropdowns.
		$product_types = get_terms( [ 'taxonomy' => 'wcat_product_type', 'hide_empty' => false ] );
		$materials     = get_terms( [ 'taxonomy' => 'wcat_material', 'hide_empty' => false ] );
		$finishes      = get_terms( [ 'taxonomy' => 'wcat_finish', 'hide_empty' => false ] );
		$components    = get_terms( [ 'taxonomy' => 'wcat_component', 'hide_empty' => false ] );

		// Craft profile for labels.
		$craft  = Config::get_active_craft_profile();
		$labels = $craft['taxonomy_labels'] ?? [];

		$material_label  = $labels['wcat_material']['singular'] ?? __( 'Material', 'wc-artisan-tools' );
		$component_label = $labels['wcat_component']['singular'] ?? __( 'Component', 'wc-artisan-tools' );

		?>
		<div class="wrap wcat-dashboard">
			<h1>
				<?php echo $is_edit
					? esc_html__( 'Edit Product', 'wc-artisan-tools' )
					: esc_html__( 'Add New Product', 'wc-artisan-tools' ); ?>
			</h1>

			<?php if ( ! empty( $error_msg ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
			<?php endif; ?>

			<form method="post" class="wcat-product-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wcat_save_product', 'wcat_product_nonce' ); ?>

				<!-- Name -->
				<div class="wcat-field">
					<label for="wcat-title"><?php esc_html_e( 'Name', 'wc-artisan-tools' ); ?></label>
					<input type="text" id="wcat-title" name="title" value="<?php echo esc_attr( $title ); ?>"
						   class="regular-text" required
						   data-auto-title="true">
				</div>

				<!-- Price -->
				<div class="wcat-field">
					<label for="wcat-price"><?php esc_html_e( 'Price', 'wc-artisan-tools' ); ?></label>
					<input type="number" id="wcat-price" name="price" value="<?php echo esc_attr( $price ); ?>"
						   class="small-text" min="0" step="0.01" required>
				</div>

				<!-- Product Type -->
				<div class="wcat-field">
					<label for="wcat-product-type"><?php esc_html_e( 'Type', 'wc-artisan-tools' ); ?></label>
					<select id="wcat-product-type" name="product_type" data-title-source="type">
						<option value=""><?php esc_html_e( 'Select type...', 'wc-artisan-tools' ); ?></option>
						<?php if ( ! is_wp_error( $product_types ) ) : ?>
							<?php foreach ( $product_types as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"
									<?php selected( in_array( $term->slug, $current_type, true ) ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>

				<!-- Material -->
				<div class="wcat-field">
					<label for="wcat-material"><?php echo esc_html( $material_label ); ?></label>
					<select id="wcat-material" name="material" data-title-source="material">
						<option value=""><?php esc_html_e( 'Select...', 'wc-artisan-tools' ); ?></option>
						<?php if ( ! is_wp_error( $materials ) ) : ?>
							<?php foreach ( $materials as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"
									<?php selected( in_array( $term->slug, $current_material, true ) ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>

				<!-- Finish -->
				<div class="wcat-field">
					<label for="wcat-finish"><?php esc_html_e( 'Finish', 'wc-artisan-tools' ); ?></label>
					<select id="wcat-finish" name="finish">
						<option value=""><?php esc_html_e( 'Select...', 'wc-artisan-tools' ); ?></option>
						<?php if ( ! is_wp_error( $finishes ) ) : ?>
							<?php foreach ( $finishes as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"
									<?php selected( in_array( $term->slug, $current_finish, true ) ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>

				<!-- Component / Hardware -->
				<?php if ( ! is_wp_error( $components ) && ! empty( $components ) ) : ?>
					<div class="wcat-field">
						<label for="wcat-component"><?php echo esc_html( $component_label ); ?></label>
						<select id="wcat-component" name="component">
							<option value=""><?php esc_html_e( 'Select...', 'wc-artisan-tools' ); ?></option>
							<?php foreach ( $components as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"
									<?php selected( in_array( $term->slug, $current_component, true ) ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<!-- Description -->
				<div class="wcat-field">
					<label for="wcat-description"><?php esc_html_e( 'Description', 'wc-artisan-tools' ); ?></label>
					<textarea id="wcat-description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
				</div>

				<!-- Featured Image -->
				<div class="wcat-field">
					<label><?php esc_html_e( 'Photo', 'wc-artisan-tools' ); ?></label>
					<div class="wcat-image-upload" id="wcat-image-upload">
						<input type="hidden" name="featured_image" id="wcat-featured-image" value="<?php echo esc_attr( $thumb_id ); ?>">
						<div class="wcat-image-preview" id="wcat-image-preview"
							 <?php echo $thumb_url ? '' : 'style="display:none"'; ?>>
							<?php if ( $thumb_url ) : ?>
								<img src="<?php echo esc_url( $thumb_url ); ?>" alt="">
							<?php endif; ?>
						</div>
						<button type="button" class="button" id="wcat-upload-btn">
							<?php echo $thumb_url
								? esc_html__( 'Change Photo', 'wc-artisan-tools' )
								: esc_html__( 'Upload Photo', 'wc-artisan-tools' ); ?>
						</button>
						<?php if ( $thumb_url ) : ?>
							<button type="button" class="button wcat-button--danger" id="wcat-remove-image-btn">
								<?php esc_html_e( 'Remove', 'wc-artisan-tools' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>

				<!-- Stock Status -->
				<div class="wcat-field wcat-field--inline">
					<label>
						<input type="checkbox" name="is_sold" value="1"
							<?php checked( 'outofstock', $stock ); ?>>
						<?php esc_html_e( 'Mark as Sold', 'wc-artisan-tools' ); ?>
					</label>
				</div>

				<!-- Featured -->
				<div class="wcat-field wcat-field--inline">
					<label>
						<input type="checkbox" name="is_featured" value="1"
							<?php checked( $featured ); ?>>
						<?php esc_html_e( 'Featured product', 'wc-artisan-tools' ); ?>
					</label>
				</div>

				<div class="wcat-form-actions">
					<button type="submit" class="button button-primary button-large">
						<?php echo $is_edit
							? esc_html__( 'Update Product', 'wc-artisan-tools' )
							: esc_html__( 'Add Product', 'wc-artisan-tools' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-dashboard' ) ); ?>" class="button button-large">
						<?php esc_html_e( 'Cancel', 'wc-artisan-tools' ); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render admin notice from action result.
	 *
	 * @since 1.0.0
	 *
	 * @param string $msg Message key.
	 */
	private static function render_notice( string $msg ): void {
		$messages = [
			'created'          => __( 'Product created.', 'wc-artisan-tools' ),
			'updated'          => __( 'Product updated.', 'wc-artisan-tools' ),
			'deleted'          => __( 'Product moved to trash.', 'wc-artisan-tools' ),
			'marked_sold'      => __( 'Product marked as sold.', 'wc-artisan-tools' ),
			'marked_available' => __( 'Product marked as available.', 'wc-artisan-tools' ),
		];

		if ( isset( $messages[ $msg ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $messages[ $msg ] )
			);
		}
	}
}
