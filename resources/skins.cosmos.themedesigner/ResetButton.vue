<template>
	<cdx-button
		v-if="isCustom( path )"
		class="skin-cosmos-themedesigner__reset"
		type="button"
		weight="quiet"
		:disabled="isDisabled()"
		@click="onClick"
	>
		{{ msg( message ) }}
	</cdx-button>
</template>

<script>
const { CdxButton } = mw.loader.require( 'skins.cosmos.themedesigner.codex' ),
	{ defineComponent } = require( 'vue' ),
	msg = require( './msg.js' ),
	{ useReset } = require( './reset.js' );

// @vue/component
module.exports = exports = defineComponent( {
	name: 'ResetButton',
	components: {
		CdxButton
	},
	props: {
		path: {
			type: [ String, Array ],
			required: true
		},
		message: {
			type: String,
			default: 'reset'
		},
		confirm: {
			type: String,
			default: ''
		}
	},
	emits: [ 'reset' ],
	setup( props, { emit } ) {
		const { isCustom, isDisabled, reset } = useReset();

		function onClick() {
			if ( props.confirm !== '' && !window.confirm( msg( props.confirm ) ) ) {
				return;
			}

			reset( props.path );
			emit( 'reset' );
		}

		return {
			isCustom,
			isDisabled,
			msg,
			onClick
		};
	}
} );
</script>
