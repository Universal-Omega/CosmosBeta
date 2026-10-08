<template>
	<cdx-accordion class="skin-cosmos-themedesigner__rail-module">
		<template #title>
			{{ title }}
			<span class="skin-cosmos-themedesigner__rail-origin">{{ msg( 'rail-origin-' + entry.origin ) }}</span>
		</template>
		<template v-if="summary !== ''" #description>
			{{ summary }}
		</template>

		<cdx-field>
			<template #label>
				{{ msg( 'rail-module-display' ) }}
			</template>
			<cdx-select
				:selected="display"
				:menu-items="displayItems"
				:disabled="disabled"
				@update:selected="onDisplay"
			></cdx-select>
		</cdx-field>
		<cdx-field>
			<template #label>
				{{ msg( 'rail-module-type' ) }}
			</template>
			<cdx-select
				:selected="rules.type || ''"
				:menu-items="typeItems"
				:disabled="disabled"
				@update:selected="onType"
			></cdx-select>
		</cdx-field>
		<cdx-field>
			<cdx-toggle-switch
				:model-value="hasPlaces"
				:disabled="disabled"
				@update:model-value="onPlaces"
			>
				{{ msg( 'rail-module-places' ) }}
			</cdx-toggle-switch>
			<template #help-text>
				{{ msg( 'rail-module-places-help' ) }}
			</template>
		</cdx-field>
		<template v-if="hasPlaces">
			<cdx-field>
				<template #label>
					{{ msg( 'rail-module-namespaces' ) }}
				</template>
				<namespace-lookup
					:model-value="rules.disabledNamespaces || []"
					:namespaces="namespaces"
					:disabled="disabled"
					@update:model-value="$emit( 'update', { disabledNamespaces: $event } )"
				></namespace-lookup>
			</cdx-field>
			<cdx-field>
				<template #label>
					{{ msg( 'rail-module-pages' ) }}
				</template>
				<page-lookup
					:model-value="rules.disabledPages || []"
					:namespaces="namespaces"
					:disabled="disabled"
					@update:model-value="$emit( 'update', { disabledPages: $event } )"
				></page-lookup>
			</cdx-field>
		</template>
		<cdx-button
			v-if="removable"
			type="button"
			action="destructive"
			:disabled="disabled"
			@click="$emit( 'remove' )"
		>
			{{ msg( 'rail-custom-remove' ) }}
		</cdx-button>
	</cdx-accordion>
</template>

<script>
const { CdxAccordion, CdxButton, CdxField, CdxSelect, CdxToggleSwitch } = mw.loader.require( 'skins.cosmosbeta.themedesigner.codex' ),
	{ computed, defineComponent } = require( 'vue' ),
	msg = require( '../msg.js' ),
	NamespaceLookup = require( './NamespaceLookup.vue' ),
	PageLookup = require( './PageLookup.vue' );

const BUILT_IN = 'builtin',
	CUSTOM = 'custom',
	RECENT_CHANGES = 'recentchanges';

// @vue/component
module.exports = exports = defineComponent( {
	name: 'RailModuleCard',
	components: {
		CdxAccordion,
		CdxButton,
		CdxField,
		CdxSelect,
		CdxToggleSwitch,
		NamespaceLookup,
		PageLookup
	},
	props: {
		entry: {
			type: Object,
			required: true
		},
		rules: {
			type: Object,
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
	emits: [ 'update', 'remove' ],
	setup( props, { emit } ) {
		const items = ( pairs ) => pairs.map( ( [ value, label ] ) => ( { value, label } ) );

		const isCustom = computed( () => props.entry.origin === CUSTOM ),
			canForceShow = computed( () => props.entry.id === RECENT_CHANGES ),
			removable = computed( () => isCustom.value && !props.disabled );

		const title = computed( () => props.entry.origin === BUILT_IN ?
			msg( 'rail-module-' + props.entry.id ) :
			props.entry.label );

		// Only recent changes is off unless asked for, every other module shows unless told not to
		const displayItems = computed( () => items( [
			[ '', canForceShow.value ? msg( 'rail-display-inherit' ) : msg( 'rail-display-show' ) ],
			...canForceShow.value ? [ [ 'show', msg( 'rail-display-show' ) ] ] : [],
			[ 'hide', msg( 'rail-display-hide' ) ]
		] ) );

		// Custom modules have no wiki setting to follow, so they are normal unless made sticky
		const typeItems = computed( () => items( [
			[ '', isCustom.value ? msg( 'rail-type-normal' ) : msg( 'rail-type-inherit' ) ],
			...isCustom.value ? [] : [ [ 'normal', msg( 'rail-type-normal' ) ] ],
			[ 'sticky', msg( 'rail-type-sticky' ) ]
		] ) );

		const display = computed( () => {
			if ( props.rules.enabled === null ) {
				return '';
			}

			return props.rules.enabled ? 'show' : 'hide';
		} );

		const hasPlaces = computed( () => props.rules.disabledNamespaces !== null || props.rules.disabledPages !== null );

		const summary = computed( () => [
			props.rules.enabled === false ? msg( 'rail-display-hide' ) : null,
			props.rules.type === 'sticky' ? msg( 'rail-type-sticky' ) : null,
			hasPlaces.value ? msg( 'rail-summary-places' ) : null
		].filter( Boolean ).join( ' · ' ) );

		function onDisplay( value ) {
			emit( 'update', { enabled: value === '' ? null : value === 'show' } );
		}

		function onType( value ) {
			emit( 'update', { type: value === '' ? null : value } );
		}

		function onPlaces( enabled ) {
			emit( 'update', {
				disabledNamespaces: enabled ? [] : null,
				disabledPages: enabled ? [] : null
			} );
		}

		return {
			title,
			display,
			displayItems,
			typeItems,
			hasPlaces,
			summary,
			removable,
			msg,
			onDisplay,
			onType,
			onPlaces
		};
	}
} );
</script>
