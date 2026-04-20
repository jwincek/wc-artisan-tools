( function ( blocks, element, blockEditor, components, data ) {
	var el = element.createElement;
	var useState = element.useState;
	var useEffect = element.useEffect;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;
	var Spinner = components.Spinner;
	var useSelect = data.useSelect;

	/**
	 * Hook to fetch taxonomy terms from the REST API.
	 *
	 * @param {string} taxonomy Taxonomy slug.
	 * @return {{ terms: Array, isLoading: boolean }}
	 */
	function useTaxonomyTerms( taxonomy ) {
		return useSelect( function ( select ) {
			var store = select( 'core' );
			var query = { per_page: -1, hide_empty: false, context: 'view' };
			var terms = store.getEntityRecords( 'taxonomy', taxonomy, query );
			var isLoading = store.isResolving( 'getEntityRecords', [ 'taxonomy', taxonomy, query ] );

			return {
				terms: terms || [],
				isLoading: isLoading,
			};
		}, [ taxonomy ] );
	}

	/**
	 * Renders a live dropdown preview with real taxonomy terms.
	 */
	function TermDropdownPreview( props ) {
		var label = props.label;
		var termsData = props.termsData;
		var placeholder = props.placeholder || 'Select...';

		if ( termsData.isLoading ) {
			return el( 'div', { className: 'wcat-commission-form-preview__field' },
				el( 'span', { className: 'wcat-commission-form-preview__label' }, label ),
				el( 'div', { className: 'wcat-commission-form-preview__input', style: { display: 'flex', alignItems: 'center', paddingLeft: '12px' } },
					el( Spinner, {} )
				)
			);
		}

		if ( ! termsData.terms.length ) {
			return null;
		}

		return el( 'div', { className: 'wcat-commission-form-preview__field' },
			el( 'span', { className: 'wcat-commission-form-preview__label' }, label ),
			el( 'select', {
				className: 'wcat-commission-form-preview__select',
				disabled: true,
			},
				el( 'option', {}, placeholder ),
				termsData.terms.map( function ( term ) {
					return el( 'option', { key: term.id, value: term.slug }, term.name );
				} )
			)
		);
	}

	blocks.registerBlockType( 'wc-artisan-tools/commission-form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( {
				className: 'wcat-commission-form-wrapper wcat-commission-form-wrapper--editor',
			} );

			// Fetch live taxonomy terms.
			var productTypes = useTaxonomyTerms( 'wcat_product_type' );
			var materials = useTaxonomyTerms( 'wcat_material' );

			return el(
				'div',
				blockProps,

				// Inspector controls (sidebar).
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Form Settings', initialOpen: true },
						el( TextControl, {
							label: 'Heading',
							value: attributes.heading,
							onChange: function ( val ) {
								setAttributes( { heading: val } );
							},
						} ),
						el( TextareaControl, {
							label: 'Description',
							value: attributes.description,
							onChange: function ( val ) {
								setAttributes( { description: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show Budget Range',
							checked: attributes.showBudget,
							onChange: function ( val ) {
								setAttributes( { showBudget: val } );
							},
						} ),
						el( ToggleControl, {
							label: 'Show Deadline Field',
							checked: attributes.showDeadline,
							onChange: function ( val ) {
								setAttributes( { showDeadline: val } );
							},
						} )
					),
					el(
						PanelBody,
						{ title: 'Success Message', initialOpen: false },
						el( TextareaControl, {
							label: 'Message shown after submission',
							value: attributes.successMessage,
							onChange: function ( val ) {
								setAttributes( { successMessage: val } );
							},
							help: 'Displayed to the customer after they submit a commission request.',
						} )
					)
				),

				// Editor preview.
				el( 'div', { className: 'wcat-commission-form-preview' },
					attributes.heading
						? el( 'h2', {}, attributes.heading )
						: null,
					attributes.description
						? el( 'p', { className: 'wcat-commission-form__description' }, attributes.description )
						: null,
					el( 'div', { className: 'wcat-commission-form-preview__fields' },

						// Name.
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Your Name *' ),
							el( 'input', {
								type: 'text',
								className: 'wcat-commission-form-preview__text-input',
								disabled: true,
								placeholder: 'Jane Smith',
							} )
						),

						// Email.
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Your Email *' ),
							el( 'input', {
								type: 'email',
								className: 'wcat-commission-form-preview__text-input',
								disabled: true,
								placeholder: 'jane@example.com',
							} )
						),

						// Product Type — live terms.
						el( TermDropdownPreview, {
							label: 'What type of piece?',
							termsData: productTypes,
							placeholder: 'Select...',
						} ),

						// Material — live terms.
						el( TermDropdownPreview, {
							label: 'Material preference',
							termsData: materials,
							placeholder: 'No preference',
						} ),

						// Description.
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Describe what you\'re looking for *' ),
							el( 'textarea', {
								className: 'wcat-commission-form-preview__textarea-input',
								disabled: true,
								rows: 3,
								placeholder: 'I\'d love a handmade...',
							} )
						),

						// Budget.
						attributes.showBudget
							? el( 'div', { className: 'wcat-commission-form-preview__field' },
								el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Budget Range' ),
								el( 'select', {
									className: 'wcat-commission-form-preview__select',
									disabled: true,
								},
									el( 'option', {}, 'Select...' ),
									el( 'option', {}, 'Under $50' ),
									el( 'option', {}, '$50 \u2013 $100' ),
									el( 'option', {}, '$100 \u2013 $200' ),
									el( 'option', {}, '$200 \u2013 $500' ),
									el( 'option', {}, '$500+' ),
									el( 'option', {}, 'No preference' )
								)
							)
							: null,

						// Deadline.
						attributes.showDeadline
							? el( 'div', { className: 'wcat-commission-form-preview__field' },
								el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Occasion or Deadline (optional)' ),
								el( 'input', {
									type: 'text',
									className: 'wcat-commission-form-preview__text-input',
									disabled: true,
									placeholder: 'e.g., Anniversary in March',
								} )
							)
							: null,

						// Display name checkbox.
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'label', { className: 'wcat-commission-form-preview__checkbox-label' },
								el( 'input', { type: 'checkbox', disabled: true } ),
								' Display my first name on the finished piece listing'
							)
						)
					),

					// Submit button.
					el( 'div', { className: 'wcat-commission-form-preview__submit' },
						el( 'button', {
							className: 'wp-element-button',
							disabled: true,
						}, 'Submit Request' )
					),

					// Success message preview.
					attributes.successMessage
						? el( 'div', { className: 'wcat-commission-form-preview__success-preview' },
							el( 'span', { className: 'wcat-commission-form-preview__success-label' }, 'Success message preview:' ),
							el( 'div', { className: 'wcat-commission-form__success' },
								el( 'p', {}, attributes.successMessage )
							)
						)
						: null
				)
			);
		},
	} );
} )( wp.blocks, wp.element, wp.blockEditor, wp.components, wp.data );
