<template>
	<cdx-multiselect-lookup
		v-model:selected="selected"
		v-model:input-chips="chips"
		v-model:input-value="input"
		:menu-items="menuItems"
		:menu-config="{ visibleItemLimit: 8 }"
		:disabled="disabled"
		:placeholder="msg( 'rail-pages-placeholder' )"
	>
		<template #no-results>
			{{ msg( 'rail-no-results' ) }}
		</template>
	</cdx-multiselect-lookup>
</template>

<script>
const { CdxMultiselectLookup } = mw.loader.require( 'skins.cosmosbeta.themedesigner.codex' ),
	{ computed, defineComponent, ref, watch } = require( 'vue' ),
	msg = require( '../msg.js' ),
	useChipList = require( './useChipList.js' );

const MAIN_PAGE = 'mainpage',
	SEARCH_DELAY = 250;

const getLabel = ( value ) => value === MAIN_PAGE ? msg( 'rail-pages-mainpage' ) : value;

// @vue/component
module.exports = exports = defineComponent( {
	name: 'PageLookup',
	components: { CdxMultiselectLookup },
	props: {
		modelValue: {
			type: Array,
			required: true
		},
		namespaces: {
			type: Array,
			required: true
		},
		disabled: {
			type: Boolean,
			required: true
		}
	},
	emits: [ 'update:modelValue' ],
	setup( props, { emit } ) {
		const { selected, chips, input } = useChipList(
				props,
				emit,
				( value ) => ( { value, label: getLabel( value ) } )
			),
			results = ref( [] ),
			searchable = props.namespaces.map( ( ns ) => ns.value ).filter( ( id ) => id >= 0 );

		let timer = null,
			latest = 0;

		const menuItems = computed( () => {
			const term = input.value.trim(),
				titles = [ MAIN_PAGE, ...results.value ];

			if ( term !== '' && !titles.includes( term ) ) {
				titles.push( term );
			}

			return titles
				.map( ( value ) => ( { value, label: getLabel( value ) } ) )
				.filter( ( item ) => item.label.toLowerCase().includes( term.toLowerCase() ) );
		} );

		watch( input, ( value ) => {
			const term = value.trim(),
				request = ++latest;

			clearTimeout( timer );

			if ( term === '' ) {
				results.value = [];
				return;
			}

			timer = setTimeout( () => {
				new mw.Api().get( {
					action: 'opensearch',
					search: term,
					limit: 10,
					namespace: searchable.join( '|' )
				} ).then( ( data ) => {
					if ( request === latest ) {
						results.value = data[ 1 ] || [];
					}
				} );
			}, SEARCH_DELAY );
		} );

		return { selected, chips, input, menuItems, msg };
	}
} );
</script>
