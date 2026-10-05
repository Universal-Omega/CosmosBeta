<template>
	<div class="skin-cosmos-td-imagefield">
		<div class="skin-cosmos-td-imagefield-row">
			<cdx-lookup
				v-model:selected="selected"
				v-model:input-value="text"
				class="skin-cosmos-td-imagefield-lookup"
				:menu-items="items"
				:menu-config="{ visibleItemLimit: 6 }"
				:placeholder="placeholder"
				:disabled="disabled"
				@input="onInput"
				@update:selected="onSelect"
			>
				<template #no-results>
					{{ noResults }}
				</template>
			</cdx-lookup>
			<template v-if="upload.enabled">
				<cdx-button
					type="button"
					:disabled="disabled || uploading"
					@click="pick"
				>
					{{ uploading ? uploadingLabel : uploadLabel }}
				</cdx-button>
				<input
					ref="file"
					class="skin-cosmos-td-imagefield-file"
					type="file"
					:accept="accept"
					@change="onFile"
				>
			</template>
		</div>
		<cdx-message v-if="error" type="error" :inline="true">
			{{ error }}
		</cdx-message>
	</div>
</template>

<script>
const { CdxButton, CdxLookup, CdxMessage } = mw.loader.require( 'skins.cosmosbeta.themedesigner.codex' ),
	{ defineComponent, ref, watch } = require( 'vue' );

const DELAY = 250;

// @vue/component
module.exports = exports = defineComponent( {
	name: 'ImageField',
	components: { CdxButton, CdxLookup, CdxMessage },
	props: {
		modelValue: {
			type: String,
			default: ''
		},
		placeholder: {
			type: String,
			default: ''
		},
		disabled: {
			type: Boolean,
			default: false
		},
		upload: {
			type: Object,
			required: true
		},
		messages: {
			type: Object,
			required: true
		}
	},
	emits: [ 'update:modelValue' ],
	setup( props, { emit } ) {
		const text = ref( props.modelValue ),
			selected = ref( null ),
			items = ref( [] ),
			uploading = ref( false ),
			error = ref( '' ),
			file = ref( null ),
			accept = props.upload.extensions.map( ( ext ) => '.' + ext ).join( ',' );

		let timer = null,
			counter = 0;

		function search( term ) {
			const id = ++counter;

			if ( term.trim() === '' || /^(https?:)?\/\//i.test( term ) ) {
				items.value = [];
				return;
			}

			new mw.Api().get( {
				action: 'query',
				list: 'prefixsearch',
				pssearch: term.replace( /^(file|image):/i, '' ),
				psnamespace: 6,
				pslimit: 10,
				formatversion: 2
			} ).then( ( data ) => {
				if ( id !== counter ) {
					return;
				}

				items.value = ( data.query.prefixsearch || [] ).map( ( page ) => {
					const name = page.title.replace( /^[^:]+:/, '' );

					return { value: name, label: name };
				} );
			} );
		}

		function onInput( value ) {
			emit( 'update:modelValue', value );
			clearTimeout( timer );
			timer = setTimeout( () => search( value ), DELAY );
		}

		function onSelect( value ) {
			if ( value !== null ) {
				emit( 'update:modelValue', String( value ) );
			}
		}

		function pick() {
			error.value = '';
			file.value.click();
		}

		function onFile( event ) {
			const chosen = event.target.files[ 0 ];

			event.target.value = '';

			if ( !chosen ) {
				return;
			}

			uploading.value = true;
			error.value = '';

			new mw.Api().upload( chosen, {
				filename: chosen.name,
				comment: props.messages.comment,
				format: 'json'
			} ).then( ( result ) => {
				const name = result.upload.filename;

				text.value = name;
				emit( 'update:modelValue', name );
			}, ( code ) => {
				error.value = code === 'exists' || code === 'page-exists' ?
					props.messages.exists :
					props.messages.failed;
			} ).always( () => {
				uploading.value = false;
			} );
		}

		watch( () => props.modelValue, ( value ) => {
			if ( value !== text.value ) {
				text.value = value;
			}
		} );

		return {
			text,
			selected,
			items,
			uploading,
			error,
			file,
			accept,
			noResults: props.messages.noResults,
			uploadLabel: props.messages.upload,
			uploadingLabel: props.messages.uploading,
			onInput,
			onSelect,
			pick,
			onFile
		};
	}
} );
</script>
