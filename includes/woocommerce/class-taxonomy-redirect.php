<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\WooCommerce;

/**
 * Redirects plugin taxonomy archives to the WooCommerce shop page
 * with the taxonomy term as a filter parameter.
 *
 * When enabled, visiting /craft-type/pen/ redirects to /shop/?wcat_product_type=pen
 * so the customer sees a filtered product grid instead of a bare archive template.
 *
 * Uses 301 (permanent) redirects for SEO — search engines consolidate
 * link equity to the shop page.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Taxonomy_Redirect {

	/**
	 * Taxonomies managed by this plugin that should be redirected.
	 *
	 * @var string[]
	 */
	private const TAXONOMIES = [
		'wcat_product_type',
		'wcat_material',
		'wcat_finish',
		'wcat_component',
	];

	/**
	 * Initialise redirect hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ] );
	}

	/**
	 * Redirect taxonomy archives to the shop page with filter params.
	 *
	 * Runs on template_redirect, before any template is loaded.
	 * Only fires when the setting is enabled and the request is
	 * a public taxonomy archive for one of our taxonomies.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_redirect(): void {
		// Must be a taxonomy archive.
		if ( ! is_tax() ) {
			return;
		}

		// Check if the feature is enabled.
		$settings = get_option( 'wcat_settings', [] );
		if ( empty( $settings['redirect_taxonomy_archives'] ?? false ) ) {
			return;
		}

		$queried = get_queried_object();

		if ( ! $queried instanceof \WP_Term ) {
			return;
		}

		$taxonomy = $queried->taxonomy;

		// Only redirect our taxonomies.
		if ( ! in_array( $taxonomy, self::TAXONOMIES, true ) ) {
			return;
		}

		$shop_url = self::get_shop_url();

		if ( ! $shop_url ) {
			return;
		}

		// Build the filtered URL.
		$redirect_url = add_query_arg( $taxonomy, $queried->slug, $shop_url );

		// Support multiple terms if additional taxonomy query vars are present.
		foreach ( self::TAXONOMIES as $tax ) {
			if ( $tax === $taxonomy ) {
				continue;
			}
			$value = get_query_var( $tax );
			if ( $value ) {
				$redirect_url = add_query_arg( $tax, sanitize_text_field( $value ), $redirect_url );
			}
		}

		/**
		 * Filters the taxonomy archive redirect URL.
		 *
		 * Return an empty string to cancel the redirect for this request.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $redirect_url The URL to redirect to.
		 * @param \WP_Term $queried      The taxonomy term being viewed.
		 * @param string   $taxonomy     The taxonomy slug.
		 */
		$redirect_url = apply_filters( 'wcat_taxonomy_redirect_url', $redirect_url, $queried, $taxonomy );

		if ( empty( $redirect_url ) ) {
			return;
		}

		wp_safe_redirect( $redirect_url, 301 );
		exit;
	}

	/**
	 * Get the WooCommerce shop page URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Shop URL or empty string.
	 */
	private static function get_shop_url(): string {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		$url = wc_get_page_permalink( 'shop' );

		return ( $url && ! is_wp_error( $url ) ) ? $url : '';
	}
}
