if ( $( window ).width() > 850 ) {
	$( '.skin-cosmos-mobile-navigation' ).remove();
}

$( document ).on( 'click', '.skin-cosmos-mobile-menu-button', () => {
	$( '.skin-cosmos-nav' ).toggle();
} );

{
	const header = document.getElementById( 'cosmos-header-articleHeader' ),
		title = document.getElementById( 'firstHeading' ),
		actions = document.getElementById( 'cosmos-articleHeader-actions' );

	if ( header && title && actions ) {
		const update = () => {
			const wrapped = actions.getBoundingClientRect().top >= title.getBoundingClientRect().bottom - 1;
			header.classList.toggle( 'skin-cosmos-actions-wrapped', wrapped );
		};

		update();

		if ( window.ResizeObserver ) {
			new ResizeObserver( update ).observe( header );
		} else {
			$( window ).on( 'resize', update );
		}
	}
}
