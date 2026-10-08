if ( $( window ).width() > 850 ) {
	$( '.skin-cosmos-mobile-menu' ).remove();
}

$( document ).on( 'click', '.skin-cosmos-mobile-menu__button', () => {
	$( '.skin-cosmos-nav' ).toggle();
} );
