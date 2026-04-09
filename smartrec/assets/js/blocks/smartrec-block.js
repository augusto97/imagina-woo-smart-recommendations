( function () {
	'use strict';

	var el                = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender  = wp.serverSideRender || wp.components.ServerSideRender;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var TextControl       = wp.components.TextControl;
	var RangeControl      = wp.components.RangeControl;
	var ToggleControl     = wp.components.ToggleControl;
	var Placeholder       = wp.components.Placeholder;
	var __                = wp.i18n.__;

	var blockTypes = [
		{ value: 'for_you',           label: __( 'Recommended For You', 'smartrec' ) },
		{ value: 'recently_viewed',   label: __( 'Recently Viewed', 'smartrec' ) },
		{ value: 'trending',          label: __( 'Trending Now', 'smartrec' ) },
		{ value: 'similar_to_viewed', label: __( 'Related To Viewed', 'smartrec' ) },
		{ value: 'bought_together',   label: __( 'Customers Also Bought', 'smartrec' ) },
		{ value: 'new_arrivals',      label: __( 'New Arrivals', 'smartrec' ) },
		{ value: 'custom',            label: __( 'Custom (advanced)', 'smartrec' ) },
	];

	var layouts = [
		{ value: 'grid',    label: __( 'Grid', 'smartrec' ) },
		{ value: 'slider',  label: __( 'Slider', 'smartrec' ) },
		{ value: 'list',    label: __( 'List', 'smartrec' ) },
		{ value: 'minimal', label: __( 'Minimal', 'smartrec' ) },
	];

	registerBlockType( 'smartrec/recommendations', {
		title: 'SmartRec — ' + __( 'Product Recommendations', 'smartrec' ),
		description: __( 'Display intelligent product recommendations. Choose from multiple engines like Trending, Recently Viewed, Personalized, and more.', 'smartrec' ),
		category: 'widgets',
		icon: {
			src: 'products',
			foreground: '#7f54b3',
		},
		keywords: [
			'smartrec',
			__( 'products', 'smartrec' ),
			__( 'recommendations', 'smartrec' ),
			__( 'woocommerce', 'smartrec' ),
			__( 'related', 'smartrec' ),
			__( 'trending', 'smartrec' ),
		],
		supports: {
			html: false,
			align: [ 'wide', 'full' ],
		},

		edit: function ( props ) {
			var attributes   = props.attributes;
			var setAttributes = props.setAttributes;

			var inspectorControls = el( InspectorControls, {},

				/* Block Type */
				el( PanelBody, { title: __( 'Recommendation Type', 'smartrec' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Block Type', 'smartrec' ),
						value: attributes.blockType,
						options: blockTypes,
						onChange: function ( val ) { setAttributes( { blockType: val } ); },
						help: __( 'Each type uses a different recommendation engine.', 'smartrec' ),
					} ),
					el( TextControl, {
						label: __( 'Custom Title', 'smartrec' ),
						value: attributes.title,
						onChange: function ( val ) { setAttributes( { title: val } ); },
						placeholder: __( 'Leave empty for default', 'smartrec' ),
					} )
				),

				/* Layout */
				el( PanelBody, { title: __( 'Layout', 'smartrec' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Layout Style', 'smartrec' ),
						value: attributes.layout,
						options: layouts,
						onChange: function ( val ) { setAttributes( { layout: val } ); },
					} ),
					el( RangeControl, {
						label: __( 'Products to Show', 'smartrec' ),
						value: attributes.limit,
						onChange: function ( val ) { setAttributes( { limit: val } ); },
						min: 1,
						max: 20,
					} )
				),

				/* Columns */
				el( PanelBody, { title: __( 'Columns (Responsive)', 'smartrec' ), initialOpen: false },
					el( RangeControl, {
						label: __( 'Desktop', 'smartrec' ),
						value: attributes.columns,
						onChange: function ( val ) { setAttributes( { columns: val } ); },
						min: 1,
						max: 6,
					} ),
					el( RangeControl, {
						label: __( 'Tablet', 'smartrec' ),
						value: attributes.columnsTablet,
						onChange: function ( val ) { setAttributes( { columnsTablet: val } ); },
						min: 1,
						max: 6,
					} ),
					el( RangeControl, {
						label: __( 'Mobile', 'smartrec' ),
						value: attributes.columnsMobile,
						onChange: function ( val ) { setAttributes( { columnsMobile: val } ); },
						min: 1,
						max: 4,
					} )
				),

				/* Display Elements */
				el( PanelBody, { title: __( 'Display Elements', 'smartrec' ), initialOpen: false },
					el( ToggleControl, {
						label: __( 'Show Price', 'smartrec' ),
						checked: attributes.showPrice,
						onChange: function ( val ) { setAttributes( { showPrice: val } ); },
					} ),
					el( ToggleControl, {
						label: __( 'Show Rating', 'smartrec' ),
						checked: attributes.showRating,
						onChange: function ( val ) { setAttributes( { showRating: val } ); },
					} ),
					el( ToggleControl, {
						label: __( 'Show Add to Cart', 'smartrec' ),
						checked: attributes.showAddToCart,
						onChange: function ( val ) { setAttributes( { showAddToCart: val } ); },
					} ),
					el( ToggleControl, {
						label: __( 'Show Reason Badge', 'smartrec' ),
						checked: attributes.showReason,
						onChange: function ( val ) { setAttributes( { showReason: val } ); },
					} )
				),

				/* Load More */
				el( PanelBody, { title: __( 'Load More', 'smartrec' ), initialOpen: false },
					el( RangeControl, {
						label: __( 'Products per click (0 = disabled)', 'smartrec' ),
						value: attributes.loadMore,
						onChange: function ( val ) { setAttributes( { loadMore: val } ); },
						min: 0,
						max: 20,
					} )
				),

				/* Advanced */
				el( PanelBody, { title: __( 'Advanced', 'smartrec' ), initialOpen: false },
					el( TextControl, {
						label: __( 'Category ID (optional)', 'smartrec' ),
						value: attributes.category,
						onChange: function ( val ) { setAttributes( { category: val } ); },
						help: __( 'Filter by category ID. Leave empty for all.', 'smartrec' ),
					} )
				)
			);

			/* Preview */
			var preview = el( ServerSideRender, {
				block: 'smartrec/recommendations',
				attributes: attributes,
				EmptyResponsePlaceholder: function () {
					return el( Placeholder, {
						icon: 'star-filled',
						label: __( 'SmartRec Recommendations', 'smartrec' ),
					},
						el( 'p', {},
							__( 'No recommendations available yet. Products will appear once there is tracking data.', 'smartrec' )
						)
					);
				},
			} );

			return el( 'div', { className: props.className },
				inspectorControls,
				preview
			);
		},

		/* No save — server-side rendered */
		save: function () {
			return null;
		},
	} );
} )();
