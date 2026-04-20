/**
 * WC Artisan Tools — Admin Dashboard JS
 *
 * Handles:
 * - Media uploader for product photos
 * - Title auto-suggestion from taxonomy dropdowns
 *
 * @package WC_Artisan_Tools
 */
( function ( $ ) {
	'use strict';

	/* =====================================================================
	   Media Uploader
	   ===================================================================== */

	var frame;

	$( '#wcat-upload-btn' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: 'Select Product Photo',
			button: { text: 'Use This Photo' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium
				? attachment.sizes.medium.url
				: attachment.url;

			$( '#wcat-featured-image' ).val( attachment.id );
			$( '#wcat-image-preview' )
				.html( '<img src="' + url + '" alt="">' )
				.show();
			$( '#wcat-upload-btn' ).text( 'Change Photo' );

			// Add remove button if not present.
			if ( ! $( '#wcat-remove-image-btn' ).length ) {
				$( '#wcat-upload-btn' ).after(
					' <button type="button" class="button wcat-button--danger" id="wcat-remove-image-btn">Remove</button>'
				);
				bindRemoveBtn();
			}
		} );

		frame.open();
	} );

	function bindRemoveBtn() {
		$( document ).on( 'click', '#wcat-remove-image-btn', function ( e ) {
			e.preventDefault();
			$( '#wcat-featured-image' ).val( '' );
			$( '#wcat-image-preview' ).hide().html( '' );
			$( '#wcat-upload-btn' ).text( 'Upload Photo' );
			$( this ).remove();
		} );
	}

	bindRemoveBtn();

	/* =====================================================================
	   Title Auto-Suggestion
	   ===================================================================== */

	var $title = $( '#wcat-title' );
	var lastAutoTitle = '';
	var userEdited = false;

	// Track if user has manually edited the title.
	$title.on( 'input', function () {
		var current = $title.val();
		if ( current !== lastAutoTitle ) {
			userEdited = true;
		}
	} );

	// When taxonomy dropdowns change, suggest a title.
	$( '[data-title-source]' ).on( 'change', function () {
		if ( userEdited && $title.val() !== '' && $title.val() !== lastAutoTitle ) {
			return; // User has customised the title — don't overwrite.
		}

		var materialEl = $( '[data-title-source="material"]' );
		var typeEl = $( '[data-title-source="type"]' );

		var material = materialEl.length ? materialEl.find( 'option:selected' ).text().trim() : '';
		var type = typeEl.length ? typeEl.find( 'option:selected' ).text().trim() : '';

		// Skip placeholder options.
		if ( material === 'Select...' ) material = '';
		if ( type === 'Select type...' ) type = '';

		var parts = [];
		if ( material ) parts.push( material );
		if ( type ) parts.push( type );

		if ( parts.length > 0 ) {
			var suggested = parts.join( ' ' );
			$title.val( suggested );
			lastAutoTitle = suggested;
			userEdited = false;
		}
	} );

} )( jQuery );
