<?php
declare(strict_types=1);

namespace WC_Artisan_Tools\Emails;

use WC_Artisan_Tools\Core\Entity_Hydrator;

/**
 * Generic WooCommerce email configured entirely from JSON.
 *
 * Extends WC_Email and drives all behavior from the config array passed
 * at construction. Handles entity hydration, placeholder resolution,
 * recipient determination, and template rendering.
 *
 * @since 1.0.0
 * @package WC_Artisan_Tools
 */
class Config_Email extends \WC_Email {

	/** @var array Config from emails.json. */
	private array $config;

	/** @var array Hydrated entity data. */
	private array $entity_data = [];

	/** @var array Raw trigger arguments mapped to named keys. */
	private array $trigger_args = [];

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Email configuration from JSON.
	 */
	public function __construct( array $config = [] ) {
		$this->config = $config;

		$email_id = $config['id'] ?? 'wcat-email';

		$this->id             = 'wcat_' . str_replace( '-', '_', $email_id );
		$this->title          = $config['title'] ?? '';
		$this->description    = $config['description'] ?? '';
		$this->customer_email = true;
		$this->enabled        = 'yes';

		$this->template_html  = $config['template'] ?? '';
		$this->template_plain = str_replace( '.php', '-plain.php', $this->template_html );
		$this->template_base  = WCAT_DIR . 'templates/';

		$this->placeholders = [
			'{site_name}'   => $this->get_blogname(),
			'{site_url}'    => home_url(),
			'{admin_email}' => get_option( 'admin_email' ),
		];

		// Register trigger hook.
		$trigger_hook = $config['trigger_hook'] ?? '';
		if ( $trigger_hook ) {
			$arg_count = count( $config['trigger_args'] ?? [] );
			add_action( $trigger_hook, [ $this, 'trigger' ], 10, $arg_count );
		}

		parent::__construct();
	}

	/**
	 * Trigger the email.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed ...$args Trigger arguments matching trigger_args config.
	 */
	public function trigger( ...$args ): void {
		$this->setup_locale();

		// Map positional args to named keys.
		$arg_names = $this->config['trigger_args'] ?? [];
		$this->trigger_args = [];
		foreach ( $arg_names as $i => $name ) {
			$this->trigger_args[ $name ] = $args[ $i ] ?? null;
		}

		// Hydrate entities.
		$this->hydrate_entities();

		// Check condition.
		$condition = $this->config['condition'] ?? '';
		if ( $condition && ! $this->resolve_path( $condition ) ) {
			$this->restore_locale();
			return;
		}

		// Resolve placeholders.
		$this->resolve_placeholders();

		// Determine recipient.
		$recipient = $this->get_recipient_email();

		if ( ! $recipient ) {
			$this->restore_locale();
			return;
		}

		$this->recipient = $recipient;

		if ( $this->is_enabled() ) {
			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}

		$this->restore_locale();
	}

	/**
	 * Get HTML content.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		if ( empty( $this->template_html ) ) {
			return '';
		}

		return wc_get_template_html(
			$this->template_html,
			[
				'email'         => $this,
				'email_id'      => $this->id,
				'heading'       => $this->get_heading(),
				'data'          => $this->entity_data,
				'args'          => $this->trigger_args,
				'sent_to_admin' => false,
				'plain_text'    => false,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Get plain text content.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		if ( empty( $this->template_plain ) ) {
			return '';
		}

		return wc_get_template_html(
			$this->template_plain,
			[
				'email'         => $this,
				'email_id'      => $this->id,
				'heading'       => $this->get_heading(),
				'data'          => $this->entity_data,
				'args'          => $this->trigger_args,
				'sent_to_admin' => false,
				'plain_text'    => true,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Default subject from config.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return $this->config['subject'] ?? __( 'Notification from {site_name}', 'wc-artisan-tools' );
	}

	/**
	 * Default heading from config.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return $this->config['heading'] ?? $this->title;
	}

	/**
	 * Initialise settings form fields for WooCommerce email admin.
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields(): void {
		$placeholder_list = implode( ', ', array_map(
			fn( $k ) => '{' . $k . '}',
			array_keys( $this->config['placeholders'] ?? [] )
		) );

		$this->form_fields = [
			'enabled' => [
				'title'   => __( 'Enable/Disable', 'wc-artisan-tools' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'wc-artisan-tools' ),
				'default' => 'yes',
			],
			'subject' => [
				'title'       => __( 'Subject', 'wc-artisan-tools' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => sprintf(
					/* translators: %s: placeholder list */
					__( 'Available placeholders: %s', 'wc-artisan-tools' ),
					$placeholder_list
				),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			],
			'heading' => [
				'title'       => __( 'Email heading', 'wc-artisan-tools' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => sprintf(
					/* translators: %s: placeholder list */
					__( 'Available placeholders: %s', 'wc-artisan-tools' ),
					$placeholder_list
				),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			],
			'email_type' => [
				'title'       => __( 'Email type', 'wc-artisan-tools' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'wc-artisan-tools' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			],
		];
	}

	/**
	 * Hydrate entities from trigger args using config.
	 *
	 * @since 1.0.0
	 */
	private function hydrate_entities(): void {
		$entity_configs = $this->config['entities'] ?? [];

		foreach ( $entity_configs as $name => $entity_config ) {
			$entity_type = $entity_config['entity'] ?? '';
			$id_from     = $entity_config['id_from'] ?? '';
			$entity_id   = (int) ( $this->trigger_args[ $id_from ] ?? 0 );

			if ( $entity_type && $entity_id ) {
				$entity = Entity_Hydrator::get( $entity_type, $entity_id );
				if ( $entity ) {
					$this->entity_data[ $name ] = $entity;
				}
			}
		}
	}

	/**
	 * Resolve config placeholders from entity data.
	 *
	 * @since 1.0.0
	 */
	private function resolve_placeholders(): void {
		$config_placeholders = $this->config['placeholders'] ?? [];

		foreach ( $config_placeholders as $key => $path ) {
			$value = $this->resolve_path( $path );
			$this->placeholders[ '{' . $key . '}' ] = is_scalar( $value ) ? (string) $value : '';
		}
	}

	/**
	 * Determine recipient email address.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	private function get_recipient_email(): ?string {
		$type = $this->config['recipient_type'] ?? 'admin';

		return match ( $type ) {
			'admin'  => get_option( 'admin_email' ),
			'custom' => $this->resolve_custom_recipient(),
			default  => get_option( 'admin_email' ),
		};
	}

	/**
	 * Resolve custom recipient from config path.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	private function resolve_custom_recipient(): ?string {
		$field = $this->config['recipient_field'] ?? '';
		if ( ! $field ) {
			return null;
		}

		$value = $this->resolve_path( $field );
		return is_string( $value ) && is_email( $value ) ? $value : null;
	}

	/**
	 * Resolve a dot-notation path against entity data and trigger args.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Dot-notation path (e.g. 'commission.customer_name').
	 * @return mixed Resolved value or null.
	 */
	private function resolve_path( string $path ): mixed {
		$parts   = explode( '.', $path );
		$current = array_merge( $this->entity_data, [ 'args' => $this->trigger_args ] );

		foreach ( $parts as $part ) {
			if ( is_array( $current ) && array_key_exists( $part, $current ) ) {
				$current = $current[ $part ];
			} elseif ( is_object( $current ) && property_exists( $current, $part ) ) {
				$current = $current->$part;
			} else {
				return null;
			}
		}

		return $current;
	}
}
