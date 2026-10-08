const RULE_KEYS = [ 'enabled', 'type', 'disabledNamespaces', 'disabledPages' ];

/**
 * The saved settings leave out rules a module does not set, and an empty map arrives as a list.
 *
 * @param {Object|Array} modules
 * @return {Object}
 */
function toMap( modules ) {
	return Array.isArray( modules ) ? {} : modules;
}

/**
 * @param {Object|Array} modules Rules by module id
 * @param {string} id
 * @return {Object} All four rules, null for those that follow the wiki
 */
function getRules( modules, id ) {
	return Object.assign(
		{ enabled: null, type: null, disabledNamespaces: null, disabledPages: null },
		toMap( modules )[ id ]
	);
}

/**
 * @param {Object|Array} modules Rules by module id
 * @param {string} id
 * @param {Object} patch Rules to change
 * @return {Object} A copy of the modules, without the module if it follows the wiki again
 */
function setRules( modules, id, patch ) {
	const rules = Object.assign( getRules( modules, id ), patch ),
		next = Object.assign( {}, toMap( modules ) ),
		kept = RULE_KEYS.filter( ( key ) => rules[ key ] !== null );

	if ( kept.length ) {
		next[ id ] = Object.fromEntries( kept.map( ( key ) => [ key, rules[ key ] ] ) );
	} else {
		delete next[ id ];
	}

	return next;
}

/**
 * @param {Object|Array} modules Rules by module id
 * @param {string} id
 * @return {Object} A copy of the modules without the module
 */
function removeRules( modules, id ) {
	const next = Object.assign( {}, toMap( modules ) );

	delete next[ id ];

	return next;
}

/**
 * @param {string} text
 * @return {string} Lowercase words joined by dashes, as custom module ids are stored
 */
function slugify( text ) {
	return text.toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 60 );
}

module.exports = { getRules, setRules, removeRules, slugify };
