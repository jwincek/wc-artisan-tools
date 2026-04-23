<?php
declare(strict_types=1);
/**
 * Commission request form — server-side render.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 *
 * @package WC_Artisan_Tools
 */

use WC_Artisan_Tools\Core\Config;
use WC_Artisan_Tools\Commission\Commission_Handler;

// Check if commissions are enabled.
$settings = get_option( 'wcat_settings', [] );
if ( empty( $settings['commission_enabled'] ?? true ) ) {
	return;
}

$heading     = $attributes['heading'] ?? __( 'Request a Custom Piece', 'wc-artisan-tools' );
$description = $attributes['description'] ?? '';
$show_budget = $attributes['showBudget'] ?? true;
$show_deadline = $attributes['showDeadline'] ?? true;

// Get taxonomy terms for dropdowns.
$product_types = get_terms( [ 'taxonomy' => 'wcat_product_type', 'hide_empty' => false ] );
$materials     = get_terms( [ 'taxonomy' => 'wcat_material', 'hide_empty' => false ] );

// Craft profile labels.
$craft  = Config::get_active_craft_profile();
$labels = $craft['taxonomy_labels'] ?? [];
$material_label = $labels['wcat_material']['singular'] ?? __( 'Material', 'wc-artisan-tools' );

// Budget ranges from config.
$budget_ranges = Config::get_item( 'settings', 'budget_ranges', [] );

// Handle form submission.
$form_success = false;
$form_error   = '';

if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['wcat_commission_form_nonce'] ) ) {
	if ( ! wp_verify_nonce( $_POST['wcat_commission_form_nonce'], 'wcat_commission_form' ) ) {
		$form_error = __( 'Invalid form submission. Please try again.', 'wc-artisan-tools' );
	} else {
		// Run spam check if available.
		$spam_result = apply_filters( 'wcat_commission_spam_check', true, $_POST );

		if ( is_wp_error( $spam_result ) ) {
			$form_error = $spam_result->get_error_message();
		} else {
			$result = Commission_Handler::create( $_POST );

			if ( is_wp_error( $result ) ) {
				$form_error = $result->get_error_message();
			} else {
				$form_success = true;
			}
		}
	}
}

$wrapper_attributes = get_block_wrapper_attributes( [
	'class' => 'wcat-commission-form-wrapper',
] );
?>
<div <?php echo $wrapper_attributes; ?>>
	<?php if ( $heading ) : ?>
		<h2 class="wcat-commission-form__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( $description ) : ?>
		<p class="wcat-commission-form__description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>

	<?php if ( $form_success ) : ?>
		<div class="wcat-commission-form__success" role="status" aria-live="polite">
			<p><?php echo esc_html( $attributes['successMessage'] ?? __( 'Thank you! Your commission request has been submitted. We\'ll be in touch soon with a quote.', 'wc-artisan-tools' ) ); ?></p>
		</div>
	<?php else : ?>

		<?php if ( $form_error ) : ?>
			<div class="wcat-commission-form__error" role="alert" id="wcat-form-error">
				<p><?php echo esc_html( $form_error ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" class="wcat-commission-form" novalidate
			  aria-label="<?php esc_attr_e( 'Commission request form', 'wc-artisan-tools' ); ?>"
			  <?php echo $form_error ? 'aria-describedby="wcat-form-error"' : ''; ?>>
			<?php wp_nonce_field( 'wcat_commission_form', 'wcat_commission_form_nonce' ); ?>

			<!-- Name -->
			<div class="wcat-commission-form__field">
				<label for="wcat-customer-name"><?php esc_html_e( 'Your Name', 'wc-artisan-tools' ); ?> <span class="required" aria-hidden="true">*</span></label>
				<input type="text" id="wcat-customer-name" name="customer_name" required
					   autocomplete="name"
					   aria-required="true"
					   value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) ) ); ?>">
			</div>

			<!-- Email -->
			<div class="wcat-commission-form__field">
				<label for="wcat-customer-email"><?php esc_html_e( 'Your Email', 'wc-artisan-tools' ); ?> <span class="required" aria-hidden="true">*</span></label>
				<input type="email" id="wcat-customer-email" name="customer_email" required
					   autocomplete="email"
					   aria-required="true"
					   value="<?php echo esc_attr( sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ) ); ?>">
			</div>

			<!-- Craft Type -->
			<?php if ( ! is_wp_error( $product_types ) && ! empty( $product_types ) ) : ?>
				<div class="wcat-commission-form__field">
					<label for="wcat-craft-type"><?php esc_html_e( 'What type of piece?', 'wc-artisan-tools' ); ?></label>
					<select id="wcat-craft-type" name="craft_type">
						<option value=""><?php esc_html_e( 'Select...', 'wc-artisan-tools' ); ?></option>
						<?php foreach ( $product_types as $term ) : ?>
							<option value="<?php echo esc_attr( $term->name ); ?>"
								<?php selected( ( $_POST['craft_type'] ?? '' ), $term->name ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<!-- Material Preference -->
			<?php if ( ! is_wp_error( $materials ) && ! empty( $materials ) ) : ?>
				<div class="wcat-commission-form__field">
					<label for="wcat-material-pref"><?php echo esc_html( $material_label ); ?> <?php esc_html_e( 'preference', 'wc-artisan-tools' ); ?></label>
					<select id="wcat-material-pref" name="material_pref">
						<option value=""><?php esc_html_e( 'No preference', 'wc-artisan-tools' ); ?></option>
						<?php foreach ( $materials as $term ) : ?>
							<option value="<?php echo esc_attr( $term->name ); ?>"
								<?php selected( ( $_POST['material_pref'] ?? '' ), $term->name ); ?>>
								<?php echo esc_html( $term->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<!-- Description -->
			<div class="wcat-commission-form__field">
				<label for="wcat-comm-description"><?php esc_html_e( 'Describe what you\'re looking for', 'wc-artisan-tools' ); ?> <span class="required" aria-hidden="true">*</span></label>
				<textarea id="wcat-comm-description" name="description" rows="4" required
						  aria-required="true"><?php
					echo esc_textarea( wp_unslash( $_POST['description'] ?? '' ) );
				?></textarea>
			</div>

			<!-- Budget Range -->
			<?php if ( $show_budget && ! empty( $budget_ranges ) ) : ?>
				<div class="wcat-commission-form__field">
					<label for="wcat-budget"><?php esc_html_e( 'Budget Range', 'wc-artisan-tools' ); ?></label>
					<select id="wcat-budget" name="budget_range">
						<option value=""><?php esc_html_e( 'Select...', 'wc-artisan-tools' ); ?></option>
						<?php foreach ( $budget_ranges as $range ) : ?>
							<option value="<?php echo esc_attr( $range['label'] ); ?>"
								<?php selected( ( $_POST['budget_range'] ?? '' ), $range['label'] ); ?>>
								<?php echo esc_html( $range['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<!-- Deadline / Occasion -->
			<?php if ( $show_deadline ) : ?>
				<div class="wcat-commission-form__field">
					<label for="wcat-deadline"><?php esc_html_e( 'Occasion or Deadline (optional)', 'wc-artisan-tools' ); ?></label>
					<input type="text" id="wcat-deadline" name="deadline"
						   placeholder="<?php esc_attr_e( 'e.g., Anniversary in March', 'wc-artisan-tools' ); ?>"
						   value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) ) ); ?>">
				</div>
			<?php endif; ?>

			<!-- Display Name Checkbox -->
			<div class="wcat-commission-form__field wcat-commission-form__field--checkbox">
				<label for="wcat-display-name">
					<input type="checkbox" id="wcat-display-name" name="display_name" value="1"
						<?php checked( ! empty( $_POST['display_name'] ) ); ?>>
					<?php esc_html_e( 'Display my first name on the finished piece listing', 'wc-artisan-tools' ); ?>
				</label>
			</div>

			<!-- Required fields note (screen reader) -->
			<p class="screen-reader-text"><?php esc_html_e( 'Fields marked with * are required.', 'wc-artisan-tools' ); ?></p>

			<div class="wcat-commission-form__submit">
				<button type="submit" class="wp-element-button">
					<?php esc_html_e( 'Submit Request', 'wc-artisan-tools' ); ?>
				</button>
			</div>
		</form>
	<?php endif; ?>
</div>
