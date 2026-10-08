/**
 * @param {{type: string, value: string}} font
 * @param {Object<string, string>} presets Font families of the presets by name
 * @param {string} fallback The font family when the theme does not set one
 * @param {string} [fileFamily] The font family a loaded font file went by
 * @return {string} A CSS font family
 */
function getStack( font, presets, fallback, fileFamily = '' ) {
	switch ( font.type ) {
		case 'preset':
			return presets[ font.value ] || fallback;
		case 'custom':
			return font.value || fallback;
		case 'file':
			return fileFamily ? "'" + fileFamily + "', " + fallback : fallback;
		default:
			return fallback;
	}
}

module.exports = { getStack };
