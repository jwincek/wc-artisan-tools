( function ( blocks, element, blockEditor, components ) {
	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'wc-artisan-tools/commission-form', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( {
				className: 'wcat-commission-form-wrapper wcat-commission-form-wrapper--editor',
			} );

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
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Your Name *' ),
							el( 'div', { className: 'wcat-commission-form-preview__input' } )
						),
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Your Email *' ),
							el( 'div', { className: 'wcat-commission-form-preview__input' } )
						),
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'What type of piece?' ),
							el( 'div', { className: 'wcat-commission-form-preview__input wcat-commission-form-preview__input--select' } )
						),
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Material preference' ),
							el( 'div', { className: 'wcat-commission-form-preview__input wcat-commission-form-preview__input--select' } )
						),
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Describe what you\'re looking for *' ),
							el( 'div', { className: 'wcat-commission-form-preview__input wcat-commission-form-preview__input--textarea' } )
						),
						attributes.showBudget
							? el( 'div', { className: 'wcat-commission-form-preview__field' },
								el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Budget Range' ),
								el( 'div', { className: 'wcat-commission-form-preview__input wcat-commission-form-preview__input--select' } )
							)
							: null,
						attributes.showDeadline
							? el( 'div', { className: 'wcat-commission-form-preview__field' },
								el( 'span', { className: 'wcat-commission-form-preview__label' }, 'Occasion or Deadline' ),
								el( 'div', { className: 'wcat-commission-form-preview__input' } )
							)
							: null,
						el( 'div', { className: 'wcat-commission-form-preview__field' },
							el( 'label', {},
								el( 'input', { type: 'checkbox', disabled: true } ),
								' Display my first name on the finished piece listing'
							)
						)
					),
					el( 'div', { className: 'wcat-commission-form-preview__submit' },
						el( 'button', {
							className: 'wp-element-button',
							disabled: true,
						}, 'Submit Request' )
					)
				)
			);
		},
	} );
} )( wp.blocks, wp.element, wp.blockEditor, wp.components );
