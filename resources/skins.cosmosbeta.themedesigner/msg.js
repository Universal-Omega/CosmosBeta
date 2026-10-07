/**
 * Gets a theme designer message by the part of its key after the shared prefix.
 *
 * @param {string} key
 * @return {string}
 */
module.exports = function msg( key ) {
	// Messages used here: cosmosbeta-themedesigner-*
	// eslint-disable-next-line mediawiki/msg-doc
	return mw.msg( 'cosmosbeta-themedesigner-' + key );
};
