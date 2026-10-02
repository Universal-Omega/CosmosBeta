/* global mw */

( function ( mw ) {
	const designer = mw.cosmosBetaThemeDesigner = mw.cosmosBetaThemeDesigner || {};
	const cache = new Map();
	const BLOCKED = [ 'inherit', 'initial', 'unset', 'revert', 'currentcolor' ];

	function clamp( value, min, max ) {
		return Math.max( min, Math.min( max, value ) );
	}

	/**
	 * Reads any CSS color the browser understands.
	 *
	 * @param {string} value
	 * @return {Object|null} r, g, b as 0 to 255 and a as 0 to 1
	 */
	function parse( value ) {
		if ( typeof value !== 'string' ) {
			return null;
		}

		const key = value.trim().toLowerCase();

		if ( key === '' || BLOCKED.indexOf( key ) !== -1 || !/^[#a-z0-9(),.%\s/-]+$/.test( key ) ) {
			return null;
		}

		if ( cache.has( key ) ) {
			return cache.get( key );
		}

		let result = null;
		const probe = document.createElement( 'span' );
		probe.style.color = key;

		if ( probe.style.color !== '' ) {
			probe.style.display = 'none';
			document.body.appendChild( probe );
			const computed = window.getComputedStyle( probe ).color;
			document.body.removeChild( probe );

			const match = /^rgba?\(([^)]+)\)$/.exec( computed );
			if ( match ) {
				const parts = match[ 1 ].split( /[\s,/]+/ ).filter( Boolean ).map( Number );

				if ( parts.length >= 3 && parts.every( ( part ) => !isNaN( part ) ) ) {
					result = {
						r: Math.round( parts[ 0 ] ),
						g: Math.round( parts[ 1 ] ),
						b: Math.round( parts[ 2 ] ),
						a: parts.length > 3 ? parts[ 3 ] : 1
					};
				}
			}
		}

		cache.set( key, result );

		return result;
	}

	function hex( n ) {
		return ( '0' + clamp( Math.round( n ), 0, 255 ).toString( 16 ) ).slice( -2 );
	}

	function toHex( color ) {
		return '#' + hex( color.r ) + hex( color.g ) + hex( color.b );
	}

	/**
	 * The form the server stores. Always #rrggbb, rgba() or transparent.
	 *
	 * @param {Object} color
	 * @return {string}
	 */
	function toCanonical( color ) {
		if ( color.a <= 0 ) {
			return 'transparent';
		}

		if ( color.a >= 1 ) {
			return toHex( color );
		}

		const alpha = String( Math.round( color.a * 100 ) / 100 );

		return 'rgba(' + color.r + ', ' + color.g + ', ' + color.b + ', ' + alpha + ')';
	}

	/**
	 * @param {string} value
	 * @return {string|null} Canonical color, or null if it is not a color
	 */
	function normalize( value ) {
		const color = parse( value );

		return color ? toCanonical( color ) : null;
	}

	function toHsl( color ) {
		const r = color.r / 255;
		const g = color.g / 255;
		const b = color.b / 255;
		const max = Math.max( r, g, b );
		const min = Math.min( r, g, b );
		const l = ( max + min ) / 2;
		const d = max - min;
		let h = 0;
		let s = 0;

		if ( d !== 0 ) {
			s = l < 0.5 ? d / ( max + min ) : d / ( 2 - max - min );

			if ( max === r ) {
				h = ( ( g - b ) / d + ( g < b ? 6 : 0 ) ) / 6;
			} else if ( max === g ) {
				h = ( ( b - r ) / d + 2 ) / 6;
			} else {
				h = ( ( r - g ) / d + 4 ) / 6;
			}
		}

		return { h: h, s: s, l: l };
	}

	function toRgb( hsl ) {
		const h = hsl.h;
		const s = hsl.s;
		const l = hsl.l;

		if ( s === 0 ) {
			const v = l * 255;

			return { r: v, g: v, b: v, a: 1 };
		}

		const q = l < 0.5 ? l * ( 1 + s ) : l + s - l * s;
		const p = 2 * l - q;

		function channel( t ) {
			if ( t < 0 ) {
				t += 1;
			}
			if ( t > 1 ) {
				t -= 1;
			}
			if ( t < 1 / 6 ) {
				return p + ( q - p ) * 6 * t;
			}
			if ( t < 1 / 2 ) {
				return q;
			}
			if ( t < 2 / 3 ) {
				return p + ( q - p ) * ( 2 / 3 - t ) * 6;
			}

			return p;
		}

		return {
			r: Math.round( channel( h + 1 / 3 ) * 255 ),
			g: Math.round( channel( h ) * 255 ),
			b: Math.round( channel( h - 1 / 3 ) * 255 ),
			a: 1
		};
	}

	/**
	 * Same rule the server uses: lightness under one half counts as dark.
	 * Transparent and unreadable values count as dark too.
	 *
	 * @param {string} value
	 * @return {boolean}
	 */
	function isDark( value ) {
		const color = parse( value );

		return !color || color.a <= 0 || toHsl( color ).l < 0.5;
	}

	function readableOn( value ) {
		return isDark( value ) ? '#fff' : '#000';
	}

	function contentTextOn( value ) {
		return isDark( value ) ? '#d5d4d4' : '#000';
	}

	/**
	 * Makes a dark palette out of a light one by keeping hues and
	 * moving surfaces to dark lightness values.
	 *
	 * @param {Object} palette Slot to color
	 * @return {Object}
	 */
	function deriveDark( palette ) {
		const result = {};
		const surfaces = {
			banner: [ 0.1, 0.2 ],
			header: [ 0.14, 0.24 ],
			footer: [ 0.1, 0.2 ]
		};

		Object.keys( palette ).forEach( ( slot ) => {
			const color = parse( palette[ slot ] );

			if ( !color ) {
				return;
			}

			const hsl = toHsl( color );
			let next = { h: hsl.h, s: Math.min( hsl.s, 0.35 ), l: hsl.l };

			if ( slot === 'content' ) {
				next.l = 0.09;
			} else if ( slot === 'body' ) {
				next.l = 0.05;
			} else if ( slot === 'toolbar' ) {
				next = { h: 0, s: 0, l: 0 };
			} else if ( surfaces[ slot ] ) {
				next.l = surfaces[ slot ][ 0 ] + ( 1 - hsl.l ) * ( surfaces[ slot ][ 1 ] - surfaces[ slot ][ 0 ] );
			} else if ( slot === 'button' ) {
				next = { h: hsl.h, s: hsl.s, l: clamp( hsl.l, 0.38, 0.5 ) };
			} else if ( slot === 'link' ) {
				next = { h: hsl.h, s: Math.max( hsl.s, 0.6 ), l: 0.7 };
			}

			result[ slot ] = toHex( toRgb( next ) );
		} );

		return result;
	}

	designer.colors = {
		parse: parse,
		normalize: normalize,
		toHex: toHex,
		isDark: isDark,
		readableOn: readableOn,
		contentTextOn: contentTextOn,
		deriveDark: deriveDark
	};
}( mediaWiki ) );
