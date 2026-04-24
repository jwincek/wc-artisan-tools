<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Admin;

use WC_Artisan_Tools\Core\Config;

/**
 * Product migration tool — maps existing WooCommerce product categories
 * and attributes to the plugin's taxonomy system.
 *
 * Provides a two-step workflow:
 *   1. Scan & Preview: Analyse existing products, show proposed mapping
 *   2. Execute: Apply the mapping, create terms, assign to products
 *
 * Designed to be reusable across artisan sites, not River Writers-specific.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Migration {

	/**
	 * Plugin taxonomies available as migration targets.
	 *
	 * @var string[]
	 */
	private const TARGET_TAXONOMIES = [
		'wcat_product_type',
		'wcat_material',
		'wcat_finish',
		'wcat_component',
	];

	/**
	 * Initialise migration hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_init', [ self::class, 'handle_migration' ] );
	}

	/**
	 * Enqueue assets on migration page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'my-crafts_page_wcat-migration' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wcat-admin-migration',
			WCAT_URL . 'assets/css/admin-migration.css',
			[],
			WCAT_VERSION
		);
	}

	/**
	 * Render the migration page.
	 *
	 * @since 1.0.0
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$msg = sanitize_text_field( wp_unslash( $_GET['msg'] ?? '' ) );

		?>
		<div class="wrap wcat-dashboard">
			<h1><?php esc_html_e( 'Product Migration', 'wc-artisan-tools' ); ?></h1>
			<p class="wcat-migration__intro">
				<?php esc_html_e( 'Migrate existing WooCommerce product categories and attributes to the Artisan Tools taxonomy system. This lets your products work with the simplified dashboard and filtering.', 'wc-artisan-tools' ); ?>
			</p>

			<?php self::render_notice( $msg ); ?>

			<?php
			$scan = self::scan_products();

			if ( empty( $scan['products'] ) ) {
				echo '<div class="wcat-empty"><p>' . esc_html__( 'No WooCommerce products found to migrate.', 'wc-artisan-tools' ) . '</p></div>';
				return;
			}

			self::render_scan_results( $scan );
			?>
		</div>
		<?php
	}

	/**
	 * Scan all WooCommerce products and extract their categories and attributes.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     products: array,
	 *     categories: array<string, int>,
	 *     attributes: array<string, array<string, int>>,
	 *     already_migrated: int
	 * }
	 */
	private static function scan_products(): array {
		$products = wc_get_products( [
			'limit'  => -1,
			'status' => 'publish',
			'return' => 'objects',
		] );

		$categories       = [];
		$attributes       = [];
		$product_data     = [];
		$already_migrated = 0;

		foreach ( $products as $product ) {
			$id = $product->get_id();

			// Check if already has plugin taxonomies.
			$has_type = wp_get_post_terms( $id, 'wcat_product_type', [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $has_type ) && ! empty( $has_type ) ) {
				$already_migrated++;
				continue;
			}

			// Collect WooCommerce categories.
			$cat_ids = $product->get_category_ids();
			$cat_names = [];
			foreach ( $cat_ids as $cat_id ) {
				$term = get_term( $cat_id, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) && 'Uncategorized' !== $term->name ) {
					$cat_names[] = $term->name;
					$categories[ $term->name ] = ( $categories[ $term->name ] ?? 0 ) + 1;
				}
			}

			// Collect product attributes.
			$product_attrs = [];
			foreach ( $product->get_attributes() as $attr ) {
				$attr_name = $attr->get_name();

				// Translate pa_ prefixed attributes to their label.
				if ( str_starts_with( $attr_name, 'pa_' ) ) {
					$taxonomy_obj = get_taxonomy( $attr_name );
					$label = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $attr_name;
				} else {
					$label = $attr_name;
				}

				$values = [];
				if ( $attr->is_taxonomy() ) {
					$terms = wp_get_post_terms( $id, $attr_name, [ 'fields' => 'names' ] );
					if ( ! is_wp_error( $terms ) ) {
						$values = $terms;
					}
				} else {
					$values = array_map( 'trim', explode( '|', $attr->get_options()[0] ?? '' ) );
					if ( count( $attr->get_options() ) > 1 ) {
						$values = $attr->get_options();
					}
				}

				foreach ( $values as $val ) {
					$val = trim( (string) $val );
					if ( '' === $val ) {
						continue;
					}
					$attributes[ $label ][ $val ] = ( $attributes[ $label ][ $val ] ?? 0 ) + 1;
				}

				$product_attrs[ $label ] = $values;
			}

			$product_data[] = [
				'id'         => $id,
				'name'       => $product->get_name(),
				'categories' => $cat_names,
				'attributes' => $product_attrs,
				'price'      => $product->get_price(),
				'in_stock'   => $product->is_in_stock(),
			];
		}

		// Sort attributes by frequency.
		foreach ( $attributes as &$values ) {
			arsort( $values );
		}
		unset( $values );

		// Sort attribute groups by number of products using them.
		uksort( $attributes, function ( $a, $b ) use ( $attributes ) {
			return array_sum( $attributes[ $b ] ) <=> array_sum( $attributes[ $a ] );
		} );

		arsort( $categories );

		return [
			'products'         => $product_data,
			'categories'       => $categories,
			'attributes'       => $attributes,
			'already_migrated' => $already_migrated,
		];
	}

	/**
	 * Render the scan results with mapping form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $scan Scan results from scan_products().
	 */
	private static function render_scan_results( array $scan ): void {
		$craft  = Config::get_active_craft_profile();
		$labels = $craft['taxonomy_labels'] ?? [];

		$taxonomy_labels = [
			'wcat_product_type' => __( 'Product Type', 'wc-artisan-tools' ),
			'wcat_material'     => $labels['wcat_material']['singular'] ?? __( 'Material', 'wc-artisan-tools' ),
			'wcat_finish'       => $labels['wcat_finish']['singular'] ?? __( 'Finish', 'wc-artisan-tools' ),
			'wcat_component'    => $labels['wcat_component']['singular'] ?? __( 'Component', 'wc-artisan-tools' ),
		];

		?>
		<!-- Summary -->
		<div class="wcat-migration__summary">
			<h2><?php esc_html_e( 'Scan Results', 'wc-artisan-tools' ); ?></h2>
			<ul>
				<li>
					<?php printf(
						/* translators: %d: product count */
						esc_html( _n( '%d product to migrate', '%d products to migrate', count( $scan['products'] ), 'wc-artisan-tools' ) ),
						count( $scan['products'] )
					); ?>
				</li>
				<li>
					<?php printf(
						/* translators: %d: category count */
						esc_html( _n( '%d WooCommerce category found', '%d WooCommerce categories found', count( $scan['categories'] ), 'wc-artisan-tools' ) ),
						count( $scan['categories'] )
					); ?>
				</li>
				<li>
					<?php printf(
						/* translators: %d: attribute count */
						esc_html( _n( '%d product attribute found', '%d product attributes found', count( $scan['attributes'] ), 'wc-artisan-tools' ) ),
						count( $scan['attributes'] )
					); ?>
				</li>
				<?php if ( $scan['already_migrated'] > 0 ) : ?>
					<li>
						<?php printf(
							/* translators: %d: count */
							esc_html( _n( '%d product already migrated (skipped)', '%d products already migrated (skipped)', $scan['already_migrated'], 'wc-artisan-tools' ) ),
							$scan['already_migrated']
						); ?>
					</li>
				<?php endif; ?>
			</ul>
		</div>

		<!-- Mapping Form -->
		<form method="post" class="wcat-migration__form">
			<?php wp_nonce_field( 'wcat_run_migration', 'wcat_migration_nonce' ); ?>

			<!-- Category Mapping -->
			<div class="wcat-migration__section">
				<h3><?php esc_html_e( 'Category Mapping', 'wc-artisan-tools' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Map your existing WooCommerce product categories to the Product Type taxonomy.', 'wc-artisan-tools' ); ?>
				</p>

				<?php if ( empty( $scan['categories'] ) ) : ?>
					<p><em><?php esc_html_e( 'No product categories found.', 'wc-artisan-tools' ); ?></em></p>
				<?php else : ?>
					<table class="widefat fixed striped wcat-migration__table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'WooCommerce Category', 'wc-artisan-tools' ); ?></th>
								<th><?php esc_html_e( 'Products', 'wc-artisan-tools' ); ?></th>
								<th><?php esc_html_e( 'Map To', 'wc-artisan-tools' ); ?></th>
								<th><?php esc_html_e( 'New Term Name', 'wc-artisan-tools' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $scan['categories'] as $cat_name => $count ) :
								$safe_key = sanitize_title( $cat_name );
								?>
								<tr>
									<td><strong><?php echo esc_html( $cat_name ); ?></strong></td>
									<td><?php echo esc_html( (string) $count ); ?></td>
									<td>
										<select name="cat_map[<?php echo esc_attr( $safe_key ); ?>][target]">
											<option value="wcat_product_type" selected>
												<?php echo esc_html( $taxonomy_labels['wcat_product_type'] ); ?>
											</option>
											<option value="skip"><?php esc_html_e( 'Skip', 'wc-artisan-tools' ); ?></option>
										</select>
									</td>
									<td>
										<input type="text" name="cat_map[<?php echo esc_attr( $safe_key ); ?>][name]"
											   value="<?php echo esc_attr( $cat_name ); ?>" class="regular-text">
										<input type="hidden" name="cat_map[<?php echo esc_attr( $safe_key ); ?>][source]"
											   value="<?php echo esc_attr( $cat_name ); ?>">
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Attribute Mapping -->
			<div class="wcat-migration__section">
				<h3><?php esc_html_e( 'Attribute Mapping', 'wc-artisan-tools' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Map your existing product attributes to the plugin\'s taxonomies. Attributes mapped to "Skip" will be left as WooCommerce product attributes.', 'wc-artisan-tools' ); ?>
				</p>

				<?php if ( empty( $scan['attributes'] ) ) : ?>
					<p><em><?php esc_html_e( 'No product attributes found.', 'wc-artisan-tools' ); ?></em></p>
				<?php else : ?>
					<?php foreach ( $scan['attributes'] as $attr_name => $values ) :
						$safe_attr = sanitize_title( $attr_name );
						$suggested = self::suggest_target( $attr_name );
						$total     = array_sum( $values );
						?>
						<div class="wcat-migration__attribute-group">
							<div class="wcat-migration__attribute-header">
								<h4>
									<?php echo esc_html( $attr_name ); ?>
									<span class="wcat-migration__count">
										<?php printf(
											/* translators: %d: usage count */
											esc_html( _n( '%d product', '%d products', $total, 'wc-artisan-tools' ) ),
											$total
										); ?>
									</span>
								</h4>
								<div class="wcat-migration__attribute-target">
									<label><?php esc_html_e( 'Map to:', 'wc-artisan-tools' ); ?>
										<select name="attr_map[<?php echo esc_attr( $safe_attr ); ?>][target]">
											<option value="skip"
												<?php selected( 'skip', $suggested ); ?>>
												<?php esc_html_e( 'Skip (keep as attribute)', 'wc-artisan-tools' ); ?>
											</option>
											<?php foreach ( self::TARGET_TAXONOMIES as $tax ) : ?>
												<option value="<?php echo esc_attr( $tax ); ?>"
													<?php selected( $tax, $suggested ); ?>>
													<?php echo esc_html( $taxonomy_labels[ $tax ] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
								</div>
							</div>

							<input type="hidden" name="attr_map[<?php echo esc_attr( $safe_attr ); ?>][source]"
								   value="<?php echo esc_attr( $attr_name ); ?>">

							<table class="widefat fixed striped wcat-migration__values-table">
								<thead>
									<tr>
										<th class="check-column">
											<input type="checkbox" checked
												   data-toggle-group="<?php echo esc_attr( $safe_attr ); ?>">
										</th>
										<th><?php esc_html_e( 'Value', 'wc-artisan-tools' ); ?></th>
										<th><?php esc_html_e( 'Products', 'wc-artisan-tools' ); ?></th>
										<th><?php esc_html_e( 'New Term Name', 'wc-artisan-tools' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $values as $val => $val_count ) :
										$safe_val = sanitize_title( (string) $val );
										?>
										<tr>
											<td class="check-column">
												<input type="checkbox"
													   name="attr_map[<?php echo esc_attr( $safe_attr ); ?>][values][<?php echo esc_attr( $safe_val ); ?>][include]"
													   value="1" checked
													   data-group="<?php echo esc_attr( $safe_attr ); ?>">
											</td>
											<td><?php echo esc_html( (string) $val ); ?></td>
											<td><?php echo esc_html( (string) $val_count ); ?></td>
											<td>
												<input type="text"
													   name="attr_map[<?php echo esc_attr( $safe_attr ); ?>][values][<?php echo esc_attr( $safe_val ); ?>][name]"
													   value="<?php echo esc_attr( (string) $val ); ?>"
													   class="regular-text">
												<input type="hidden"
													   name="attr_map[<?php echo esc_attr( $safe_attr ); ?>][values][<?php echo esc_attr( $safe_val ); ?>][source]"
													   value="<?php echo esc_attr( (string) $val ); ?>">
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Product Preview -->
			<div class="wcat-migration__section">
				<h3><?php esc_html_e( 'Products to Migrate', 'wc-artisan-tools' ); ?></h3>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Price', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Category', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Attributes', 'wc-artisan-tools' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wc-artisan-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $scan['products'], 0, 50 ) as $p ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
								<td><?php echo $p['price'] ? wp_kses_post( wc_price( (float) $p['price'] ) ) : '&mdash;'; ?></td>
								<td><?php echo esc_html( implode( ', ', $p['categories'] ) ); ?></td>
								<td>
									<?php
									$attr_parts = [];
									foreach ( $p['attributes'] as $attr_label => $vals ) {
										$attr_parts[] = $attr_label . ': ' . implode( ', ', array_map( 'strval', $vals ) );
									}
									echo esc_html( implode( ' | ', $attr_parts ) );
									?>
								</td>
								<td><?php echo $p['in_stock'] ? esc_html__( 'In Stock', 'wc-artisan-tools' ) : esc_html__( 'Sold', 'wc-artisan-tools' ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( count( $scan['products'] ) > 50 ) : ?>
							<tr>
								<td colspan="5"><em>
									<?php printf(
										/* translators: %d: remaining count */
										esc_html__( '... and %d more products', 'wc-artisan-tools' ),
										count( $scan['products'] ) - 50
									); ?>
								</em></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Actions -->
			<div class="wcat-migration__actions">
				<button type="submit" name="wcat_migration_action" value="run" class="button button-primary button-large">
					<?php printf(
						/* translators: %d: product count */
						esc_html( _n( 'Migrate %d Product', 'Migrate %d Products', count( $scan['products'] ), 'wc-artisan-tools' ) ),
						count( $scan['products'] )
					); ?>
				</button>
				<p class="description">
					<?php esc_html_e( 'This assigns taxonomy terms to your products. It does not modify or delete existing WooCommerce categories or attributes.', 'wc-artisan-tools' ); ?>
				</p>
			</div>
		</form>
		<?php
	}

	/**
	 * Handle migration form submission.
	 *
	 * @since 1.0.0
	 */
	public static function handle_migration(): void {
		if ( ! isset( $_POST['wcat_migration_action'] ) || 'run' !== $_POST['wcat_migration_action'] ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['wcat_migration_nonce'] ?? '', 'wcat_run_migration' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wc-artisan-tools' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-artisan-tools' ) );
		}

		$cat_map  = $_POST['cat_map'] ?? [];
		$attr_map = $_POST['attr_map'] ?? [];

		// Build the mapping tables.
		$category_mapping  = self::build_category_mapping( $cat_map );
		$attribute_mapping = self::build_attribute_mapping( $attr_map );

		// Run migration.
		$results = self::execute_migration( $category_mapping, $attribute_mapping );

		// Store results in transient for display.
		set_transient( 'wcat_migration_results', $results, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( [
			'page' => 'wcat-migration',
			'msg'  => 'migration_complete',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Build category mapping from form data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $cat_map Raw form data.
	 * @return array<string, array{target: string, name: string}> Source name => target config.
	 */
	private static function build_category_mapping( array $cat_map ): array {
		$mapping = [];

		foreach ( $cat_map as $entry ) {
			$source = sanitize_text_field( wp_unslash( $entry['source'] ?? '' ) );
			$target = sanitize_text_field( wp_unslash( $entry['target'] ?? 'skip' ) );
			$name   = sanitize_text_field( wp_unslash( $entry['name'] ?? $source ) );

			if ( 'skip' === $target || empty( $source ) ) {
				continue;
			}

			$mapping[ $source ] = [
				'target' => $target,
				'name'   => $name,
			];
		}

		return $mapping;
	}

	/**
	 * Build attribute mapping from form data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attr_map Raw form data.
	 * @return array<string, array{target: string, values: array}> Source attr name => target config.
	 */
	private static function build_attribute_mapping( array $attr_map ): array {
		$mapping = [];

		foreach ( $attr_map as $entry ) {
			$source = sanitize_text_field( wp_unslash( $entry['source'] ?? '' ) );
			$target = sanitize_text_field( wp_unslash( $entry['target'] ?? 'skip' ) );

			if ( 'skip' === $target || empty( $source ) ) {
				continue;
			}

			$values = [];
			foreach ( ( $entry['values'] ?? [] ) as $val_entry ) {
				if ( empty( $val_entry['include'] ) ) {
					continue;
				}
				$val_source = sanitize_text_field( wp_unslash( $val_entry['source'] ?? '' ) );
				$val_name   = sanitize_text_field( wp_unslash( $val_entry['name'] ?? $val_source ) );

				if ( $val_source ) {
					$values[ $val_source ] = $val_name;
				}
			}

			$mapping[ $source ] = [
				'target' => $target,
				'values' => $values,
			];
		}

		return $mapping;
	}

	/**
	 * Execute the migration across all products.
	 *
	 * @since 1.0.0
	 *
	 * @param array $category_mapping  Category name => target taxonomy + term name.
	 * @param array $attribute_mapping Attribute name => target taxonomy + value mapping.
	 * @return array{migrated: int, terms_created: int, assignments: int, errors: string[]}
	 */
	private static function execute_migration( array $category_mapping, array $attribute_mapping ): array {
		$results = [
			'migrated'      => 0,
			'terms_created' => 0,
			'assignments'   => 0,
			'errors'        => [],
		];

		// Pre-create all mapped terms.
		$term_cache = self::ensure_terms( $category_mapping, $attribute_mapping, $results );

		// Get all publishable products.
		$products = wc_get_products( [
			'limit'  => -1,
			'status' => 'publish',
			'return' => 'objects',
		] );

		foreach ( $products as $product ) {
			$id      = $product->get_id();
			$touched = false;

			// Skip already-migrated products.
			$existing = wp_get_post_terms( $id, 'wcat_product_type', [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
				continue;
			}

			// Map categories.
			foreach ( $product->get_category_ids() as $cat_id ) {
				$term = get_term( $cat_id, 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}

				if ( isset( $category_mapping[ $term->name ] ) ) {
					$map    = $category_mapping[ $term->name ];
					$target = $map['target'];
					$slug   = $term_cache[ $target ][ $map['name'] ] ?? null;

					if ( $slug ) {
						wp_set_object_terms( $id, $slug, $target, true );
						$results['assignments']++;
						$touched = true;
					}
				}
			}

			// Map attributes.
			foreach ( $product->get_attributes() as $attr ) {
				$attr_name = $attr->get_name();

				// Resolve label for matching.
				if ( str_starts_with( $attr_name, 'pa_' ) ) {
					$taxonomy_obj = get_taxonomy( $attr_name );
					$label = $taxonomy_obj ? $taxonomy_obj->labels->singular_name : $attr_name;
				} else {
					$label = $attr_name;
				}

				if ( ! isset( $attribute_mapping[ $label ] ) ) {
					continue;
				}

				$map    = $attribute_mapping[ $label ];
				$target = $map['target'];

				// Get attribute values.
				$values = [];
				if ( $attr->is_taxonomy() ) {
					$terms = wp_get_post_terms( $id, $attr_name, [ 'fields' => 'names' ] );
					if ( ! is_wp_error( $terms ) ) {
						$values = $terms;
					}
				} else {
					$values = $attr->get_options();
				}

				foreach ( $values as $val ) {
					// Handle comma-separated values (e.g., "Black, Chrome").
					$sub_values = array_map( 'trim', explode( ',', (string) $val ) );

					foreach ( $sub_values as $sub_val ) {
						if ( '' === $sub_val ) {
							continue;
						}

						// Check if this value is included in the mapping.
						$mapped_name = $map['values'][ $sub_val ] ?? null;

						if ( ! $mapped_name ) {
							continue;
						}

						$slug = $term_cache[ $target ][ $mapped_name ] ?? null;

						if ( $slug ) {
							wp_set_object_terms( $id, $slug, $target, true );
							$results['assignments']++;
							$touched = true;
						}
					}
				}
			}

			// Set origin to "Shop" for migrated products.
			if ( $touched ) {
				wp_set_object_terms( $id, 'shop', 'wcat_product_origin' );
				$results['migrated']++;
			}
		}

		return $results;
	}

	/**
	 * Ensure all mapped terms exist, creating them if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param array $category_mapping  Category mapping.
	 * @param array $attribute_mapping Attribute mapping.
	 * @param array $results           Results array (modified by reference).
	 * @return array<string, array<string, string>> taxonomy => [name => slug] cache.
	 */
	private static function ensure_terms( array $category_mapping, array $attribute_mapping, array &$results ): array {
		$cache = [];

		// Category terms.
		foreach ( $category_mapping as $map ) {
			$target = $map['target'];
			$name   = $map['name'];

			$cache[ $target ][ $name ] = self::ensure_term( $target, $name, $results );
		}

		// Attribute value terms.
		foreach ( $attribute_mapping as $map ) {
			$target = $map['target'];

			foreach ( $map['values'] as $mapped_name ) {
				$cache[ $target ][ $mapped_name ] = self::ensure_term( $target, $mapped_name, $results );
			}
		}

		// Ensure "Shop" origin term exists.
		self::ensure_term( 'wcat_product_origin', 'Shop', $results );

		return $cache;
	}

	/**
	 * Ensure a single term exists in a taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param string $name     Term name.
	 * @param array  $results  Results array (modified by reference).
	 * @return string Term slug.
	 */
	private static function ensure_term( string $taxonomy, string $name, array &$results ): string {
		$existing = get_term_by( 'name', $name, $taxonomy );

		if ( $existing ) {
			return $existing->slug;
		}

		$created = wp_insert_term( $name, $taxonomy );

		if ( is_wp_error( $created ) ) {
			// Term may exist with a different name but same slug.
			$by_slug = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
			if ( $by_slug ) {
				return $by_slug->slug;
			}

			$results['errors'][] = sprintf( '%s: %s', $name, $created->get_error_message() );
			return sanitize_title( $name );
		}

		$results['terms_created']++;

		$term = get_term( $created['term_id'], $taxonomy );
		return $term ? $term->slug : sanitize_title( $name );
	}

	/**
	 * Suggest which target taxonomy an attribute should map to.
	 *
	 * @since 1.0.0
	 *
	 * @param string $attr_name Attribute name.
	 * @return string Suggested taxonomy slug or 'skip'.
	 */
	private static function suggest_target( string $attr_name ): string {
		$lower = strtolower( $attr_name );

		// Material keywords.
		if ( str_contains( $lower, 'material' ) || str_contains( $lower, 'wood' )
			|| str_contains( $lower, 'metal' ) || str_contains( $lower, 'clay' )
			|| str_contains( $lower, 'fiber' ) || str_contains( $lower, 'leather' ) ) {
			return 'wcat_material';
		}

		// Finish keywords.
		if ( str_contains( $lower, 'finish' ) || str_contains( $lower, 'glaze' )
			|| str_contains( $lower, 'polish' ) || str_contains( $lower, 'coat' ) ) {
			return 'wcat_finish';
		}

		// Component keywords.
		if ( str_contains( $lower, 'scribe' ) || str_contains( $lower, 'cartridge' )
			|| str_contains( $lower, 'blade' ) || str_contains( $lower, 'mechanism' )
			|| str_contains( $lower, 'hardware' ) || str_contains( $lower, 'kit' ) ) {
			return 'wcat_component';
		}

		// Type keywords.
		if ( str_contains( $lower, 'type' ) || str_contains( $lower, 'category' ) ) {
			return 'wcat_product_type';
		}

		return 'skip';
	}

	/**
	 * Render admin notices.
	 *
	 * @since 1.0.0
	 *
	 * @param string $msg Message key.
	 */
	private static function render_notice( string $msg ): void {
		if ( 'migration_complete' === $msg ) {
			$results = get_transient( 'wcat_migration_results' );
			delete_transient( 'wcat_migration_results' );

			if ( $results ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					sprintf(
						/* translators: 1: migrated count, 2: terms created, 3: assignments */
						esc_html__( 'Migration complete: %1$d products migrated, %2$d new terms created, %3$d taxonomy assignments made.', 'wc-artisan-tools' ),
						$results['migrated'],
						$results['terms_created'],
						$results['assignments']
					)
				);

				if ( ! empty( $results['errors'] ) ) {
					echo '<div class="notice notice-warning is-dismissible"><p>';
					esc_html_e( 'Some issues occurred:', 'wc-artisan-tools' );
					echo '<ul>';
					foreach ( $results['errors'] as $error ) {
						printf( '<li>%s</li>', esc_html( $error ) );
					}
					echo '</ul></p></div>';
				}
			}
		}
	}
}
