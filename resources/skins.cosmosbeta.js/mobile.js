if ( $( window ).width() > 850 ) {
	$( '.skin-cosmos-mobile-navigation' ).remove();
}

$( document ).on( 'click', '.skin-cosmos-mobile-menu-button', () => {
	$( '.skin-cosmos-nav' ).toggle();
} );
