<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Admin;

use WC_Artisan_Tools\Core\Config;

/**
 * Registers admin menu pages.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Menu {

	/**
	 * Initialise menu hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menus' ] );
	}

	/**
	 * Register admin menu and submenu pages.
	 *
	 * @since 1.0.0
	 */
	public static function register_menus(): void {
		$craft = Config::get_active_craft_profile();
		$craft_name = $craft['name'] ?? 'Artisan';

		// Main menu.
		add_menu_page(
			__( 'Artisan Tools', 'wc-artisan-tools' ),
			__( 'My Crafts', 'wc-artisan-tools' ),
			'edit_products',
			'wcat-dashboard',
			[ Dashboard::class, 'render_page' ],
			'dashicons-art',
			26
		);

		// Products submenu (default).
		add_submenu_page(
			'wcat-dashboard',
			__( 'My Products', 'wc-artisan-tools' ),
			__( 'Products', 'wc-artisan-tools' ),
			'edit_products',
			'wcat-dashboard',
			[ Dashboard::class, 'render_page' ]
		);

		// Add new product.
		add_submenu_page(
			'wcat-dashboard',
			__( 'Add New', 'wc-artisan-tools' ),
			__( 'Add New', 'wc-artisan-tools' ),
			'edit_products',
			'wcat-add-product',
			[ Dashboard::class, 'render_add_page' ]
		);

		// Commissions.
		$pending = self::get_pending_count();
		$badge   = $pending > 0
			? sprintf( ' <span class="awaiting-mod">%d</span>', $pending )
			: '';

		add_submenu_page(
			'wcat-dashboard',
			__( 'Commissions', 'wc-artisan-tools' ),
			__( 'Commissions', 'wc-artisan-tools' ) . $badge,
			'edit_products',
			'wcat-commissions',
			[ Commission_Admin::class, 'render_page' ]
		);

		// Settings.
		add_submenu_page(
			'wcat-dashboard',
			__( 'Settings', 'wc-artisan-tools' ),
			__( 'Settings', 'wc-artisan-tools' ),
			'manage_options',
			'wcat-settings',
			[ Settings::class, 'render_page' ]
		);
	}

	/**
	 * Get count of new/pending commissions.
	 *
	 * @since 1.0.0
	 *
	 * @return int Pending count.
	 */
	private static function get_pending_count(): int {
		$cached = get_transient( 'wcat_pending_count' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$count = (int) wp_count_posts( 'wcat_commission' )->publish ?? 0;

		// Count posts with _wcat_status = 'new'.
		$query = new \WP_Query( [
			'post_type'      => 'wcat_commission',
			'post_status'    => 'publish',
			'meta_key'       => '_wcat_status',
			'meta_value'     => 'new',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		$count = $query->post_count;

		set_transient( 'wcat_pending_count', $count, 10 * MINUTE_IN_SECONDS );

		return $count;
	}
}
