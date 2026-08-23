/* global wp, BCMS_EDITOR */
/**
 * Project details sidebar.
 *
 * Deliberately written against the wp.* globals with no build step: the site
 * has to stay maintainable by whoever inherits it, and a missing node_modules
 * folder should never be the reason an edit cannot be made.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! BCMS_EDITOR ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var __ = wp.i18n.__;

	var PluginDocumentSettingPanel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	var C = wp.components;

	function useMeta() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		function set( key, value ) {
			var next = {};
			next[ key ] = value;
			editPost( { meta: next } );
		}

		return { postType: postType, meta: meta, set: set };
	}

	function StatsRepeater( props ) {
		var rows = [];
		try {
			rows = JSON.parse( props.value || '[]' );
		} catch ( e ) {
			rows = [];
		}
		if ( ! Array.isArray( rows ) ) {
			rows = [];
		}

		function commit( next ) {
			props.onChange( JSON.stringify( next ) );
		}

		function update( index, field, value ) {
			var next = rows.slice();
			next[ index ] = Object.assign( {}, next[ index ] );
			next[ index ][ field ] = value;
			commit( next );
		}

		var children = rows.map( function ( row, index ) {
			return el(
				'div',
				{ className: 'bcms-stat-row', key: 'stat-' + index },
				el( C.TextControl, {
					label: __( 'Figure', 'business-cms' ),
					value: row.value || '',
					__nextHasNoMarginBottom: true,
					onChange: function ( v ) {
						update( index, 'value', v );
					},
				} ),
				el( C.TextControl, {
					label: __( 'Label', 'business-cms' ),
					value: row.label || '',
					__nextHasNoMarginBottom: true,
					onChange: function ( v ) {
						update( index, 'label', v );
					},
				} ),
				el(
					C.Button,
					{
						isDestructive: true,
						variant: 'link',
						onClick: function () {
							commit(
								rows.filter( function ( _r, i ) {
									return i !== index;
								} )
							);
						},
					},
					__( 'Remove', 'business-cms' )
				)
			);
		} );

		children.push(
			el(
				C.Button,
				{
					key: 'add',
					variant: 'secondary',
					disabled: rows.length >= 4,
					onClick: function () {
						commit( rows.concat( [ { value: '', label: '' } ] ) );
					},
				},
				rows.length >= 4
					? __( 'Four is the maximum', 'business-cms' )
					: __( 'Add a result', 'business-cms' )
			)
		);

		children.unshift(
			el(
				'p',
				{ key: 'help', className: 'bcms-panel-help' },
				__(
					'Up to four headline numbers, shown as a band on the case study.',
					'business-cms'
				)
			)
		);

		return el( 'div', { className: 'bcms-stats-repeater' }, children );
	}

	function Panel() {
		var state = useMeta();

		if ( state.postType !== BCMS_EDITOR.postType ) {
			return null;
		}

		var controls = BCMS_EDITOR.fields.map( function ( field ) {
			if ( field.type === 'boolean' ) {
				return el( C.ToggleControl, {
					key: field.key,
					label: field.label,
					help: field.help || undefined,
					checked: !! state.meta[ field.key ],
					__nextHasNoMarginBottom: true,
					onChange: function ( v ) {
						state.set( field.key, !! v );
					},
				} );
			}

			var Control = field.multiline ? C.TextareaControl : C.TextControl;

			return el( Control, {
				key: field.key,
				label: field.label,
				help: field.help || undefined,
				value: state.meta[ field.key ] || '',
				__nextHasNoMarginBottom: true,
				onChange: function ( v ) {
					state.set( field.key, v );
				},
			} );
		} );

		controls.push(
			el(
				'div',
				{ key: 'stats-wrap', className: 'bcms-panel-section' },
				el( 'h3', { className: 'bcms-panel-heading' }, __( 'Headline results', 'business-cms' ) ),
				el( StatsRepeater, {
					value: state.meta._bcms_stats || '',
					onChange: function ( v ) {
						state.set( '_bcms_stats', v );
					},
				} )
			)
		);

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'bcms-project-details',
				title: BCMS_EDITOR.panel,
				className: 'bcms-project-panel',
			},
			el( Fragment, null, controls )
		);
	}

	wp.plugins.registerPlugin( 'bcms-project-details', { render: Panel, icon: null } );
} )( window.wp );
