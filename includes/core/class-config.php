<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Core;

/**
 * JSON configuration loader with per-request caching.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Config {

	/** @var array<string, array> */
	private static array $cache = [];

	/** @var string */
	private static string $dir = '';

	/**
	 * Initialise with configuration directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Absolute path to config directory (with trailing slash).
	 */
	public static function init( string $dir ): void {
		self::$dir   = trailingslashit( $dir );
		self::$cache = [];
	}

	/**
	 * Load a JSON config file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name File name without extension (e.g. 'post-types').
	 * @return array Decoded JSON data.
	 */
	public static function get( string $name ): array {
		if ( isset( self::$cache[ $name ] ) ) {
			return self::$cache[ $name ];
		}

		$file = self::$dir . $name . '.json';

		if ( ! file_exists( $file ) ) {
			self::$cache[ $name ] = [];
			return [];
		}

		$contents = file_get_contents( $file );
		$data     = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			self::$cache[ $name ] = [];
			return [];
		}

		self::$cache[ $name ] = $data;
		return $data;
	}

	/**
	 * Get a top-level key from a config file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name    Config file name.
	 * @param string $key     Top-level key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed
	 */
	public static function get_item( string $name, string $key, mixed $default = null ): mixed {
		$data = self::get( $name );
		return $data[ $key ] ?? $default;
	}

	/**
	 * Get a nested value using dot-notation path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name    Config file name.
	 * @param string $path    Dot-notation path (e.g. 'defaults.batch_size').
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_path( string $name, string $path, mixed $default = null ): mixed {
		$data    = self::get( $name );
		$keys    = explode( '.', $path );
		$current = $data;

		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return $default;
			}
			$current = $current[ $key ];
		}

		return $current;
	}

	/**
	 * Load a craft profile by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Craft slug (e.g. 'woodworking').
	 * @return array Craft profile data.
	 */
	public static function get_craft_profile( string $slug ): array {
		$cache_key = 'crafts/' . $slug;

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$file = self::$dir . 'crafts/' . $slug . '.json';

		if ( ! file_exists( $file ) ) {
			self::$cache[ $cache_key ] = [];
			return [];
		}

		$contents = file_get_contents( $file );
		$data     = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			self::$cache[ $cache_key ] = [];
			return [];
		}

		self::$cache[ $cache_key ] = $data;
		return $data;
	}

	/**
	 * Get the active craft profile slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string Craft slug, defaults to 'general'.
	 */
	public static function get_active_craft(): string {
		return get_option( 'wcat_craft_profile', 'general' );
	}

	/**
	 * Get the active craft profile data.
	 *
	 * @since 1.0.0
	 *
	 * @return array Craft profile data.
	 */
	public static function get_active_craft_profile(): array {
		return self::get_craft_profile( self::get_active_craft() );
	}

	/**
	 * Flush config cache.
	 *
	 * @since 1.0.0
	 */
	public static function flush(): void {
		self::$cache = [];
	}
}
