<?php
declare(strict_types=1);
/**
 * Email template: Quote Declined — sent to maker (HTML).
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
		esc_html__( '%1$s has declined the quote for their custom %2$s.', 'wc-artisan-tools' ),
		'<strong>' . esc_html( $commission['customer_name'] ?? '' ) . '</strong>',
		esc_html( $commission['craft_type'] ?? __( 'piece', 'wc-artisan-tools' ) )
	); ?>
</p>

<p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-commissions&commission_id=' . ( $commission['id'] ?? 0 ) ) ); ?>"
	   style="color:#2271b1">
		<?php esc_html_e( 'View Commission Details', 'wc-artisan-tools' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
