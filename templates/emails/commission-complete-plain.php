<?php
declare(strict_types=1);
defined( 'ABSPATH' ) || exit;

$commission = $data['commission'] ?? [];

echo "= " . wp_strip_all_tags( $heading ) . " =\n\n";

printf( esc_html__( 'Hi %s,', 'wc-artisan-tools' ), esc_html( $commission['customer_name'] ?? '' ) );
echo "\n\n";

printf(
	esc_html__( 'Great news! Your custom %s is complete and ready to ship.', 'wc-artisan-tools' ),
	esc_html( $commission['craft_type'] ?? __( 'piece', 'wc-artisan-tools' ) )
);
echo "\n\n";

echo esc_html__( 'Thank you for trusting us with your custom piece. We hope you love it!', 'wc-artisan-tools' ) . "\n";
