<template>
	<div class="skin-cosmos-themedesigner__fontpicker">
		<div
			class="skin-cosmos-themedesigner__fonts"
			role="radiogroup"
			:aria-label="msg( 'layout-font' )"
		>
			<button
				v-for="choice in choices"
				:key="choice.id"
				type="button"
				role="radio"
				class="skin-cosmos-themedesigner__font"
				:class="{ 'skin-cosmos-is-active': choice.id === selected }"
				:aria-checked="choice.id === selected"
				:disabled="disabled"
				@click="select( choice.id )"
			>
				<span class="skin-cosmos-themedesigner__font-sample" :style="{ fontFamily: choice.stack }">
					{{ msg( 'font-sample' ) }}
				</span>
				<span class="skin-cosmos-themedesigner__font-name">
					{{ choice.label }}
				</span>
			</button>
		</div>
		<p class="skin-cosmos-themedesigner__font-preview" :style="{ fontFamily: currentStack }">
			{{ msg( 'font-preview' ) }}
		</p>
	</div>
</template>

<script>
const { computed, defineComponent, ref, watch } = require( 'vue' ),
	{ getStack } = require( './font.js' ),
	msg = require( './msg.js' );

const PREVIEW_FAMILY = 'Cosmos Font Preview';

// @vue/component
module.exports = exports = defineComponent( {
	name: 'FontPicker',
	props: {
		modelValue: {
			type: Object,
			required: true
		},
		presets: {
			type: Object,
			required: true
		},
		fallback: {
			type: String,
			required: true
		},
		disabled: {
			type: Boolean,
			required: true
		}
	},
	emits: [ 'update:modelValue' ],
	setup( props, { emit } ) {
		const fileFamily = ref( '' );

		let face = null,
			counter = 0;

		// Loads the chosen font file in the browser so it can be shown
		function loadFile( name ) {
			const id = ++counter;

			fileFamily.value = '';

			if ( face ) {
				document.fonts.delete( face );
				face = null;
			}

			if ( name === '' || typeof FontFace === 'undefined' ) {
				return;
			}

			const url = mw.config.get( 'wgScript' ) + '?title=Special:FilePath/' + encodeURIComponent( name ),
				loading = new FontFace( PREVIEW_FAMILY, 'url(' + JSON.stringify( url ) + ')' );

			loading.load().then( () => {
				if ( id === counter ) {
					face = loading;
					document.fonts.add( loading );
					fileFamily.value = PREVIEW_FAMILY;
				}
			}, () => {} );
		}

		watch(
			() => props.modelValue.type === 'file' ? props.modelValue.value : '',
			loadFile,
			{ immediate: true }
		);

		const currentStack = computed( () => getStack( props.modelValue, props.presets, props.fallback, fileFamily.value ) );

		const selected = computed( () => {
			const { type, value } = props.modelValue;

			return type === 'preset' ? 'preset:' + value : type || 'default';
		} );

		// The choices that take input show what has been entered, once they are chosen
		const choices = computed( () => [
			{ id: 'default', label: msg( 'font-default' ), stack: props.fallback },
			...Object.keys( props.presets ).map( ( key ) => ( {
				id: 'preset:' + key,
				label: msg( 'font-preset-' + key ),
				stack: props.presets[ key ]
			} ) ),
			...[ 'file', 'custom' ].map( ( id ) => ( {
				id,
				label: msg( 'font-' + id ),
				stack: selected.value === id ? currentStack.value : props.fallback
			} ) )
		] );

		function select( id ) {
			if ( id === selected.value ) {
				return;
			}

			const [ type, value = '' ] = id.split( ':' );

			emit( 'update:modelValue', type === 'default' ? { type: '', value: '' } : { type, value } );
		}

		return {
			choices,
			selected,
			currentStack,
			msg,
			select
		};
	}
} );
</script>
