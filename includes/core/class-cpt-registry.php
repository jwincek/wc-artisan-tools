<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Core;

/**
 * Registers custom post types, taxonomies, and post meta from config.
 *
 * Taxonomies are registered against WooCommerce's 'product' post type
 * and the plugin's 'wcat_commission' post type.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class CPT_Registry {

	/**
	 * Register custom post types from config.
	 *
	 * @since 1.0.0
	 */
	public static function register_post_types(): void {
		$post_types = Config::get( 'post-types' );

		foreach ( $post_types as $slug => $config ) {
			$labels = self::build_labels( $config['singular'] ?? $slug, $config['plural'] ?? $slug . 's' );

			$args = array_merge( [
				'labels'       => $labels,
				'public'       => $config['public'] ?? false,
				'show_ui'      => $config['show_ui'] ?? true,
				'show_in_rest' => $config['show_in_rest'] ?? true,
				'supports'     => $config['supports'] ?? [ 'title' ],
				'menu_icon'    => $config['menu_icon'] ?? 'dashicons-admin-generic',
				'has_archive'  => $config['has_archive'] ?? false,
				'rewrite'      => $config['rewrite'] ?? false,
			], $config['args'] ?? [] );

			register_post_type( $slug, $args );
		}
	}

	/**
	 * Register taxonomies from config and active craft profile.
	 *
	 * @since 1.0.0
	 */
	public static function register_taxonomies(): void {
		$taxonomies = Config::get( 'taxonomies' );
		$craft      = Config::get_active_craft_profile();

		foreach ( $taxonomies as $slug => $config ) {
			$labels = self::build_taxonomy_labels(
				$config['singular'] ?? $slug,
				$config['plural'] ?? $slug . 's'
			);

			// Allow craft profile to override labels.
			if ( isset( $craft['taxonomy_labels'][ $slug ] ) ) {
				$overrides = $craft['taxonomy_labels'][ $slug ];
				if ( isset( $overrides['singular'] ) ) {
					$labels = self::build_taxonomy_labels( $overrides['singular'], $overrides['plural'] ?? $overrides['singular'] . 's' );
				}
			}

			$post_types = $config['post_types'] ?? [ 'product' ];

			$args = [
				'labels'            => $labels,
				'hierarchical'      => $config['hierarchical'] ?? false,
				'public'            => $config['public'] ?? true,
				'show_in_rest'      => $config['show_in_rest'] ?? true,
				'show_admin_column' => $config['show_admin_column'] ?? true,
				'rewrite'           => $config['rewrite'] ?? [ 'slug' => $slug ],
			];

			register_taxonomy( $slug, $post_types, $args );
		}

		// Seed default terms from craft profile on admin_init.
		add_action( 'admin_init', [ self::class, 'seed_default_terms' ], 12 );
	}

	/**
	 * Seed default taxonomy terms from the active craft profile.
	 *
	 * @since 1.0.0
	 */
	public static function seed_default_terms(): void {
		// Only seed once per craft profile change.
		$craft_slug = Config::get_active_craft();
		$seeded_key = 'wcat_terms_seeded_' . $craft_slug;

		if ( get_option( $seeded_key ) ) {
			return;
		}

		$craft = Config::get_active_craft_profile();
		$terms = $craft['default_terms'] ?? [];

		foreach ( $terms as $taxonomy => $term_list ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			foreach ( $term_list as $term_name ) {
				if ( ! term_exists( $term_name, $taxonomy ) ) {
					wp_insert_term( $term_name, $taxonomy );
				}
			}
		}

		update_option( $seeded_key, true );
	}

	/**
	 * Register post meta from entities config.
	 *
	 * @since 1.0.0
	 */
	public static function register_post_meta(): void {
		$entities = Config::get( 'entities' );

		foreach ( $entities as $entity_key => $entity_config ) {
			$post_type  = $entity_config['post_type'] ?? $entity_key;
			$prefix     = $entity_config['meta_prefix'] ?? '_wcat_';
			$fields     = $entity_config['fields'] ?? [];

			foreach ( $fields as $field_key => $field_config ) {
				$meta_key = $prefix . $field_key;
				$type     = $field_config['type'] ?? 'string';

				register_post_meta( $post_type, $meta_key, [
					'type'              => self::map_type( $type ),
					'single'            => $field_config['single'] ?? true,
					'show_in_rest'      => $field_config['show_in_rest'] ?? true,
					'sanitize_callback' => self::get_sanitize_callback( $type ),
					'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
				] );
			}
		}
	}

	/**
	 * Map JSON schema type to WordPress meta type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type JSON type.
	 * @return string WordPress meta type.
	 */
	private static function map_type( string $type ): string {
		return match ( $type ) {
			'integer'     => 'integer',
			'number'      => 'number',
			'boolean'     => 'boolean',
			'array'       => 'array',
			default       => 'string',
		};
	}

	/**
	 * Get sanitization callback for a field type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Field type.
	 * @return callable Sanitization function.
	 */
	private static function get_sanitize_callback( string $type ): callable {
		return match ( $type ) {
			'integer'  => 'absint',
			'number'   => fn( $v ) => (float) $v,
			'boolean'  => 'rest_sanitize_boolean',
			'email'    => 'sanitize_email',
			'url'      => 'esc_url_raw',
			'html'     => 'wp_kses_post',
			'textarea' => 'sanitize_textarea_field',
			default    => 'sanitize_text_field',
		};
	}

	/**
	 * Build post type labels from singular and plural names.
	 *
	 * @since 1.0.0
	 *
	 * @param string $singular Singular name.
	 * @param string $plural   Plural name.
	 * @return array Complete labels array.
	 */
	private static function build_labels( string $singular, string $plural ): array {
		return [
			'name'               => $plural,
			'singular_name'      => $singular,
			'add_new'            => 'Add New',
			'add_new_item'       => "Add New {$singular}",
			'edit_item'          => "Edit {$singular}",
			'new_item'           => "New {$singular}",
			'view_item'          => "View {$singular}",
			'search_items'       => "Search {$plural}",
			'not_found'          => "No {$plural} found",
			'not_found_in_trash' => "No {$plural} found in Trash",
			'all_items'          => "All {$plural}",
			'menu_name'          => $plural,
		];
	}

	/**
	 * Build taxonomy labels from singular and plural names.
	 *
	 * @since 1.0.0
	 *
	 * @param string $singular Singular name.
	 * @param string $plural   Plural name.
	 * @return array Complete labels array.
	 */
	private static function build_taxonomy_labels( string $singular, string $plural ): array {
		return [
			'name'              => $plural,
			'singular_name'     => $singular,
			'search_items'      => "Search {$plural}",
			'all_items'         => "All {$plural}",
			'edit_item'         => "Edit {$singular}",
			'update_item'       => "Update {$singular}",
			'add_new_item'      => "Add New {$singular}",
			'new_item_name'     => "New {$singular} Name",
			'menu_name'         => $plural,
		];
	}
}
