<?php
declare(strict_types=1);
/**
 * Email template: Quote Accepted — sent to maker (HTML).
 *
 * @package WC_Artisan_Tools
 */

defined( 'ABSPATH' ) || exit;

$commission = $data['commission'] ?? [];

do_action( 'woocommerce_email_header', $heading, $email );
?>

<p>
	<?php printf(
		/* translators: 1: customer name, 2: craft type */
		esc_html__( '%1$s has accepted the quote for their custom %2$s!', 'wc-artisan-tools' ),
		'<strong>' . esc_html( $commission['customer_name'] ?? '' ) . '</strong>',
		esc_html( $commission['craft_type'] ?? __( 'piece', 'wc-artisan-tools' ) )
	); ?>
</p>

<p>
	<?php printf(
		/* translators: %s: price */
		esc_html__( 'Quoted price: %s', 'wc-artisan-tools' ),
		wp_kses_post( wc_price( (float) ( $commission['quoted_price'] ?? 0 ) ) )
	); ?>
</p>

<p><?php esc_html_e( 'A product has been created and the customer has been directed to checkout. You\'ll receive a standard order notification when payment is complete.', 'wc-artisan-tools' ); ?></p>

<p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-commissions&commission_id=' . ( $commission['id'] ?? 0 ) ) ); ?>"
	   style="background:#2271b1;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block">
		<?php esc_html_e( 'View Commission', 'wc-artisan-tools' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
