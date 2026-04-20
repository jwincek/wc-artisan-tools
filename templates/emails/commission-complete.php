<?php
declare(strict_types=1);
/**
 * Email template: Commission Complete — sent to customer (HTML).
 *
 * @package WC_Artisan_Tools
 */

defined( 'ABSPATH' ) || exit;

$commission = $data['commission'] ?? [];

do_action( 'woocommerce_email_header', $heading, $email );
?>

<p>
	<?php printf(
		/* translators: %s: customer name */
		esc_html__( 'Hi %s,', 'wc-artisan-tools' ),
		esc_html( $commission['customer_name'] ?? '' )
	); ?>
</p>

<p>
	<?php printf(
		/* translators: %s: craft type */
		esc_html__( 'Great news! Your custom %s is complete and ready to ship.', 'wc-artisan-tools' ),
		esc_html( $commission['craft_type'] ?? __( 'piece', 'wc-artisan-tools' ) )
	); ?>
</p>

<p><?php esc_html_e( 'Thank you for trusting us with your custom piece. We hope you love it!', 'wc-artisan-tools' ); ?></p>

<?php
do_action( 'woocommerce_email_footer', $email );
