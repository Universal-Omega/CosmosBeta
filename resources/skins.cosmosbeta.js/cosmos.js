/* global jQuery, mediaWiki */

( function ( $, mw ) {
	let modal = document.getElementById( 'createPageModal' ),
		triggers = document.querySelectorAll( '#createpage, [data-skin-cosmos-create-page]' ),
		span = document.getElementsByClassName( 'skin-cosmos-modal__close' )[ 0 ],
		$top = 0;

	if ( modal && triggers.length ) {
		const closeModal = function () {
			modal.style.display = 'none';
		};

		triggers.forEach( ( trigger ) => {
			trigger.onclick = function ( event ) {
				event.preventDefault();
				modal.style.display = 'flex';
				$( '#create-page-dialog__title' ).trigger( 'focus' );
			};
		} );

		if ( span ) {
			span.onclick = closeModal;
			span.onkeydown = function ( event ) {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					event.preventDefault();
					closeModal();
				}
			};
		}

		window.onclick = function ( event ) {
			if ( event.target === modal ) {
				closeModal();
			}
		};

		document.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' ) {
				closeModal();
			}
		} );
	}

	$( '.skin-cosmos-create-dialog #create-page-dialog__title' ).on( 'keyup', () => {
		let empty = false;

		$( '.skin-cosmos-create-dialog #create-page-dialog__title' ).each( function () {
			if ( $( this ).val() === '' ) {
				empty = true;
			}
		} );

		if ( empty ) {
			$( '.skin-cosmos-create-dialog__button' ).prop( 'disabled', true );
		} else {
			$( '.skin-cosmos-create-dialog__button' ).prop( 'disabled', false );
		}
	} );

	$( document ).on( 'click', '.skin-cosmos-dropdown__button', function () {
		const $dropdown = $( this ).closest( '.skin-cosmos-dropdown' ),
			willOpen = !$dropdown.hasClass( 'skin-cosmos-is-open' );

		$( '.skin-cosmos-dropdown' ).removeClass( 'skin-cosmos-is-open' );
		$dropdown.toggleClass( 'skin-cosmos-is-open', willOpen );

		// Lists that would run past the edge of the screen are moved back inside it
		const list = $dropdown.children( '.skin-cosmos-dropdown__list' )[ 0 ];

		if ( list ) {
			list.style.transform = '';

			if ( willOpen ) {
				const rect = list.getBoundingClientRect(),
					margin = 8,
					shift = Math.max( margin - rect.left, 0 ) - Math.max( rect.right - ( window.innerWidth - margin ), 0 );

				if ( shift !== 0 ) {
					list.style.transform = 'translateX( ' + shift + 'px )';
				}
			}
		}
	} );

	if ( window.matchMedia( '(hover: none)' ).matches ) {
		$( '.skin-cosmos-menu' ).addClass( 'skin-cosmos-is-touch' );
	}

	$( document ).on( 'click', '.skin-cosmos-menu__toggle', function ( event ) {
		const $menu = $( this ).closest( '.skin-cosmos-menu' );

		if ( $menu.closest( '.skin-cosmos-mobile-menu' ).length ) {
			return;
		}

		const willOpen = !$menu.hasClass( 'skin-cosmos-is-open' );

		if ( $( this ).find( 'a[href="#"]' ).length || $( this ).is( 'a[href="#"]' ) ) {
			event.preventDefault();
		}

		$( '.skin-cosmos-menu' ).removeClass( 'skin-cosmos-is-open' );
		$menu.toggleClass( 'skin-cosmos-is-open', willOpen );
	} );

	$( document ).on( 'click', ( event ) => {
		if ( !$( event.target ).closest( '.skin-cosmos-dropdown' ).length ) {
			$( '.skin-cosmos-dropdown' ).removeClass( 'skin-cosmos-is-open' );
		}

		if ( !$( event.target ).closest( '.skin-cosmos-menu' ).length ) {
			$( '.skin-cosmos-menu' ).removeClass( 'skin-cosmos-is-open' );
		}
	} );

	$( document ).on( 'click', '.skin-cosmos-personal-tools__list .uls-trigger', () => {
		$( '.skin-cosmos-dropdown' ).removeClass( 'skin-cosmos-is-open' );
	} );

	mw.hook( 've.activationComplete' ).add( () => {
		$( '.ve-activated .firstHeading' ).html( $( 'title' ).html().replace( ' - ' + mw.config.get( 'wgSiteName' ), '' ) );
	} );

	$( '.skin-cosmos-rail .skin-cosmos-rail__inner' ).addClass( 'loaded skin-cosmos-is-loaded' );
	$( '.skin-cosmos-rail__module--sticky' ).each( function () {
		const $module = $( this ).nextAll( '.skin-cosmos-rail__module--sticky' );

		$top += $( this ).outerHeight() + 20;

		$module.attr( 'style', 'top: ' + ( $top + 60 ) + 'px;' );
	} );

	/**
	 * Closes the site notice
	 */
	function closeSiteNotice() {
		const $siteNotice = $( '#cosmos-content-siteNotice' );
		$siteNotice.remove();
		mw.cookie.set( 'CosmosSiteNoticeState', 'closed', { expires: 604800 } );
	}

	$( () => {
		$( '#cosmos-siteNotice-closeButton' ).on( 'click', closeSiteNotice );
	} );

	$( () => {
		if (
			mw.config.get( 'wgVisualEditorConfig' ) &&
			mw.config.get( 'wgVisualEditorConfig' ).enableWikitext &&
			mw.config.get( 'wgPageName' ) === 'MediaWiki:Cosmos-navigation'
		) {
			const visualEditorConfig = mw.config.get( 'wgVisualEditorConfig' );
			visualEditorConfig.enableWikitext = false;

			mw.config.set( 'wgVisualEditorConfig', visualEditorConfig );
		}
	} );
}( jQuery, mediaWiki ) );
