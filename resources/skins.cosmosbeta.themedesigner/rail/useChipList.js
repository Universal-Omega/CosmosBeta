const { ref, watch } = require( 'vue' );

/**
 * Keeps a Codex multiselect lookup in step with a plain list kept in the settings.
 *
 * @param {Object} props Props with the list as modelValue
 * @param {Function} emit
 * @param {Function} toChip Turns a list entry into a chip
 * @return {{selected: Object, chips: Object, input: Object}}
 */
module.exports = function useChipList( props, emit, toChip ) {
	const selected = ref( [ ...props.modelValue ] ),
		chips = ref( props.modelValue.map( toChip ) ),
		input = ref( '' );

	watch( selected, ( list ) => {
		emit( 'update:modelValue', [ ...list ] );
	}, { deep: true } );

	watch( () => props.modelValue, ( list ) => {
		if ( JSON.stringify( list ) !== JSON.stringify( selected.value ) ) {
			selected.value = [ ...list ];
			chips.value = list.map( toChip );
		}
	}, { deep: true } );

	return { selected, chips, input };
};
