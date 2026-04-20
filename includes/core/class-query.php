<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Core;

/**
 * Fluent query builder for WordPress posts with auto-hydration.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Query {

	private string $post_type;
	private array $args = [];
	private array $meta_query = [];
	private array $tax_query = [];
	private bool $hydrate = true;

	/**
	 * Create a new query for a post type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type Post type slug.
	 * @return self
	 */
	public static function for( string $post_type ): self {
		$instance = new self();
		$instance->post_type = $post_type;
		$instance->args['post_type'] = $post_type;
		$instance->args['post_status'] = 'publish';
		return $instance;
	}

	/**
	 * Set a WP_Query argument.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field Argument name.
	 * @param mixed  $value Argument value.
	 * @return self
	 */
	public function where( string $field, mixed $value ): self {
		$this->args[ $field ] = $value;
		return $this;
	}

	/**
	 * Add a meta query clause.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @param string $compare Comparison operator.
	 * @param string $type    Meta value type.
	 * @return self
	 */
	public function meta_where( string $key, mixed $value, string $compare = '=', string $type = 'CHAR' ): self {
		$this->meta_query[] = [
			'key'     => $key,
			'value'   => $value,
			'compare' => $compare,
			'type'    => $type,
		];
		return $this;
	}

	/**
	 * Add a taxonomy query clause.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $taxonomy Taxonomy slug.
	 * @param string|array $terms    Term slug(s) or ID(s).
	 * @param string       $field    Term field to match ('slug', 'term_id', 'name').
	 * @return self
	 */
	public function tax_where( string $taxonomy, string|array $terms, string $field = 'slug' ): self {
		$this->tax_query[] = [
			'taxonomy' => $taxonomy,
			'field'    => $field,
			'terms'    => (array) $terms,
		];
		return $this;
	}

	/**
	 * Set ordering.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field Order field.
	 * @param string $order Direction ('ASC' or 'DESC').
	 * @return self
	 */
	public function orderby( string $field, string $order = 'DESC' ): self {
		$this->args['orderby'] = $field;
		$this->args['order']   = $order;
		return $this;
	}

	/**
	 * Set pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param int $per_page Posts per page.
	 * @param int $page     Page number.
	 * @return self
	 */
	public function paginate( int $per_page, int $page = 1 ): self {
		$this->args['posts_per_page'] = $per_page;
		$this->args['paged']          = $page;
		return $this;
	}

	/**
	 * Disable hydration (return raw WP_Post objects).
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public function raw(): self {
		$this->hydrate = false;
		return $this;
	}

	/**
	 * Execute query and return results.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of hydrated entities or WP_Post objects.
	 */
	public function get(): array {
		$query_args = $this->build_args();
		$query      = new \WP_Query( $query_args );

		if ( ! $this->hydrate ) {
			return $query->posts;
		}

		return Entity_Hydrator::hydrate_many( $query->posts, $this->post_type );
	}

	/**
	 * Execute query and return paginated results with total counts.
	 *
	 * @since 1.0.0
	 *
	 * @return array{items: array, total: int, pages: int, page: int}
	 */
	public function get_paginated(): array {
		$query_args = $this->build_args();
		$query      = new \WP_Query( $query_args );

		$items = $this->hydrate
			? Entity_Hydrator::hydrate_many( $query->posts, $this->post_type )
			: $query->posts;

		return [
			'items' => $items,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'page'  => (int) ( $this->args['paged'] ?? 1 ),
		];
	}

	/**
	 * Build final WP_Query arguments.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function build_args(): array {
		$args = $this->args;

		if ( $this->meta_query ) {
			$args['meta_query'] = $this->meta_query;
		}

		if ( $this->tax_query ) {
			$args['tax_query'] = $this->tax_query;
		}

		return $args;
	}
}
