/* global mw, jQuery */

( function ( mw, $ ) {
	const designer = mw.cosmosBetaThemeDesigner;
	const data = mw.config.get( 'wgCosmosBetaThemeDesigner' );

	if ( !data || !designer || !designer.colors ) {
		return;
	}

	const colors = designer.colors;
	const state = JSON.parse( JSON.stringify( data.settings ) );
	const MODES = [ 'light', 'dark' ];
	const TABS = [ 'themes', 'colors', 'images', 'layout', 'chrome', 'rail', 'darkmode', 'history' ];
	let editing = state.colorMode.default;
	let activeTab = 'themes';
	let $root, $json, $panel, $preview;

	function msg( key ) {
		return mw.message( 'cosmosbeta-themedesigner-' + key );
	}

	function el( tag, attrs, children ) {
		const node = document.createElement( tag );

		Object.keys( attrs || {} ).forEach( ( name ) => {
			if ( name === 'text' ) {
				node.textContent = attrs[ name ];
			} else if ( attrs[ name ] !== false && attrs[ name ] !== null && attrs[ name ] !== undefined ) {
				node.setAttribute( name, attrs[ name ] === true ? '' : attrs[ name ] );
			}
		} );

		( children || [] ).forEach( ( child ) => {
			if ( child ) {
				node.appendChild( child );
			}
		} );

		return node;
	}

	function field( label, input, help ) {
		return el( 'label', { class: 'skin-cosmos-td-field' }, [
			el( 'span', { class: 'skin-cosmos-td-label', text: label } ),
			input,
			help ? el( 'span', { class: 'skin-cosmos-td-help', text: help } ) : null
		] );
	}

	function checkbox( label, checked, onChange, lockedReason ) {
		const locked = !data.canEdit || !!lockedReason;
		const input = el( 'input', { type: 'checkbox', disabled: locked } );
		input.checked = !!checked;
		input.addEventListener( 'change', () => {
			onChange( input.checked );
			sync();
		} );

		return el( 'label', { class: 'skin-cosmos-td-check', title: lockedReason || null }, [
			input,
			el( 'span', { text: label } )
		] );
	}

	function select( options, value, onChange ) {
		const input = el( 'select', { disabled: !data.canEdit } );

		options.forEach( ( option ) => {
			const node = el( 'option', { value: option[ 0 ], text: option[ 1 ] } );
			node.selected = option[ 0 ] === value;
			input.appendChild( node );
		} );

		input.addEventListener( 'change', () => {
			onChange( input.value );
			sync();
		} );

		return input;
	}

	function textInput( value, placeholder, onChange ) {
		const input = el( 'input', {
			type: 'text',
			value: value || '',
			placeholder: placeholder || '',
			disabled: !data.canEdit
		} );

		input.addEventListener( 'input', () => {
			onChange( input.value.trim() );
			sync();
		} );

		return input;
	}

	function listFromText( text, pattern ) {
		return text.split( /[\n,]+/ ).map( ( part ) => part.trim() ).filter( ( part ) => part && ( !pattern || pattern.test( part ) ) );
	}

	function effectiveColor( mode, slot ) {
		return state.palettes[ mode ][ slot ] || data.fallbacks[ mode ][ slot ];
	}

	function sync() {
		$json.val( JSON.stringify( state, null, '\t' ) );
		updatePreview();
	}

	function renderThemes() {
		const grid = el( 'div', { class: 'skin-cosmos-td-presets' } );

		data.presets.forEach( ( preset ) => {
			const colorsList = preset.colors;
			const card = el( 'button', {
				type: 'button',
				class: 'skin-cosmos-td-preset' + ( state.presets[ preset.mode ] === preset.name ? ' is-active' : '' ),
				disabled: !data.canEdit
			}, [
				el( 'span', { class: 'skin-cosmos-td-preset-swatches' }, [ 'banner', 'body', 'content', 'button', 'link' ].map( ( slot ) => {
					const swatch = el( 'span', { class: 'skin-cosmos-td-swatch' } );
					swatch.style.background = colorsList[ slot ];

					return swatch;
				} ) ),
				el( 'span', { class: 'skin-cosmos-td-preset-name', text: msg( 'preset-' + preset.name ).text() } ),
				el( 'span', { class: 'skin-cosmos-td-preset-mode', text: msg( 'mode-' + preset.mode ).text() } )
			] );

			card.addEventListener( 'click', () => {
				state.palettes[ preset.mode ] = Object.assign( {}, colorsList );
				state.presets[ preset.mode ] = preset.name;
				editing = preset.mode;
				mw.notify( msg( 'preset-applied' ).text() );
				render();
				sync();
			} );

			grid.appendChild( card );
		} );

		return el( 'div', {}, [ el( 'p', { text: msg( 'presets-intro' ).text() } ), grid ] );
	}

	function modeSwitch() {
		const wrap = el( 'div', { class: 'skin-cosmos-td-modes' }, [
			el( 'span', { text: msg( 'editing' ).text() } )
		] );

		MODES.forEach( ( mode ) => {
			const button = el( 'button', {
				type: 'button',
				class: 'skin-cosmos-td-chip' + ( editing === mode ? ' is-active' : '' ),
				text: msg( 'mode-' + mode ).text()
			} );

			button.addEventListener( 'click', () => {
				editing = mode;
				render();
				updatePreview();
			} );
			wrap.appendChild( button );
		} );

		return wrap;
	}

	function colorRow( slot ) {
		const current = effectiveColor( editing, slot );
		const parsed = colors.parse( current );
		const picker = el( 'input', {
			type: 'color',
			value: parsed ? colors.toHex( parsed ) : '#000000',
			disabled: !data.canEdit,
			'aria-label': msg( 'color-' + slot ).text()
		} );
		const text = el( 'input', {
			type: 'text',
			value: state.palettes[ editing ][ slot ] || '',
			placeholder: data.fallbacks[ editing ][ slot ],
			disabled: !data.canEdit
		} );
		const error = el( 'span', { class: 'skin-cosmos-td-error', text: '' } );
		const reset = el( 'button', {
			type: 'button',
			class: 'skin-cosmos-td-link',
			text: msg( 'color-reset' ).text(),
			disabled: !data.canEdit
		} );

		function commit( value ) {
			const normal = colors.normalize( value );

			if ( normal === null ) {
				error.textContent = msg( 'color-invalid' ).text();
				return;
			}

			error.textContent = '';
			state.palettes[ editing ][ slot ] = normal;
			state.presets[ editing ] = '';
			sync();
		}

		picker.addEventListener( 'input', () => {
			text.value = picker.value;
			commit( picker.value );
		} );

		text.addEventListener( 'input', () => {
			if ( text.value.trim() === '' ) {
				error.textContent = '';
				delete state.palettes[ editing ][ slot ];
				sync();
				return;
			}

			commit( text.value );
		} );

		text.addEventListener( 'change', () => {
			const normal = colors.normalize( text.value );

			if ( normal ) {
				text.value = normal;
				const next = colors.parse( normal );
				picker.value = colors.toHex( next );
			}
		} );

		reset.addEventListener( 'click', () => {
			delete state.palettes[ editing ][ slot ];
			text.value = '';
			error.textContent = '';
			picker.value = colors.toHex( colors.parse( data.fallbacks[ editing ][ slot ] ) || { r: 0, g: 0, b: 0 } );
			sync();
		} );

		return el( 'div', { class: 'skin-cosmos-td-colorrow' }, [
			el( 'div', { class: 'skin-cosmos-td-colorhead' }, [
				el( 'strong', { text: msg( 'color-' + slot ).text() } ),
				el( 'span', { class: 'skin-cosmos-td-help', text: msg( 'color-' + slot + '-help' ).text() } )
			] ),
			el( 'div', { class: 'skin-cosmos-td-colorinputs' }, [ picker, text, reset ] ),
			error
		] );
	}

	function renderColors() {
		const wrap = el( 'div', {}, [
			modeSwitch(),
			el( 'p', { text: msg( 'colors-intro' ).text() } )
		] );

		data.slots.forEach( ( slot ) => wrap.appendChild( colorRow( slot ) ) );

		return wrap;
	}

	function renderImages() {
		const images = state.images;

		return el( 'div', {}, [
			el( 'p', { text: msg( 'images-intro' ).text() } ),
			field( msg( 'image-wordmark' ).text(), textInput( images.wordmark, msg( 'image-default' ).text(), ( v ) => {
				images.wordmark = v;
			} ) ),
			field( msg( 'image-header' ).text(), textInput( images.header, msg( 'image-default' ).text(), ( v ) => {
				images.header = v;
			} ) ),
			field( msg( 'image-background' ).text(), textInput( images.background, msg( 'image-default' ).text(), ( v ) => {
				images.background = v;
			} ) ),
			field( msg( 'image-size' ).text(), select( [
				[ '', msg( 'inherit' ).text() ],
				[ 'auto', 'auto' ],
				[ 'contain', 'contain' ],
				[ 'cover', 'cover' ]
			], images.backgroundSize, ( v ) => {
				images.backgroundSize = v;
			} ) ),
			field( msg( 'image-repeat' ).text(), select( triState(), images.backgroundRepeat === null ? '' : String( images.backgroundRepeat ), ( v ) => {
				images.backgroundRepeat = v === '' ? null : v === 'true';
			} ) ),
			field( msg( 'image-fixed' ).text(), select( triState(), images.backgroundFixed === null ? '' : String( images.backgroundFixed ), ( v ) => {
				images.backgroundFixed = v === '' ? null : v === 'true';
			} ) )
		] );
	}

	function triState() {
		return [
			[ '', msg( 'inherit' ).text() ],
			[ 'true', mw.msg( 'htmlform-yes' ) ],
			[ 'false', mw.msg( 'htmlform-no' ) ]
		];
	}

	function renderLayout() {
		const layout = state.layout;
		const range = el( 'input', {
			type: 'range',
			min: 0,
			max: 100,
			step: 1,
			value: layout.contentOpacity === null ? data.config.contentOpacity : layout.contentOpacity,
			disabled: !data.canEdit
		} );
		const readout = el( 'output', { text: range.value + '%' } );

		range.addEventListener( 'input', () => {
			layout.contentOpacity = parseInt( range.value, 10 );
			readout.textContent = range.value + '%';
			sync();
		} );

		return el( 'div', {}, [
			field( msg( 'layout-width' ).text(), select( [
				[ '', msg( 'inherit' ).text() ],
				[ 'default', msg( 'layout-width-default' ).text() ],
				[ 'large', msg( 'layout-width-large' ).text() ],
				[ 'full', msg( 'layout-width-full' ).text() ]
			], layout.contentWidth, ( v ) => {
				layout.contentWidth = v;
			} ) ),
			field( msg( 'layout-opacity' ).text(), el( 'span', { class: 'skin-cosmos-td-range' }, [ range, readout ] ) )
		] );
	}

	function renderChrome() {
		const toolbar = state.toolbar;
		const footer = state.footer;
		const hiddenTools = el( 'div', { class: 'skin-cosmos-td-checks' } );
		const hiddenLinks = el( 'div', { class: 'skin-cosmos-td-checks' } );

		data.toolbarItems.forEach( ( item ) => {
			hiddenTools.appendChild( checkbox( item.label, toolbar.hiddenItems.indexOf( item.name ) !== -1, ( on ) => {
				toolbar.hiddenItems = toolbar.hiddenItems.filter( ( name ) => name !== item.name );
				if ( on ) {
					toolbar.hiddenItems.push( item.name );
				}
			} ) );
		} );

		data.footerLinks.forEach( ( item ) => {
			const reason = item.protected ? msg( 'protected' ).text() : '';

			hiddenLinks.appendChild( checkbox(
				item.label,
				!item.protected && footer.hiddenLinks.indexOf( item.name ) !== -1,
				( on ) => {
					footer.hiddenLinks = footer.hiddenLinks.filter( ( name ) => name !== item.name );
					if ( on ) {
						footer.hiddenLinks.push( item.name );
					}
				},
				reason
			) );
		} );

		const opacity = el( 'input', {
			type: 'range',
			min: 0,
			max: 100,
			step: 1,
			value: footer.opacity,
			disabled: !data.canEdit
		} );
		const readout = el( 'output', { text: opacity.value + '%' } );

		opacity.addEventListener( 'input', () => {
			footer.opacity = parseInt( opacity.value, 10 );
			readout.textContent = opacity.value + '%';
			sync();
		} );

		return el( 'div', {}, [
			checkbox( msg( 'toolbar-enabled' ).text(), toolbar.enabled, ( on ) => {
				toolbar.enabled = on;
			} ),
			field( msg( 'toolbar-style' ).text(), select( [
				[ 'floating', msg( 'toolbar-style-floating' ).text() ],
				[ 'bar', msg( 'toolbar-style-bar' ).text() ]
			], toolbar.style, ( v ) => {
				toolbar.style = v;
			} ) ),
			el( 'div', { class: 'skin-cosmos-td-label', text: msg( 'toolbar-hidden' ).text() } ),
			el( 'span', { class: 'skin-cosmos-td-help', text: msg( 'toolbar-hidden-help' ).text() } ),
			hiddenTools,
			field( msg( 'footer-opacity' ).text(), el( 'span', { class: 'skin-cosmos-td-range' }, [ opacity, readout ] ) ),
			checkbox(
				msg( 'footer-icons' ).text(),
				data.canHideFooterIcons ? footer.showIcons : true,
				( on ) => {
					footer.showIcons = on;
				},
				data.canHideFooterIcons ? '' : msg( 'footer-icons-locked' ).text()
			),
			el( 'div', { class: 'skin-cosmos-td-label', text: msg( 'footer-hidden' ).text() } ),
			el( 'span', { class: 'skin-cosmos-td-help', text: msg( 'footer-hidden-help' ).text() } ),
			hiddenLinks
		] );
	}

	function renderRail() {
		const rail = state.rail;
		const namespaces = el( 'textarea', {
			rows: 2,
			placeholder: '-1, 8, 9',
			disabled: !data.canEdit
		} );
		const pages = el( 'textarea', {
			rows: 3,
			placeholder: 'Main Page',
			disabled: !data.canEdit
		} );

		namespaces.value = rail.disabledNamespaces === null ? '' : rail.disabledNamespaces.join( ', ' );
		pages.value = rail.disabledPages === null ? '' : rail.disabledPages.join( '\n' );

		namespaces.addEventListener( 'input', () => {
			rail.disabledNamespaces = namespaces.value.trim() === '' ? null :
				listFromText( namespaces.value, /^-?\d+$/ ).map( Number );
			sync();
		} );

		pages.addEventListener( 'input', () => {
			rail.disabledPages = pages.value.trim() === '' ? null : listFromText( pages.value );
			sync();
		} );

		return el( 'div', {}, [
			checkbox( msg( 'rail-enabled' ).text(), rail.enabled, ( on ) => {
				rail.enabled = on;
			} ),
			checkbox( msg( 'rail-anons' ).text(), rail.hideForAnons, ( on ) => {
				rail.hideForAnons = on;
			} ),
			field( msg( 'rail-recentchanges' ).text(), select( [
				[ '', msg( 'rail-recentchanges-config' ).text() ],
				[ 'off', msg( 'rail-recentchanges-off' ).text() ],
				[ 'normal', msg( 'rail-recentchanges-normal' ).text() ],
				[ 'sticky', msg( 'rail-recentchanges-sticky' ).text() ]
			], rail.recentChanges, ( v ) => {
				rail.recentChanges = v;
			} ) ),
			field( msg( 'rail-namespaces' ).text(), namespaces, msg( 'rail-namespaces-help' ).text() ),
			field( msg( 'rail-pages' ).text(), pages, msg( 'rail-pages-help' ).text() )
		] );
	}

	function renderDarkMode() {
		const mode = state.colorMode;
		const generate = el( 'button', {
			type: 'button',
			class: 'skin-cosmos-td-button',
			text: msg( 'darkmode-generate' ).text(),
			disabled: !data.canEdit
		} );

		generate.addEventListener( 'click', () => {
			state.palettes.dark = colors.deriveDark( state.palettes.light );
			state.presets.dark = '';
			editing = 'dark';
			mw.notify( msg( 'darkmode-generated' ).text() );
			render();
			sync();
		} );

		return el( 'div', {}, [
			el( 'p', { text: msg( 'darkmode-intro' ).text() } ),
			checkbox( msg( 'darkmode-toggle' ).text(), mode.toggle, ( on ) => {
				mode.toggle = on;
			} ),
			field( msg( 'darkmode-default' ).text(), select( [
				[ 'light', msg( 'mode-light' ).text() ],
				[ 'dark', msg( 'mode-dark' ).text() ]
			], mode.default, ( v ) => {
				mode.default = v;
			} ) ),
			el( 'p', { class: 'skin-cosmos-td-help', text: msg( 'darkmode-builtin' ).text() } ),
			generate
		] );
	}

	function renderHistory() {
		const wrap = el( 'div', {}, [ el( 'p', { text: msg( 'history-intro' ).text() } ) ] );

		if ( !data.history.length ) {
			wrap.appendChild( el( 'p', { class: 'skin-cosmos-td-help', text: msg( 'history-empty' ).text() } ) );

			return wrap;
		}

		const list = el( 'ul', { class: 'skin-cosmos-td-history' } );

		data.history.forEach( ( row ) => {
			const live = row.id === data.revisionId;
			const item = el( 'li', {}, [
				el( 'span', { class: 'skin-cosmos-td-history-main', text: row.time + ' \u00b7 ' + row.user } ),
				row.comment ? el( 'span', { class: 'skin-cosmos-td-help', text: row.comment } ) : null
			] );

			if ( live ) {
				item.appendChild( el( 'span', { class: 'skin-cosmos-td-badge', text: msg( 'history-live' ).text() } ) );
			} else if ( data.canEdit ) {
				const button = el( 'button', {
					type: 'button',
					class: 'skin-cosmos-td-link',
					text: msg( 'history-restore' ).text()
				} );

				button.addEventListener( 'click', () => {
					const form = document.getElementById( 'cosmosbeta-themedesigner-form' );
					const input = el( 'input', { type: 'hidden', name: 'wpRevertTo', value: String( row.id ) } );

					form.appendChild( input );
					form.submit();
				} );
				item.appendChild( button );
			}

			list.appendChild( item );
		} );

		wrap.appendChild( list );

		return wrap;
	}

	const RENDERERS = {
		themes: renderThemes,
		colors: renderColors,
		images: renderImages,
		layout: renderLayout,
		chrome: renderChrome,
		rail: renderRail,
		darkmode: renderDarkMode,
		history: renderHistory
	};

	function buildPreview() {
		const part = ( cls, key ) => el( 'div', { class: cls, text: msg( key ).text() } );

		return el( 'div', { class: 'skin-cosmos-td-preview', 'aria-label': msg( 'preview' ).text() }, [
			el( 'div', { class: 'skin-cosmos-td-pv-banner' }, [ el( 'span', { text: msg( 'preview-wordmark' ).text() } ) ] ),
			el( 'div', { class: 'skin-cosmos-td-pv-header' }, [ el( 'span', { text: 'Menu  Menu  Menu' } ) ] ),
			el( 'div', { class: 'skin-cosmos-td-pv-body' }, [
				el( 'div', { class: 'skin-cosmos-td-pv-main' }, [
					part( 'skin-cosmos-td-pv-heading', 'preview-heading' ),
					part( 'skin-cosmos-td-pv-text', 'preview-text' ),
					part( 'skin-cosmos-td-pv-link', 'preview-link' ),
					part( 'skin-cosmos-td-pv-button', 'preview-button' )
				] ),
				part( 'skin-cosmos-td-pv-rail', 'preview-rail' )
			] ),
			part( 'skin-cosmos-td-pv-footer', 'preview-footer' ),
			part( 'skin-cosmos-td-pv-toolbar', 'preview-toolbar' )
		] );
	}

	function updatePreview() {
		if ( !$preview || !$preview.length ) {
			return;
		}

		const mode = editing;
		const color = ( slot ) => effectiveColor( mode, slot );
		const content = color( 'content' );
		const opacity = ( state.layout.contentOpacity === null ? data.config.contentOpacity : state.layout.contentOpacity ) / 100;
		const parsed = colors.parse( content );
		const style = ( selector, props ) => $preview.find( selector ).css( props );

		style( '.skin-cosmos-td-pv-banner', { background: color( 'banner' ), color: colors.readableOn( color( 'banner' ) ) } );
		style( '.skin-cosmos-td-pv-header', { background: color( 'header' ), color: colors.readableOn( color( 'header' ) ) } );
		$preview.css( { background: color( 'body' ) } );
		style( '.skin-cosmos-td-pv-main, .skin-cosmos-td-pv-rail', {
			background: parsed ? 'rgba(' + parsed.r + ',' + parsed.g + ',' + parsed.b + ',' + opacity + ')' : content,
			color: colors.contentTextOn( content )
		} );
		style( '.skin-cosmos-td-pv-link', { color: color( 'link' ) } );
		style( '.skin-cosmos-td-pv-button', { background: color( 'button' ), color: colors.readableOn( color( 'button' ) ) } );
		style( '.skin-cosmos-td-pv-footer', { background: color( 'footer' ), color: colors.readableOn( color( 'footer' ) ) } );
		style( '.skin-cosmos-td-pv-toolbar', {
			background: color( 'toolbar' ),
			color: colors.readableOn( color( 'toolbar' ) ),
			display: state.toolbar.enabled ? '' : 'none'
		} );
		style( '.skin-cosmos-td-pv-rail', { display: state.rail.enabled ? '' : 'none' } );
	}

	function render() {
		const $tabs = $root.find( '.skin-cosmos-td-tabs' ).empty();

		TABS.forEach( ( name ) => {
			const button = el( 'button', {
				type: 'button',
				role: 'tab',
				class: 'skin-cosmos-td-tab' + ( name === activeTab ? ' is-active' : '' ),
				'aria-selected': name === activeTab ? 'true' : 'false',
				text: msg( 'tab-' + name ).text()
			} );

			button.addEventListener( 'click', () => {
				activeTab = name;
				render();
			} );
			$tabs.append( button );
		} );

		$panel.empty().append( RENDERERS[ activeTab ]() );
		updatePreview();
	}

	$( () => {
		const app = document.getElementById( 'skin-cosmos-themedesigner-app' );
		$json = $( '#skin-cosmos-themedesigner-json' );

		if ( !app || !$json.length ) {
			return;
		}

		$root = $( app ).empty();
		$root.append(
			$( '<div>' ).addClass( 'skin-cosmos-td-tabs' ).attr( 'role', 'tablist' ),
			$( '<div>' ).addClass( 'skin-cosmos-td-layout' ).append(
				$( '<div>' ).addClass( 'skin-cosmos-td-panel' ),
				$( '<div>' ).addClass( 'skin-cosmos-td-side' ).append( buildPreview() )
			)
		);

		$panel = $root.find( '.skin-cosmos-td-panel' );
		$preview = $root.find( '.skin-cosmos-td-preview' );

		$json.on( 'change', () => {
			try {
				const parsed = JSON.parse( $json.val() );

				Object.keys( state ).forEach( ( key ) => delete state[ key ] );
				Object.assign( state, parsed );
				render();
			} catch ( e ) {
				mw.notify( msg( 'color-invalid' ).text(), { type: 'error' } );
			}
		} );

		render();
	} );
}( mediaWiki, jQuery ) );
