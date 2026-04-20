<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Core;

/**
 * Converts WP_Post objects into associative arrays with meta, taxonomy terms,
 * and computed fields. Supports batch cache priming for O(n) hydration.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Entity_Hydrator {

	/** @var array<int, array> Per-request entity cache. */
	private static array $cache = [];

	/**
	 * Get a single hydrated entity.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type Post type slug.
	 * @param int    $post_id   Post ID.
	 * @return array|null Hydrated entity or null if not found.
	 */
	public static function get( string $post_type, int $post_id ): ?array {
		$cache_key = $post_type . ':' . $post_id;

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$post = get_post( $post_id );

		if ( ! $post || $post->post_type !== $post_type ) {
			return null;
		}

		$entity = self::hydrate( $post );
		self::$cache[ $cache_key ] = $entity;

		return $entity;
	}

	/**
	 * Hydrate multiple posts with batch cache priming.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $posts     Array of WP_Post objects.
	 * @param string $post_type Post type slug.
	 * @return array Array of hydrated entities.
	 */
	public static function hydrate_many( array $posts, string $post_type ): array {
		if ( empty( $posts ) ) {
			return [];
		}

		$post_ids = wp_list_pluck( $posts, 'ID' );

		// Batch prime caches — single DB query each.
		update_postmeta_cache( $post_ids );

		$entity_config = Config::get_item( 'entities', $post_type, [] );
		$taxonomies    = self::get_entity_taxonomies( $entity_config );

		if ( $taxonomies ) {
			update_object_term_cache( $post_ids, $post_type );
		}

		// Hydrate each post with zero additional DB queries.
		$results = [];
		foreach ( $posts as $post ) {
			$cache_key = $post_type . ':' . $post->ID;
			if ( isset( self::$cache[ $cache_key ] ) ) {
				$results[] = self::$cache[ $cache_key ];
				continue;
			}

			$entity = self::hydrate( $post );
			self::$cache[ $cache_key ] = $entity;
			$results[] = $entity;
		}

		return $results;
	}

	/**
	 * Hydrate a single WP_Post into an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Hydrated entity.
	 */
	public static function hydrate( \WP_Post $post ): array {
		$entity_config = Config::get_item( 'entities', $post->post_type, [] );
		$prefix        = $entity_config['meta_prefix'] ?? '_wcat_';
		$fields        = $entity_config['fields'] ?? [];
		$computed      = $entity_config['computed'] ?? [];

		$entity = [
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'content'    => $post->post_content,
			'status'     => $post->post_status,
			'date'       => $post->post_date,
			'permalink'  => get_permalink( $post->ID ),
		];

		// Meta fields.
		foreach ( $fields as $field_key => $field_config ) {
			$meta_key  = $prefix . $field_key;
			$raw_value = get_post_meta( $post->ID, $meta_key, true );

			$entity[ $field_key ] = self::cast_value( $raw_value, $field_config['type'] ?? 'string' );
		}

		// Taxonomy terms.
		$taxonomies = self::get_entity_taxonomies( $entity_config );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );
			$entity[ $taxonomy ] = ( $terms && ! is_wp_error( $terms ) )
				? array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], $terms )
				: [];
		}

		// Computed fields.
		foreach ( $computed as $key => $definition ) {
			$entity[ $key ] = self::compute_field( $definition, $entity );
		}

		// Featured image.
		$thumb_id = get_post_thumbnail_id( $post->ID );
		$entity['featured_image'] = $thumb_id
			? wp_get_attachment_image_url( (int) $thumb_id, 'medium' )
			: null;
		$entity['featured_image_id'] = $thumb_id ? (int) $thumb_id : null;

		return $entity;
	}

	/**
	 * Get taxonomies associated with an entity from config.
	 *
	 * @since 1.0.0
	 *
	 * @param array $entity_config Entity configuration.
	 * @return array Taxonomy slugs.
	 */
	private static function get_entity_taxonomies( array $entity_config ): array {
		return $entity_config['taxonomies'] ?? [];
	}

	/**
	 * Cast a meta value to its expected type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Expected type.
	 * @return mixed Cast value.
	 */
	private static function cast_value( mixed $value, string $type ): mixed {
		if ( '' === $value || null === $value ) {
			return match ( $type ) {
				'integer' => 0,
				'number'  => 0.0,
				'boolean' => false,
				default   => '',
			};
		}

		return match ( $type ) {
			'integer' => (int) $value,
			'number'  => (float) $value,
			'boolean' => (bool) $value,
			default   => (string) $value,
		};
	}

	/**
	 * Compute a derived field from entity data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $definition Computed field definition with 'callback' and 'args'.
	 * @param array $entity     Current entity data.
	 * @return mixed Computed value.
	 */
	private static function compute_field( array $definition, array $entity ): mixed {
		$callback = $definition['callback'] ?? null;
		$args     = $definition['args'] ?? [];

		if ( ! $callback ) {
			return null;
		}

		// Resolve argument values from entity data.
		$resolved_args = array_map( fn( $arg ) => $entity[ $arg ] ?? null, $args );

		return match ( $callback ) {
			'format_currency' => self::format_currency( $resolved_args[0] ?? 0 ),
			'format_date'     => self::format_date( $resolved_args[0] ?? '' ),
			'get_term_label'  => self::get_first_term_name( $resolved_args[0] ?? [] ),
			'get_accept_url'  => self::build_token_url( 'accept', $entity ),
			'get_decline_url' => self::build_token_url( 'decline', $entity ),
			default           => null,
		};
	}

	/**
	 * Format a number as currency.
	 *
	 * @since 1.0.0
	 *
	 * @param float|int $amount Amount.
	 * @return string Formatted currency string.
	 */
	private static function format_currency( float|int $amount ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( (float) $amount ) );
		}
		return '$' . number_format( (float) $amount, 2 );
	}

	/**
	 * Format a date string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date Date string.
	 * @return string Formatted date.
	 */
	private static function format_date( string $date ): string {
		if ( empty( $date ) ) {
			return '';
		}
		return wp_date( get_option( 'date_format', 'F j, Y' ), strtotime( $date ) ) ?: $date;
	}

	/**
	 * Get the name of the first term in a terms array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $terms Terms array from hydration.
	 * @return string First term name or empty string.
	 */
	private static function get_first_term_name( array $terms ): string {
		return $terms[0]['name'] ?? '';
	}

	/**
	 * Build a tokenized URL for accept/decline actions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action 'accept' or 'decline'.
	 * @param array  $entity Entity data.
	 * @return string URL.
	 */
	private static function build_token_url( string $action, array $entity ): string {
		$token = $entity['quote_token'] ?? '';
		if ( empty( $token ) ) {
			return '';
		}

		return add_query_arg( [
			'wcat_action' => $action,
			'token'       => $token,
		], wc_get_page_permalink( 'myaccount' ) );
	}

	/**
	 * Flush entity cache.
	 *
	 * @since 1.0.0
	 */
	public static function flush(): void {
		self::$cache = [];
	}
}
