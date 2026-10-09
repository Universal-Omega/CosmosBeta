const { inject, provide } = require( 'vue' );

const KEY = 'themedesigner-reset';

// An empty map arrives from the server as an empty list
const emptyAsMap = ( key, value ) => Array.isArray( value ) && value.length === 0 ? {} : value;

const getPath = ( object, path ) => path.split( '/' ).reduce(
		( value, key ) => value === undefined || value === null ? undefined : value[ key ],
		object
	),
	same = ( a, b ) => JSON.stringify( a, emptyAsMap ) === JSON.stringify( b, emptyAsMap );

function setPath( object, path, value ) {
	const keys = path.split( '/' ),
		last = keys.pop(),
		parent = keys.reduce( ( current, key ) => current[ key ], object );

	if ( value === undefined ) {
		delete parent[ last ];
	} else {
		parent[ last ] = JSON.parse( JSON.stringify( value ) );
	}
}

/**
 * Lets every reset button in the designer compare settings with their defaults by path.
 *
 * @param {Object} state The settings being edited
 * @param {Object} defaults What the settings are when nothing is customized
 * @param {Function} isDisabled
 */
function provideReset( state, defaults, isDisabled ) {
	provide( KEY, {
		isDisabled,
		isCustom: ( paths ) => [].concat( paths ).some(
			( path ) => !same( getPath( state, path ), getPath( defaults, path ) )
		),
		reset: ( paths ) => [].concat( paths ).forEach(
			( path ) => setPath( state, path, getPath( defaults, path ) )
		)
	} );
}

function useReset() {
	return inject( KEY );
}

module.exports = { provideReset, useReset };
