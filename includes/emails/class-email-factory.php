<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Emails;

use WC_Artisan_Tools\Core\Config;

/**
 * Config-driven email factory built on WooCommerce's email system.
 *
 * Reads email definitions from config/emails.json and dynamically registers
 * them as WooCommerce emails. Each email is an instance of Config_Email
 * configured entirely from JSON — no per-email PHP classes needed.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Email_Factory {

	/** @var array<string, Config_Email> */
	private static array $emails = [];

	/**
	 * Initialise email factory.
	 *
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_filter( 'woocommerce_email_classes', [ self::class, 'register_emails' ] );
	}

	/**
	 * Register all configured emails as WooCommerce email classes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $email_classes Existing WooCommerce email classes.
	 * @return array Modified email classes.
	 */
	public static function register_emails( array $email_classes ): array {
		$email_configs = Config::get_item( 'emails', 'emails', [] );

		foreach ( $email_configs as $email_id => $config ) {
			$class_name = 'WCAT_Email_' . self::to_class_name( $email_id );
			$config['id'] = $email_id;

			$email = new Config_Email( $config );
			self::$emails[ $email_id ] = $email;

			$email_classes[ $class_name ] = $email;
		}

		return $email_classes;
	}

	/**
	 * Get a registered email instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_id Email ID.
	 * @return Config_Email|null
	 */
	public static function get_email( string $email_id ): ?Config_Email {
		return self::$emails[ $email_id ] ?? null;
	}

	/**
	 * Convert dash-case email ID to PascalCase class name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_id Email ID (e.g. 'quote-sent').
	 * @return string Class name fragment (e.g. 'Quote_Sent').
	 */
	private static function to_class_name( string $email_id ): string {
		return str_replace( '-', '_', ucwords( $email_id, '-' ) );
	}
}
