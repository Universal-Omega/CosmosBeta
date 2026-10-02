const Vue = require( 'vue' ),
	App = require( './App.vue' );

const mountPoint = document.getElementById( 'skin-cosmos-themedesigner-app' ),
	jsonField = document.getElementById( 'skin-cosmos-themedesigner-json' ),
	form = document.getElementById( 'cosmosbeta-themedesigner-form' );

if ( mountPoint && jsonField && form ) {
	mountPoint.textContent = '';

	Vue.createMwApp( App, {
		designer: mw.config.get( 'wgCosmosBetaThemeDesigner' ),
		jsonField,
		form
	} ).mount( mountPoint );
}
