/* global mw, jQuery */

( function ( mw, $ ) {
	const config = mw.config.get( 'wgCosmosBetaColorMode' );

	if ( !config ) {
		return;
	}

	const STORAGE_KEY = 'skin-cosmos-colormode';
	const root = document.documentElement;

	function getStoredMode() {
		try {
			const mode = window.localStorage.getItem( STORAGE_KEY );

			return mode === 'light' || mode === 'dark' ? mode : null;
		} catch ( e ) {
			return null;
		}
	}

	function storeMode( mode ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, mode );
		} catch ( e ) {
			// Storage can be unavailable. The switch still works for this page view.
		}
	}

	function getCurrentMode() {
		return root.classList.contains( 'skin-cosmos-colormode-dark' ) ? 'dark' : 'light';
	}

	function applyMode( mode ) {
		root.classList.remove( 'skin-cosmos-colormode-light', 'skin-cosmos-colormode-dark', 'skin-cosmos-colormode-pending' );
		root.classList.add( 'skin-cosmos-colormode-' + mode );

		document.body.classList.toggle( 'theme-dark', mode === 'dark' );
		document.body.classList.toggle( 'theme-light', mode !== 'dark' );

		const $item = $( '#m-colormode' ),
			// eslint-disable-next-line mediawiki/msg-doc
			label = mw.msg( 'cosmosbeta-colormode-switch-' + mode );

		$item.attr( { title: label, 'aria-label': label } );
		root.className = root.className.replace( /skin-theme-clientpref-\w+/, 'skin-theme-clientpref-' + ( mode === 'dark' ? 'night' : 'day' ) );
		$item.find( '.skin-cosmos-icon' )
			.removeClass( 'skin-cosmos-icon-moon skin-cosmos-icon-bright' )
			.addClass( mode === 'dark' ? 'skin-cosmos-icon-bright' : 'skin-cosmos-icon-moon' );
	}

	// The alternative stylesheets are style only modules, so they are loaded the way load.php serves styles.
	function loadAltStyles() {
		return new Promise( ( resolve, reject ) => {
			if ( !config.altModules.length ) {
				reject( new Error( 'No alternative styles' ) );
				return;
			}

			const link = document.createElement( 'link' ),
				params = new URLSearchParams( {
					lang: mw.config.get( 'wgUserLanguage' ),
					modules: config.altModules.join( '|' ),
					only: 'styles',
					skin: mw.config.get( 'skin' )
				} );

			link.rel = 'stylesheet';
			link.href = mw.config.get( 'wgScriptPath' ) + '/load.php?' + params.toString();
			link.onload = resolve;
			link.onerror = reject;
			document.head.appendChild( link );
		} );
	}

	function switchForVisitor( mode ) {
		storeMode( mode );

		// Going back to what the server rendered means dropping the extra styles.
		if ( mode === config.render ) {
			window.location.reload();
			return;
		}

		loadAltStyles().then(
			() => applyMode( mode ),
			() => root.classList.remove( 'skin-cosmos-colormode-pending' )
		);
	}

	function switchForUser( mode ) {
		// With the auto default every choice has to be stored, or the browser setting wins again
		const value = !config.autoDefault && mode === config.default ? '' : mode;

		new mw.Api().saveOption( config.option, value ).then( () => {
			window.location.reload();
		} );
	}

	$( () => {
		$( '#m-colormode' ).on( 'click', function ( event ) {
			event.preventDefault();

			const next = getCurrentMode() === 'dark' ? 'light' : 'dark';

			if ( config.registered ) {
				switchForUser( next );
			} else {
				switchForVisitor( next );
			}
		} );

		const stored = config.registered ? null : getStoredMode();

		if ( stored && stored !== config.render ) {
			switchForVisitor( stored );
		} else if ( config.auto && !stored ) {
			// Nothing was chosen yet, so the button follows what the browser asks for
			const query = window.matchMedia( '(prefers-color-scheme: dark)' );

			applyMode( query.matches ? 'dark' : 'light' );
			query.addEventListener( 'change', ( event ) => applyMode( event.matches ? 'dark' : 'light' ) );
		} else {
			root.classList.remove( 'skin-cosmos-colormode-pending' );
		}
	} );
}( mediaWiki, jQuery ) );
