<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\REST;

use WC_Artisan_Tools\Commission\Commission_Handler;
use WC_Artisan_Tools\Core\Entity_Hydrator;

/**
 * REST API endpoints for commissions.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
final class Commission_REST {

	private const NAMESPACE = 'wc-artisan-tools/v1';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 */
	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/commissions', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'create_commission' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'customer_name'  => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				'customer_email' => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email' ],
				'craft_type'     => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'material_pref'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'description'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_textarea_field' ],
				'budget_range'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'deadline'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'display_name'   => [ 'type' => 'boolean', 'default' => false ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/commissions/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'get_commission' ],
			'permission_callback' => fn() => current_user_can( 'edit_products' ),
			'args'                => [
				'id' => [ 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ],
			],
		] );
	}

	/**
	 * Create a commission via REST.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function create_commission( \WP_REST_Request $request ): \WP_REST_Response {
		$result = Commission_Handler::create( $request->get_params() );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [
				'error' => $result->get_error_message(),
			], 400 );
		}

		return new \WP_REST_Response( [
			'id'      => $result,
			'message' => __( 'Commission request submitted.', 'wc-artisan-tools' ),
		], 201 );
	}

	/**
	 * Get a single commission.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public static function get_commission( \WP_REST_Request $request ): \WP_REST_Response {
		$entity = Entity_Hydrator::get( 'wcat_commission', $request->get_param( 'id' ) );

		if ( ! $entity ) {
			return new \WP_REST_Response( [ 'error' => 'Not found.' ], 404 );
		}

		return new \WP_REST_Response( $entity, 200 );
	}
}
