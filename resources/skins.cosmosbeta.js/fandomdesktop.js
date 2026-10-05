( function () {
	if ( !document.body.classList.contains( 'skin-cosmos-style-fandomdesktop' ) ) {
		return;
	}

	const heading = document.getElementById( 'firstHeading' ),
		title = document.getElementById( 'cosmos-title-text' ),
		actions = document.getElementById( 'cosmos-articleHeader-actions' ),
		banner = document.getElementById( 'cosmos-banner' );

	if ( !heading || !title || !( 'IntersectionObserver' in window ) ) {
		return;
	}

	const offset = banner ? banner.offsetHeight : 0,
		bar = document.createElement( 'div' ),
		inner = document.createElement( 'div' ),
		text = document.createElement( 'div' );

	bar.className = 'skin-cosmos-sticky-header';
	bar.setAttribute( 'aria-hidden', 'true' );
	inner.className = 'skin-cosmos-sticky-header__inner';
	text.className = 'skin-cosmos-sticky-header__title';
	text.textContent = title.textContent;
	inner.appendChild( text );

	if ( actions && actions.children.length ) {
		const copy = actions.cloneNode( true );

		copy.removeAttribute( 'id' );
		copy.className = 'skin-cosmos-sticky-header__actions';
		copy.querySelectorAll( '[id]' ).forEach( ( node ) => node.removeAttribute( 'id' ) );
		copy.querySelectorAll( 'a' ).forEach( ( link ) => link.setAttribute( 'tabindex', '-1' ) );
		inner.appendChild( copy );
	}

	bar.appendChild( inner );
	document.body.appendChild( bar );

	new IntersectionObserver( ( [ entry ] ) => {
		const pastHeading = !entry.isIntersecting && entry.boundingClientRect.bottom <= offset;

		bar.classList.toggle( 'skin-cosmos-sticky-header--visible', pastHeading );
	}, { rootMargin: `-${ offset }px 0px 0px 0px` } ).observe( heading );
}() );
