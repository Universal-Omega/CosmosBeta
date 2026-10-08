const Vue = require( 'vue' ),
	App = require( './App.vue' );

const mountPoint = document.getElementById( 'skin-cosmos-themedesigner__app' ),
	jsonField = document.getElementById( 'skin-cosmos-themedesigner__json' ),
	form = document.getElementById( 'skin-cosmos-themedesigner__form' );

if ( mountPoint && jsonField && form ) {
	mountPoint.textContent = '';

	Vue.createMwApp( App, {
		designer: mw.config.get( 'wgCosmosThemeDesigner' ),
		jsonField,
		form
	} ).mount( mountPoint );
}
