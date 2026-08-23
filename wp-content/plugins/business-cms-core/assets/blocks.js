/* global wp, BCMS_BLOCKS */
/**
 * Editor registration for the server-rendered blocks.
 *
 * Each block saves nothing (save: null) — the markup is produced in PHP, so
 * the editor shows a compact configuration card rather than a fragile preview
 * that has to be kept byte-identical with the front end.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var C = wp.components;
	var BE = wp.blockEditor;
	var useBlockProps = BE.useBlockProps;
	var InspectorControls = BE.InspectorControls;

	var industries = ( window.BCMS_BLOCKS && window.BCMS_BLOCKS.industries ) || [
		{ label: __( 'All industries', 'business-cms' ), value: '' },
	];

	function placeholder( title, description ) {
		return el(
			'div',
			{ className: 'bcms-block-placeholder' },
			el( 'strong', null, title ),
			el( 'span', null, description )
		);
	}

	wp.blocks.registerBlockType( 'bcms/project-grid', {
		apiVersion: 3,
		title: __( 'Project grid', 'business-cms' ),
		description: __(
			'A filterable grid of portfolio projects. Content comes from Portfolio → Projects.',
			'business-cms'
		),
		icon: 'grid-view',
		category: 'bcms',
		supports: { html: false, anchor: true },
		attributes: {
			heading: { type: 'string', default: '' },
			count: { type: 'number', default: 6 },
			columns: { type: 'number', default: 3 },
			industry: { type: 'string', default: '' },
			showFilter: { type: 'boolean', default: true },
			featuredOnly: { type: 'boolean', default: false },
			showSummary: { type: 'boolean', default: true },
			ctaText: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			var summary = [
				a.featuredOnly
					? __( 'Featured projects only', 'business-cms' )
					: __( 'Latest projects', 'business-cms' ),
				a.count + ' ' + __( 'shown', 'business-cms' ),
				a.columns + ' ' + __( 'columns', 'business-cms' ),
				a.industry
					? __( 'filtered to', 'business-cms' ) + ' ' + a.industry
					: __( 'all industries', 'business-cms' ),
			].join( ' · ' );

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						C.PanelBody,
						{ title: __( 'Grid', 'business-cms' ), initialOpen: true },
						el( C.TextControl, {
							label: __( 'Heading', 'business-cms' ),
							value: a.heading,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { heading: v } );
							},
						} ),
						el( C.RangeControl, {
							label: __( 'Projects to show', 'business-cms' ),
							value: a.count,
							min: 1,
							max: 24,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { count: v } );
							},
						} ),
						el( C.RangeControl, {
							label: __( 'Columns', 'business-cms' ),
							value: a.columns,
							min: 1,
							max: 4,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { columns: v } );
							},
						} ),
						el( C.SelectControl, {
							label: __( 'Industry', 'business-cms' ),
							value: a.industry,
							options: industries,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { industry: v } );
							},
						} ),
						el( C.ToggleControl, {
							label: __( 'Show the industry filter', 'business-cms' ),
							checked: a.showFilter,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { showFilter: v } );
							},
						} ),
						el( C.ToggleControl, {
							label: __( 'Featured projects only', 'business-cms' ),
							help: __(
								'Uses the "Feature on the homepage" switch on each project.',
								'business-cms'
							),
							checked: a.featuredOnly,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { featuredOnly: v } );
							},
						} ),
						el( C.ToggleControl, {
							label: __( 'Show one-line summaries', 'business-cms' ),
							checked: a.showSummary,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { showSummary: v } );
							},
						} ),
						el( C.TextControl, {
							label: __( 'Button under the grid', 'business-cms' ),
							help: __( 'Leave empty for no button.', 'business-cms' ),
							value: a.ctaText,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { ctaText: v } );
							},
						} )
					)
				),
				placeholder( a.heading || __( 'Project grid', 'business-cms' ), summary )
			);
		},
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'bcms/project-stats', {
		apiVersion: 3,
		title: __( 'Headline results', 'business-cms' ),
		description: __(
			'The results band for this case study. Edit the figures in Project details in the sidebar.',
			'business-cms'
		),
		icon: 'chart-bar',
		category: 'bcms',
		supports: { html: false, anchor: true, multiple: false },
		attributes: { align: { type: 'string', default: '' } },
		edit: function () {
			var stats = [];
			try {
				var meta = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
				stats = JSON.parse( meta._bcms_stats || '[]' );
			} catch ( e ) {
				stats = [];
			}

			var text = stats.length
				? stats
						.map( function ( s ) {
							return ( s.value || '?' ) + ' ' + ( s.label || '' );
						} )
						.join( '  ·  ' )
				: __(
						'No figures yet — add them under Project details in the sidebar.',
						'business-cms'
				  );

			return el(
				'div',
				useBlockProps(),
				placeholder( __( 'Headline results', 'business-cms' ), text )
			);
		},
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'bcms/project-facts', {
		apiVersion: 3,
		title: __( 'Project facts', 'business-cms' ),
		description: __(
			'Client, industry, services, location, year and duration, as a definition list.',
			'business-cms'
		),
		icon: 'list-view',
		category: 'bcms',
		supports: { html: false, anchor: true, multiple: false },
		attributes: {},
		edit: function () {
			return el(
				'div',
				useBlockProps(),
				placeholder(
					__( 'Project facts', 'business-cms' ),
					__(
						'Pulled from Project details and the Industry / Service taxonomies.',
						'business-cms'
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );

	wp.blocks.registerBlockType( 'bcms/contact-form', {
		apiVersion: 3,
		title: __( 'Contact form', 'business-cms' ),
		description: __(
			'Enquiry form with honeypot, timing and optional Turnstile spam filtering.',
			'business-cms'
		),
		icon: 'email-alt',
		category: 'bcms',
		supports: { html: false, anchor: true },
		attributes: {
			heading: { type: 'string', default: '' },
			buttonLabel: { type: 'string', default: '' },
			showCompany: { type: 'boolean', default: true },
			showPhone: { type: 'boolean', default: false },
			successMessage: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						C.PanelBody,
						{ title: __( 'Form', 'business-cms' ), initialOpen: true },
						el( C.TextControl, {
							label: __( 'Heading', 'business-cms' ),
							value: a.heading,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { heading: v } );
							},
						} ),
						el( C.TextControl, {
							label: __( 'Button label', 'business-cms' ),
							placeholder: __( 'Send enquiry', 'business-cms' ),
							value: a.buttonLabel,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { buttonLabel: v } );
							},
						} ),
						el( C.ToggleControl, {
							label: __( 'Company field', 'business-cms' ),
							checked: a.showCompany,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { showCompany: v } );
							},
						} ),
						el( C.ToggleControl, {
							label: __( 'Phone field', 'business-cms' ),
							checked: a.showPhone,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { showPhone: v } );
							},
						} ),
						el( C.TextareaControl, {
							label: __( 'Message after sending', 'business-cms' ),
							value: a.successMessage,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								set( { successMessage: v } );
							},
						} )
					)
				),
				placeholder(
					a.heading || __( 'Contact form', 'business-cms' ),
					__(
						'Name, email, message. Enquiries appear under Enquiries and are sent to your CRM.',
						'business-cms'
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
