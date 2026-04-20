<?php
declare(strict_types=1);
/**
 * Plugin Name: WC Artisan Tools
 * Description: A simplified WooCommerce product dashboard and commission system for artisans and makers.
 * Version:     1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author:      Jerome Wincek
 * Text Domain: wc-artisan-tools
 * License:     GPL-2.0-or-later
 *
 * @package WC_Artisan_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCAT_VERSION', '1.0.0' );
define( 'WCAT_FILE', __FILE__ );
define( 'WCAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCAT_URL', plugin_dir_url( __FILE__ ) );
define( 'WCAT_CONFIG_PATH', WCAT_DIR . 'config/' );

/**
 * Autoloader.
 *
 * Handles two naming conventions:
 *   Namespaced:  WC_Artisan_Tools\Core\Config       → includes/core/class-config.php
 *   Legacy flat: WC_Artisan_Tools_Dashboard          → includes/class-wc-artisan-tools-dashboard.php
 */
spl_autoload_register( function ( string $class ): void {
	$root_namespace = 'WC_Artisan_Tools';

	// Namespaced classes.
	if ( str_starts_with( $class, $root_namespace . '\\' ) ) {
		$relative = substr( $class, strlen( $root_namespace ) + 1 );
		$parts    = explode( '\\', $relative );
		$filename = 'class-' . str_replace( '_', '-', strtolower( array_pop( $parts ) ) ) . '.php';
		$path     = WCAT_DIR . 'includes/'
			. ( $parts ? strtolower( implode( '/', array_map( fn( $p ) => str_replace( '_', '-', $p ), $parts ) ) ) . '/' : '' )
			. $filename;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
		return;
	}

	// Legacy flat classes: WC_Artisan_Tools_Something → includes/class-wc-artisan-tools-something.php
	if ( str_starts_with( $class, $root_namespace . '_' ) ) {
		$filename = 'class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
		$path     = WCAT_DIR . 'includes/' . $filename;

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
} );

/* =========================================================================
   Activation / Deactivation
   ========================================================================= */

register_activation_hook( __FILE__, function (): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	WC_Artisan_Tools\Core\Config::init( WCAT_CONFIG_PATH );
	WC_Artisan_Tools\Core\CPT_Registry::register_taxonomies();
	WC_Artisan_Tools\Core\CPT_Registry::register_post_types();
	WC_Artisan_Tools\Core\Activator::activate();

	// Register My Account endpoint before flushing so the rewrite rule is created.
	add_rewrite_endpoint( 'commissions', EP_ROOT | EP_PAGES );

	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function (): void {
	wp_clear_scheduled_hook( 'wcat_payment_reminder' );
	flush_rewrite_rules();
} );

/* =========================================================================
   Main initialisation
   ========================================================================= */

add_action( 'plugins_loaded', function (): void {

	/* --- WooCommerce dependency check --- */
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'WC Artisan Tools requires WooCommerce to be installed and active.', 'wc-artisan-tools' )
			);
		} );
		return;
	}

	/* --- Priority 10: Core infrastructure --- */
	WC_Artisan_Tools\Core\Config::init( WCAT_CONFIG_PATH );

	/* --- Priority 5 on init: CPT + taxonomy registration --- */
	add_action( 'init', [ WC_Artisan_Tools\Core\CPT_Registry::class, 'register_post_types' ], 5 );
	add_action( 'init', [ WC_Artisan_Tools\Core\CPT_Registry::class, 'register_taxonomies' ], 5 );
	add_action( 'init', [ WC_Artisan_Tools\Core\CPT_Registry::class, 'register_post_meta' ], 11 );

	/* --- Priority 20: Commission block registration --- */
	add_action( 'init', function (): void {
		$blocks_dir = WCAT_DIR . 'blocks/';
		foreach ( glob( $blocks_dir . '*/block.json' ) as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}, 20 );

	/* --- WooCommerce integration --- */
	WC_Artisan_Tools\WooCommerce\Product_Manager::init();
	WC_Artisan_Tools\WooCommerce\Order_Handler::init();
	WC_Artisan_Tools\WooCommerce\My_Account::init();

	/* --- Commission handling --- */
	WC_Artisan_Tools\Commission\Commission_Handler::init();

	/* --- Email system --- */
	WC_Artisan_Tools\Emails\Email_Factory::init();

	/* --- Simple Spam Shield integration --- */
	WC_Artisan_Tools\Integrations\Spam_Shield::init();

	/* --- REST API --- */
	add_action( 'rest_api_init', [ WC_Artisan_Tools\REST\Commission_REST::class, 'register_routes' ] );

	/* --- Admin --- */
	if ( is_admin() ) {
		WC_Artisan_Tools\Admin\Menu::init();
		WC_Artisan_Tools\Admin\Settings::init();
		WC_Artisan_Tools\Admin\Dashboard::init();
		WC_Artisan_Tools\Admin\Commission_Admin::init();

		// Deferred rewrite flush after craft profile change.
		add_action( 'admin_init', function (): void {
			if ( get_transient( 'wcat_flush_rewrites' ) ) {
				delete_transient( 'wcat_flush_rewrites' );
				flush_rewrite_rules();
			}
		} );
	}

	/* --- Frontend assets --- */
	add_action( 'wp_enqueue_scripts', function (): void {
		if ( has_block( 'wc-artisan-tools/commission-form' ) ) {
			wp_enqueue_style(
				'wcat-commission-form',
				WCAT_URL . 'assets/css/commission-form.css',
				[],
				WCAT_VERSION
			);
		}
	} );

	/* --- Abilities API (WP 6.9+) --- */
	if ( function_exists( 'wp_register_ability_category' ) ) {
		add_action( 'wp_abilities_api_categories_init', function (): void {
			wp_register_ability_category( 'wc-artisan-tools', [
				'label'       => __( 'Artisan Tools', 'wc-artisan-tools' ),
				'description' => __( 'Product and commission management for artisan shops.', 'wc-artisan-tools' ),
			] );
		} );
	}
} );
