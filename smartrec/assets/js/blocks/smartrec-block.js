( function () {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.blockEditor ) { return; }

	var el                = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var TextControl       = wp.components.TextControl;
	var RangeControl      = wp.components.RangeControl;
	var ToggleControl     = wp.components.ToggleControl;
	var __                = wp.i18n.__;

	var blockTypes = [
		{ value: 'for_you',           label: __( 'Recommended For You', 'smartrec' ) },
		{ value: 'recently_viewed',   label: __( 'Recently Viewed', 'smartrec' ) },
		{ value: 'trending',          label: __( 'Trending Now', 'smartrec' ) },
		{ value: 'similar_to_viewed', label: __( 'Related To Viewed', 'smartrec' ) },
		{ value: 'bought_together',   label: __( 'Customers Also Bought', 'smartrec' ) },
		{ value: 'new_arrivals',      label: __( 'New Arrivals', 'smartrec' ) },
	];

	var layouts = [
		{ value: 'grid',    label: __( 'Grid', 'smartrec' ) },
		{ value: 'slider',  label: __( 'Slider', 'smartrec' ) },
		{ value: 'list',    label: __( 'List', 'smartrec' ) },
		{ value: 'minimal', label: __( 'Minimal', 'smartrec' ) },
	];

	/* Fetch WC categories on first load */
	var categoryOptions = [ { value: '', label: __( 'All categories', 'smartrec' ) } ];
	var categoriesLoaded = false;

	function loadCategories( callback ) {
		if ( categoriesLoaded ) { callback(); return; }
		wp.apiFetch( { path: '/wc/v3/products/categories?per_page=100&hide_empty=true' } ).then( function ( cats ) {
			cats.forEach( function ( cat ) {
				categoryOptions.push( { value: String( cat.id ), label: cat.name + ' (' + cat.count + ')' } );
			} );
			categoriesLoaded = true;
			callback();
		} ).catch( function () {
			categoriesLoaded = true;
			callback();
		} );
	}

	registerBlockType( 'smartrec/recommendations', {
		title: 'SmartRec — ' + __( 'Product Recommendations', 'smartrec' ),
		description: __( 'Display intelligent product recommendations filtered by category and recommendation engine.', 'smartrec' ),
		category: 'widgets',
		icon: { src: 'products', foreground: '#7f54b3' },
		keywords: [ 'smartrec', 'products', 'recommendations', 'woocommerce', 'related', 'trending' ],
		supports: { html: false, align: [ 'wide', 'full' ] },

		edit: function ( props ) {
			var a    = props.attributes;
			var set  = props.setAttributes;

			/* Load categories */
			var forceUpdate = wp.element.useState( 0 )[ 1 ];
			wp.element.useEffect( function () {
				loadCategories( function () { forceUpdate( Date.now() ); } );
			}, [] );

			var selectedType = blockTypes.filter( function(t) { return t.value === a.blockType; } )[0];
			var typeName     = selectedType ? selectedType.label : a.blockType;
			var catName      = '';
			if ( a.category ) {
				var found = categoryOptions.filter( function(c) { return c.value === a.category; } )[0];
				catName = found ? found.label : '';
			}

			var sidebar = el( InspectorControls, {},

				el( PanelBody, { title: __( 'Recommendation Type', 'smartrec' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Block Type', 'smartrec' ),
						value: a.blockType,
						options: blockTypes,
						onChange: function ( v ) { set( { blockType: v } ); },
						help: __( 'Each type uses a different recommendation engine.', 'smartrec' ),
					} ),
					el( TextControl, {
						label: __( 'Custom Title', 'smartrec' ),
						value: a.title,
						onChange: function ( v ) { set( { title: v } ); },
						placeholder: __( 'Leave empty for default', 'smartrec' ),
					} )
				),

				el( PanelBody, { title: __( 'Category Filter', 'smartrec' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Filter by Category', 'smartrec' ),
						value: a.category,
						options: categoryOptions,
						onChange: function ( v ) { set( { category: v } ); },
						help: __( 'Show recommendations only from this category. Great for "Trending in Electronics", "For you in Home", etc.', 'smartrec' ),
					} )
				),

				el( PanelBody, { title: __( 'Layout', 'smartrec' ), initialOpen: false },
					el( SelectControl, {
						label: __( 'Layout Style', 'smartrec' ),
						value: a.layout,
						options: layouts,
						onChange: function ( v ) { set( { layout: v } ); },
					} ),
					el( RangeControl, {
						label: __( 'Products to Show', 'smartrec' ),
						value: a.limit, onChange: function ( v ) { set( { limit: v } ); },
						min: 1, max: 20,
					} )
				),

				el( PanelBody, { title: __( 'Columns (Responsive)', 'smartrec' ), initialOpen: false },
					el( RangeControl, { label: __( 'Desktop', 'smartrec' ), value: a.columns, onChange: function ( v ) { set( { columns: v } ); }, min: 1, max: 6 } ),
					el( RangeControl, { label: __( 'Tablet', 'smartrec' ), value: a.columnsTablet, onChange: function ( v ) { set( { columnsTablet: v } ); }, min: 1, max: 6 } ),
					el( RangeControl, { label: __( 'Mobile', 'smartrec' ), value: a.columnsMobile, onChange: function ( v ) { set( { columnsMobile: v } ); }, min: 1, max: 4 } )
				),

				el( PanelBody, { title: __( 'Display Elements', 'smartrec' ), initialOpen: false },
					el( ToggleControl, { label: __( 'Price', 'smartrec' ), checked: a.showPrice, onChange: function ( v ) { set( { showPrice: v } ); } } ),
					el( ToggleControl, { label: __( 'Rating', 'smartrec' ), checked: a.showRating, onChange: function ( v ) { set( { showRating: v } ); } } ),
					el( ToggleControl, { label: __( 'Add to Cart', 'smartrec' ), checked: a.showAddToCart, onChange: function ( v ) { set( { showAddToCart: v } ); } } ),
					el( ToggleControl, { label: __( 'Reason Badge', 'smartrec' ), checked: a.showReason, onChange: function ( v ) { set( { showReason: v } ); } } )
				),

				el( PanelBody, { title: __( 'Load More', 'smartrec' ), initialOpen: false },
					el( RangeControl, {
						label: __( 'Products per click (0 = off)', 'smartrec' ),
						value: a.loadMore, onChange: function ( v ) { set( { loadMore: v } ); },
						min: 0, max: 20,
					} )
				)
			);

			/* Editor placeholder — clean summary instead of broken SSR preview */
			var details = [];
			details.push( a.limit + ' ' + __( 'products', 'smartrec' ) );
			details.push( a.columns + ' ' + __( 'columns', 'smartrec' ) );
			details.push( a.layout );
			if ( catName ) { details.push( catName ); }
			if ( a.loadMore > 0 ) { details.push( __( 'Load More', 'smartrec' ) + ': +' + a.loadMore ); }

			var displayTitle = a.title || typeName;
			if ( catName ) {
				displayTitle += ' — ' + catName;
			}

			var placeholder = el( 'div', {
					className: 'smartrec-block-placeholder',
					style: {
						border: '2px dashed #7f54b3',
						borderRadius: '8px',
						padding: '24px',
						textAlign: 'center',
						background: '#faf8fc',
					},
				},
				el( 'div', { style: { fontSize: '14px', fontWeight: 600, color: '#7f54b3', marginBottom: '6px' } },
					'SmartRec'
				),
				el( 'div', { style: { fontSize: '18px', fontWeight: 600, color: '#1d2327', marginBottom: '8px' } },
					displayTitle
				),
				el( 'div', { style: { fontSize: '13px', color: '#646970' } },
					details.join( '  ·  ' )
				),
				el( 'div', { style: { fontSize: '11px', color: '#8c8f94', marginTop: '12px' } },
					__( 'Product recommendations will render on the published page with your theme\'s styles.', 'smartrec' )
				)
			);

			return el( 'div', { className: props.className },
				sidebar,
				placeholder
			);
		},

		save: function () { return null; },
	} );
} )();
