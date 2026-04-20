<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Core;

/**
 * Handles plugin activation tasks.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Activator {

	/**
	 * Run activation tasks.
	 *
	 * @since 1.0.0
	 */
	public static function activate(): void {
		// Set default craft profile if not set.
		if ( ! get_option( 'wcat_craft_profile' ) ) {
			update_option( 'wcat_craft_profile', 'general' );
		}

		// Set default settings.
		if ( ! get_option( 'wcat_settings' ) ) {
			$defaults = Config::get_item( 'settings', 'defaults', [] );
			update_option( 'wcat_settings', $defaults );
		}
	}
}
