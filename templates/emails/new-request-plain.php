<?php
declare(strict_types=1);
/**
 * Email template: New Commission Request (Plain Text).
 *
 * @package WC_Artisan_Tools
 */

defined( 'ABSPATH' ) || exit;

$commission = $data['commission'] ?? [];

echo "= " . wp_strip_all_tags( $heading ) . " =\n\n";

echo esc_html__( 'You have a new commission request!', 'wc-artisan-tools' ) . "\n\n";

echo esc_html__( 'Customer:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['customer_name'] ?? '' ) . ' (' . esc_html( $commission['customer_email'] ?? '' ) . ")\n";

if ( ! empty( $commission['craft_type'] ) ) {
	echo esc_html__( 'Type:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['craft_type'] ) . "\n";
}

if ( ! empty( $commission['material_pref'] ) ) {
	echo esc_html__( 'Material:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['material_pref'] ) . "\n";
}

echo esc_html__( 'Description:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['description'] ?? '' ) . "\n";

if ( ! empty( $commission['budget_range'] ) ) {
	echo esc_html__( 'Budget:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['budget_range'] ) . "\n";
}

if ( ! empty( $commission['deadline'] ) ) {
	echo esc_html__( 'Deadline:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['deadline'] ) . "\n";
}

echo "\n" . esc_html__( 'View & Send Quote:', 'wc-artisan-tools' ) . "\n";
echo esc_url( admin_url( 'admin.php?page=wcat-commissions&commission_id=' . ( $commission['id'] ?? 0 ) ) ) . "\n";
