<?php
declare(strict_types=1);
/**
 * Email template: Quote Sent (Plain Text).
 *
 * @package WC_Artisan_Tools
 */

defined( 'ABSPATH' ) || exit;

$commission = $data['commission'] ?? [];

echo "= " . wp_strip_all_tags( $heading ) . " =\n\n";

printf( esc_html__( 'Hi %s,', 'wc-artisan-tools' ), esc_html( $commission['customer_name'] ?? '' ) );
echo "\n\n";
echo esc_html__( 'Thank you for your interest in a custom piece! Here are the details of your quote:', 'wc-artisan-tools' ) . "\n\n";

echo esc_html__( 'Price:', 'wc-artisan-tools' ) . ' ' . wp_strip_all_tags( wc_price( (float) ( $commission['quoted_price'] ?? 0 ) ) ) . "\n";

if ( ! empty( $commission['estimated_date'] ) ) {
	echo esc_html__( 'Estimated Completion:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['estimated_date_formatted'] ?? $commission['estimated_date'] ) . "\n";
}

if ( ! empty( $commission['maker_note'] ) ) {
	echo esc_html__( 'Note from Maker:', 'wc-artisan-tools' ) . ' ' . esc_html( $commission['maker_note'] ) . "\n";
}

echo "\n" . esc_html__( 'Accept Quote:', 'wc-artisan-tools' ) . "\n";
echo esc_url( $commission['accept_url'] ?? '' ) . "\n\n";

echo esc_html__( 'Decline Quote:', 'wc-artisan-tools' ) . "\n";
echo esc_url( $commission['decline_url'] ?? '' ) . "\n";
