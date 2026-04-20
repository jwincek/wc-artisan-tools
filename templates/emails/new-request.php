<?php
declare(strict_types=1);
/**
 * Email template: New Commission Request (HTML).
 *
 * @var WC_Artisan_Tools\Emails\Config_Email $email
 * @var string $heading
 * @var array  $data
 * @var array  $args
 *
 * @package WC_Artisan_Tools
 */

defined( 'ABSPATH' ) || exit;

$commission = $data['commission'] ?? [];

do_action( 'woocommerce_email_header', $heading, $email );
?>

<p><?php esc_html_e( 'You have a new commission request!', 'wc-artisan-tools' ); ?></p>

<table cellpadding="6" cellspacing="0" style="width:100%;border:1px solid #e5e5e5;margin-bottom:20px">
	<tr>
		<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Customer', 'wc-artisan-tools' ); ?></th>
		<td style="border-bottom:1px solid #e5e5e5;padding:12px">
			<?php echo esc_html( $commission['customer_name'] ?? '' ); ?>
			(<a href="mailto:<?php echo esc_attr( $commission['customer_email'] ?? '' ); ?>"><?php echo esc_html( $commission['customer_email'] ?? '' ); ?></a>)
		</td>
	</tr>
	<?php if ( ! empty( $commission['craft_type'] ) ) : ?>
		<tr>
			<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Type', 'wc-artisan-tools' ); ?></th>
			<td style="border-bottom:1px solid #e5e5e5;padding:12px"><?php echo esc_html( $commission['craft_type'] ); ?></td>
		</tr>
	<?php endif; ?>
	<?php if ( ! empty( $commission['material_pref'] ) ) : ?>
		<tr>
			<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Material', 'wc-artisan-tools' ); ?></th>
			<td style="border-bottom:1px solid #e5e5e5;padding:12px"><?php echo esc_html( $commission['material_pref'] ); ?></td>
		</tr>
	<?php endif; ?>
	<tr>
		<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Description', 'wc-artisan-tools' ); ?></th>
		<td style="border-bottom:1px solid #e5e5e5;padding:12px"><?php echo esc_html( $commission['description'] ?? '' ); ?></td>
	</tr>
	<?php if ( ! empty( $commission['budget_range'] ) ) : ?>
		<tr>
			<th style="text-align:left;border-bottom:1px solid #e5e5e5;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Budget', 'wc-artisan-tools' ); ?></th>
			<td style="border-bottom:1px solid #e5e5e5;padding:12px"><?php echo esc_html( $commission['budget_range'] ); ?></td>
		</tr>
	<?php endif; ?>
	<?php if ( ! empty( $commission['deadline'] ) ) : ?>
		<tr>
			<th style="text-align:left;padding:12px;background:#f8f8f8"><?php esc_html_e( 'Deadline', 'wc-artisan-tools' ); ?></th>
			<td style="padding:12px"><?php echo esc_html( $commission['deadline'] ); ?></td>
		</tr>
	<?php endif; ?>
</table>

<p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wcat-commissions&commission_id=' . ( $commission['id'] ?? 0 ) ) ); ?>"
	   style="background:#2271b1;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block">
		<?php esc_html_e( 'View & Send Quote', 'wc-artisan-tools' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
