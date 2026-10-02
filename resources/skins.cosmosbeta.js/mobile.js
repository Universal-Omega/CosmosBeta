if ( $( window ).width() < 851 ) {
	$( 'body:not(.skin-cosmos-search-vue) .skin-cosmos-search-box-input' ).on( 'focus', () => {
		$( '#cosmos-banner-userOptions' ).hide();
		$( '.skin-cosmos-mobile-menu-button' ).hide();
		$( '#cosmos-search-buttonContainer' ).show();
	} );

	$( 'body:not(.skin-cosmos-search-vue) .skin-cosmos-search-box-input' ).on( 'focusout', () => {
		$( '#cosmos-banner-userOptions' ).show();
		$( '.skin-cosmos-mobile-menu-button' ).show();
		$( '#cosmos-search-buttonContainer' ).hide();
	} );
}

if ( $( window ).width() > 850 ) {
	$( '.skin-cosmos-mobile-navigation' ).remove();
}
