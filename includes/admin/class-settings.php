<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Admin;

use WC_Artisan_Tools\Core\Config;

/**
 * Plugin settings page with craft profile selection and commission options.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Settings {

	/**
	 * Initialise settings hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0.0
	 */
	public static function register_settings(): void {
		register_setting( 'wcat_settings_group', 'wcat_craft_profile', [
			'type'              => 'string',
			'sanitize_callback' => [ self::class, 'sanitize_craft_profile' ],
			'default'           => 'general',
		] );

		register_setting( 'wcat_settings_group', 'wcat_settings', [
			'type'              => 'array',
			'sanitize_callback' => [ self::class, 'sanitize_settings' ],
		] );
	}

	/**
	 * Sanitize and handle craft profile changes.
	 *
	 * When the profile changes, clear the seed flag so new terms are seeded
	 * on the next admin_init, and flush rewrite rules for any taxonomy
	 * slug changes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value New craft profile slug.
	 * @return string Sanitized slug.
	 */
	public static function sanitize_craft_profile( string $value ): string {
		$value   = sanitize_text_field( $value );
		$current = get_option( 'wcat_craft_profile', 'general' );

		if ( $value !== $current ) {
			// Clear config cache so new profile loads immediately.
			Config::flush();

			// Allow term seeding for the new profile on next admin_init.
			// (Old profile flag stays — its terms are harmless.)
			delete_option( 'wcat_terms_seeded_' . $value );

			// Schedule a rewrite flush on next load.
			set_transient( 'wcat_flush_rewrites', '1', 60 );
		}

		return $value;
	}

	/**
	 * Sanitize settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Raw settings input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( array $input ): array {
		return [
			'commission_enabled'          => ! empty( $input['commission_enabled'] ),
			'commission_expiry_days'      => min( 90, max( 7, absint( $input['commission_expiry_days'] ?? 30 ) ) ),
			'commission_reminder_days'    => min( 60, max( 3, absint( $input['commission_reminder_days'] ?? 14 ) ) ),
			'products_per_page'           => min( 100, max( 5, absint( $input['products_per_page'] ?? 20 ) ) ),
			'redirect_taxonomy_archives'  => ! empty( $input['redirect_taxonomy_archives'] ),
		];
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_craft = Config::get_active_craft();
		$settings      = get_option( 'wcat_settings', Config::get_item( 'settings', 'defaults', [] ) );

		// Available craft profiles.
		$crafts = self::get_available_crafts();

		?>
		<div class="wrap wcat-dashboard">
			<h1><?php esc_html_e( 'Artisan Tools Settings', 'wc-artisan-tools' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcat_settings_group' ); ?>

				<!-- Craft Profile -->
				<h2><?php esc_html_e( 'Craft Profile', 'wc-artisan-tools' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wcat_craft_profile"><?php esc_html_e( 'What do you make?', 'wc-artisan-tools' ); ?></label>
						</th>
						<td>
							<select name="wcat_craft_profile" id="wcat_craft_profile">
								<?php foreach ( $crafts as $slug => $name ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"
										<?php selected( $current_craft, $slug ); ?>>
										<?php echo esc_html( $name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Sets default taxonomy labels and seed terms for your craft. Changing this will add new terms but won\'t remove existing ones.', 'wc-artisan-tools' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<!-- Commission Settings -->
				<h2><?php esc_html_e( 'Commissions', 'wc-artisan-tools' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Commissions', 'wc-artisan-tools' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wcat_settings[commission_enabled]" value="1"
									<?php checked( $settings['commission_enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Accept commission requests from customers', 'wc-artisan-tools' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wcat-expiry"><?php esc_html_e( 'Quote Expiry', 'wc-artisan-tools' ); ?></label>
						</th>
						<td>
							<input type="number" id="wcat-expiry" name="wcat_settings[commission_expiry_days]"
								   value="<?php echo esc_attr( (string) ( $settings['commission_expiry_days'] ?? 30 ) ); ?>"
								   min="7" max="90" class="small-text">
							<?php esc_html_e( 'days', 'wc-artisan-tools' ); ?>
							<p class="description">
								<?php esc_html_e( 'How long a customer has to accept a quote before the maker is nudged.', 'wc-artisan-tools' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<!-- Display Settings -->
				<h2><?php esc_html_e( 'Display', 'wc-artisan-tools' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="wcat-per-page"><?php esc_html_e( 'Products Per Page', 'wc-artisan-tools' ); ?></label>
						</th>
						<td>
							<input type="number" id="wcat-per-page" name="wcat_settings[products_per_page]"
								   value="<?php echo esc_attr( (string) ( $settings['products_per_page'] ?? 20 ) ); ?>"
								   min="5" max="100" class="small-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Taxonomy Archives', 'wc-artisan-tools' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wcat_settings[redirect_taxonomy_archives]" value="1"
									<?php checked( $settings['redirect_taxonomy_archives'] ?? false ); ?>>
								<?php esc_html_e( 'Redirect taxonomy archives to filtered shop page', 'wc-artisan-tools' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %1$s: example archive URL, %2$s: example shop URL */
									esc_html__( 'When enabled, visiting %1$s redirects to %2$s so customers see a filtered product grid instead of a bare archive.', 'wc-artisan-tools' ),
									'<code>/craft-type/pen/</code>',
									'<code>/shop/?wcat_product_type=pen</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get available craft profiles from config directory.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Slug => display name pairs.
	 */
	private static function get_available_crafts(): array {
		$crafts = [];
		$dir    = WCAT_CONFIG_PATH . 'crafts/';

		foreach ( glob( $dir . '*.json' ) as $file ) {
			$slug     = basename( $file, '.json' );
			$data     = json_decode( file_get_contents( $file ), true );
			$crafts[ $slug ] = $data['name'] ?? ucfirst( $slug );
		}

		// Sort with 'general' last.
		uksort( $crafts, function ( $a, $b ) {
			if ( 'general' === $a ) return 1;
			if ( 'general' === $b ) return -1;
			return strcmp( $a, $b );
		} );

		return $crafts;
	}
}
