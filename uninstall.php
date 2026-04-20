<?php
/**
 * WC Artisan Tools — Uninstall.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin.
 *
 * @package WC_Artisan_Tools
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin options.
delete_option( 'wcat_craft_profile' );
delete_option( 'wcat_settings' );

// Remove all seed flags (wcat_terms_seeded_*).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'wcat_terms_seeded_%'
	)
);

// Remove all commission posts and their meta.
$commission_ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wcat_commission'"
);

if ( ! empty( $commission_ids ) ) {
	foreach ( $commission_ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}

// Remove custom taxonomies' terms (WordPress handles this on taxonomy unregistration,
// but clean up any orphaned term relationships).
$taxonomies = [
	'wcat_product_type',
	'wcat_material',
	'wcat_finish',
	'wcat_component',
	'wcat_product_origin',
];

foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms( [
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'fields'     => 'ids',
	] );

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}
	}
}

// Remove commission-related product meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_wcat_commission', '_wcat_commission_id')" );

// Remove transients.
delete_transient( 'wcat_pending_count' );
delete_transient( 'wcat_flush_rewrites' );

// Clean up any rate-limiting transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_wcat_commission_%',
		'_transient_timeout_wcat_commission_%'
	)
);

// Remove WooCommerce email settings for our emails.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'woocommerce_wcat_%'
	)
);

// Flush rewrite rules to remove our endpoints.
flush_rewrite_rules();
