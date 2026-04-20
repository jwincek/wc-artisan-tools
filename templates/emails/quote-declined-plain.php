<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

$commission = $data['commission'] ?? [];

echo "= " . wp_strip_all_tags( $heading ) . " =\n\n";

printf(
	esc_html__( '%1$s has declined the quote for their custom %2$s.', 'wc-artisan-tools' ),
	esc_html( $commission['customer_name'] ?? '' ),
	esc_html( $commission['craft_type'] ?? __( 'piece', 'wc-artisan-tools' ) )
);
echo "\n\n";

echo esc_html__( 'View Commission Details:', 'wc-artisan-tools' ) . "\n";
echo esc_url( admin_url( 'admin.php?page=wcat-commissions&commission_id=' . ( $commission['id'] ?? 0 ) ) ) . "\n";
