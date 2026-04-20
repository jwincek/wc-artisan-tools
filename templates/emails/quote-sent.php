<?php
declare(strict_types=1);
/**
 * Email template: Quote Sent to Customer (HTML).
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

<p><?php esc_html_e( 'Thank you for your interest in a custom piece! Here are the details of your quote:', 'wc-artisan-tools' ); ?></p>

<table cellpadding="6" cellspacing="0" style="width:100%;border:1px solid #e5e5e5;margin:20px 0">
	<tr>
		<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Price', 'wc-artisan-tools' ); ?></th>
		<td style="border-bottom:1px solid #e5e5e5;padding:12px;font-size:18px;font-weight:bold">
			<?php echo wp_kses_post( wc_price( (float) ( $commission['quoted_price'] ?? 0 ) ) ); ?>
		</td>
	</tr>
	<?php if ( ! empty( $commission['estimated_date'] ) ) : ?>
		<tr>
			<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Estimated Completion', 'wc-artisan-tools' ); ?></th>
			<td style="border-bottom:1px solid #e5e5e5;padding:12px"><?php echo esc_html( $commission['estimated_date_formatted'] ?? $commission['estimated_date'] ); ?></td>
		</tr>
	<?php endif; ?>
	<?php if ( ! empty( $commission['maker_note'] ) ) : ?>
		<tr>
			<th style="text-align:left;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Note from Maker', 'wc-artisan-tools' ); ?></th>
			<td style="padding:12px"><?php echo esc_html( $commission['maker_note'] ); ?></td>
		</tr>
	<?php endif; ?>
</table>

<p style="text-align:center;margin:24px 0">
	<a href="<?php echo esc_url( $commission['accept_url'] ?? '#' ); ?>"
	   style="background:#00a32a;color:#fff;padding:14px 32px;text-decoration:none;border-radius:4px;display:inline-block;font-size:16px;font-weight:bold">
		<?php esc_html_e( 'Accept Quote', 'wc-artisan-tools' ); ?>
	</a>
</p>

<p style="text-align:center">
	<a href="<?php echo esc_url( $commission['decline_url'] ?? '#' ); ?>" style="color:#787c82;font-size:13px">
		<?php esc_html_e( 'No thanks, decline this quote', 'wc-artisan-tools' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
