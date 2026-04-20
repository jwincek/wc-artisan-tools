<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Integrations;

/**
 * Simple Spam Shield integration for the commission request form.
 *
 * Hooks into the commission form submission and delegates spam checking
 * to Simple Spam Shield's Guard_Runner if available. Degrades gracefully
 * if the plugin is not active.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Spam_Shield {

	/**
	 * Initialise spam shield integration.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_filter( 'wcat_commission_spam_check', [ self::class, 'check' ], 10, 2 );
	}

	/**
	 * Run spam check against Simple Spam Shield.
	 *
	 * @since 1.0.0
	 *
	 * @param true|\WP_Error $result  Current result (passthrough if already failed).
	 * @param array          $data    Raw form POST data.
	 * @return true|\WP_Error True if clean, WP_Error if spam.
	 */
	public static function check( true|\WP_Error $result, array $data ): true|\WP_Error {
		// If already flagged by another filter, don't override.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Simple Spam Shield not active — degrade gracefully.
		if ( ! class_exists( \SSS\Core\Guard_Runner::class ) ) {
			return true;
		}

		$normalized = [
			'content' => sanitize_textarea_field( wp_unslash( $data['description'] ?? '' ) ),
			'author'  => sanitize_text_field( wp_unslash( $data['customer_name'] ?? '' ) ),
			'email'   => sanitize_email( wp_unslash( $data['customer_email'] ?? '' ) ),
		];

		return \SSS\Core\Guard_Runner::run( $normalized, 'commission_form' );
	}
}
