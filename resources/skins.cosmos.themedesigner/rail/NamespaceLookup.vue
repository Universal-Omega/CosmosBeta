<template>
	<cdx-multiselect-lookup
		v-model:selected="selected"
		v-model:input-chips="chips"
		v-model:input-value="input"
		:menu-items="menuItems"
		:menu-config="{ visibleItemLimit: 8 }"
		:disabled="disabled"
		:placeholder="msg( 'rail-namespaces-placeholder' )"
	>
		<template #no-results>
			{{ msg( 'rail-no-results' ) }}
		</template>
	</cdx-multiselect-lookup>
</template>

<script>
const { CdxMultiselectLookup } = mw.loader.require( 'skins.cosmos.themedesigner.codex' ),
	{ computed, defineComponent } = require( 'vue' ),
	msg = require( '../msg.js' ),
	useChipList = require( './useChipList.js' );

// @vue/component
module.exports = exports = defineComponent( {
	name: 'NamespaceLookup',
	components: { CdxMultiselectLookup },
	props: {
		// eslint-disable-next-line vue/no-unused-properties
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
		const labels = Object.fromEntries(
				props.namespaces.map( ( ns ) => [ ns.value, ns.label ] )
			),
			{ selected, chips, input } = useChipList(
				props,
				emit,
				( value ) => ( { value, label: labels[ value ] || String( value ) } )
			);

		const menuItems = computed( () => {
			const term = input.value.trim().toLowerCase();

			return props.namespaces.filter( ( ns ) => ns.label.toLowerCase().includes( term ) );
		} );

		return { selected, chips, input, menuItems, msg };
	}
} );
</script>
