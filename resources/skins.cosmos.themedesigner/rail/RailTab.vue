<template>
	<div class="skin-cosmos-themedesigner__rail">
		<p>{{ msg( 'rail-intro' ) }}</p>

		<cdx-field>
			<cdx-toggle-switch
				:model-value="rail.enabled"
				:disabled="disabled"
				@update:model-value="update( { enabled: $event } )"
			>
				{{ msg( 'rail-enabled' ) }}
			</cdx-toggle-switch>
			<reset-button path="rail/enabled"></reset-button>
		</cdx-field>
		<cdx-field>
			<cdx-toggle-switch
				:model-value="rail.hideForAnons"
				:disabled="disabled"
				@update:model-value="update( { hideForAnons: $event } )"
			>
				{{ msg( 'rail-anons' ) }}
			</cdx-toggle-switch>
			<reset-button path="rail/hideForAnons"></reset-button>
		</cdx-field>
		<cdx-field>
			<template #label>
				{{ msg( 'rail-namespaces' ) }}
			</template>
			<template #help-text>
				{{ msg( 'rail-namespaces-help' ) }}
			</template>
			<namespace-lookup
				:model-value="rail.disabledNamespaces ?? designer.effective.rail.disabledNamespaces"
				:namespaces="designer.namespaces"
				:disabled="disabled"
				@update:model-value="update( { disabledNamespaces: $event } )"
			></namespace-lookup>
			<reset-button path="rail/disabledNamespaces"></reset-button>
		</cdx-field>
		<cdx-field>
			<template #label>
				{{ msg( 'rail-pages' ) }}
			</template>
			<template #help-text>
				{{ msg( 'rail-pages-help' ) }}
			</template>
			<page-lookup
				:model-value="rail.disabledPages ?? designer.effective.rail.disabledPages"
				:namespaces="designer.namespaces"
				:disabled="disabled"
				@update:model-value="update( { disabledPages: $event } )"
			></page-lookup>
			<reset-button path="rail/disabledPages"></reset-button>
		</cdx-field>

		<h3 class="skin-cosmos-themedesigner__rail-heading">
			{{ msg( 'rail-modules' ) }}
		</h3>
		<p class="skin-cosmos-themedesigner__help">
			{{ msg( 'rail-modules-help' ) }}
		</p>
		<div class="skin-cosmos-themedesigner__rail-modules">
			<rail-module-card
				v-for="entry in entries"
				:key="entry.id"
				:entry="entry"
				:rules="getRules( rail.modules, entry.id )"
				:defaults="getModuleDefaults( entry.id )"
				:namespaces="designer.namespaces"
				:disabled="disabled"
				@update="setModuleRules( entry.id, $event )"
				@remove="removeCustomModule( entry )"
			></rail-module-card>
		</div>

		<h3 class="skin-cosmos-themedesigner__rail-heading">
			{{ msg( 'rail-custom-add' ) }}
		</h3>
		<cdx-field
			:status="messageError === '' ? 'default' : 'error'"
			:messages="{ error: messageError }"
		>
			<template #label>
				{{ msg( 'rail-custom-message' ) }}
			</template>
			<template #help-text>
				{{ msg( 'rail-custom-message-help' ) }}
			</template>
			<cdx-text-input
				v-model="message"
				:disabled="disabled"
				@keydown.enter.prevent="addCustomModule"
			></cdx-text-input>
		</cdx-field>
		<cdx-field>
			<template #label>
				{{ msg( 'rail-custom-header' ) }}
			</template>
			<cdx-text-input
				v-model="header"
				:disabled="disabled"
				maxlength="80"
				@keydown.enter.prevent="addCustomModule"
			></cdx-text-input>
		</cdx-field>
		<cdx-button
			type="button"
			:disabled="disabled || !canAdd"
			@click="addCustomModule"
		>
			{{ msg( 'rail-custom-add-button' ) }}
		</cdx-button>
	</div>
</template>

<script>
const { CdxButton, CdxField, CdxTextInput, CdxToggleSwitch } = mw.loader.require( 'skins.cosmos.themedesigner.codex' ),
	{ computed, defineComponent, ref } = require( 'vue' ),
	msg = require( '../msg.js' ),
	NamespaceLookup = require( './NamespaceLookup.vue' ),
	PageLookup = require( './PageLookup.vue' ),
	RailModuleCard = require( './RailModuleCard.vue' ),
	ResetButton = require( '../ResetButton.vue' ),
	{ getRules, removeRules, setRules, slugify } = require( './rules.js' );

const CUSTOM = 'custom',
	CUSTOM_PREFIX = 'custom-',
	PAGE_TOOLS = 'page-tools',
	RECENT_CHANGES = 'recentchanges',
	INTERFACE_PREFIX = 'interface-',
	MESSAGE_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/;

const getModuleId = ( custom ) => CUSTOM_PREFIX + custom.id;

// @vue/component
module.exports = exports = defineComponent( {
	name: 'RailTab',
	components: {
		CdxButton,
		CdxField,
		CdxTextInput,
		CdxToggleSwitch,
		NamespaceLookup,
		PageLookup,
		RailModuleCard,
		ResetButton
	},
	props: {
		modelValue: {
			type: Object,
			required: true
		},
		toolbar: {
			type: Object,
			required: true
		},
		designer: {
			type: Object,
			required: true
		}
	},
	emits: [ 'update:modelValue' ],
	setup( props, { emit } ) {
		const message = ref( '' ),
			header = ref( '' ),
			disabled = computed( () => !props.designer.canEdit ),
			rail = computed( () => props.modelValue );

		const customModules = computed( () => rail.value.customModules );

		// The page tools are a rail module only while the toolbar is set to the rail, saved or not
		const toolsInRail = computed( () => props.toolbar.enabled && props.toolbar.style === 'rail' );

		// Custom modules come from the form as they are added, the rest from the wiki
		const entries = computed( () => [
			...props.designer.railModules.filter( ( entry ) => entry.origin !== CUSTOM &&
				( entry.id !== PAGE_TOOLS || toolsInRail.value ) ),
			...customModules.value.map( ( custom ) => ( {
				id: getModuleId( custom ),
				origin: CUSTOM,
				label: custom.header || custom.message
			} ) )
		] );

		const messageError = computed( () => {
			const text = message.value.trim();

			if ( text === '' ) {
				return '';
			}

			if ( !MESSAGE_PATTERN.test( text ) ) {
				return msg( 'rail-custom-invalid' );
			}

			return customModules.value.some( ( custom ) => custom.id === slugify( text ) ) ?
				msg( 'rail-custom-exists' ) :
				'';
		} );

		const canAdd = computed( () => message.value.trim() !== '' && messageError.value === '' );

		function update( patch ) {
			emit( 'update:modelValue', Object.assign( {}, rail.value, patch ) );
		}

		// Recent changes and interface modules take their defaults from the wiki.
		// The rest are on and normal
		function getModuleDefaults( id ) {
			const defaults = props.designer.effective.rail;

			if ( id === RECENT_CHANGES ) {
				return defaults.recentChanges;
			}

			if ( id.startsWith( INTERFACE_PREFIX ) ) {
				return { enabled: true, type: defaults.interface[ id.slice( INTERFACE_PREFIX.length ) ] || 'normal' };
			}

			return { enabled: true, type: 'normal' };
		}

		function setModuleRules( id, patch ) {
			update( { modules: setRules( rail.value.modules, id, patch ) } );
		}

		function addCustomModule() {
			if ( !canAdd.value ) {
				return;
			}

			const text = message.value.trim();

			update( {
				customModules: [
					...customModules.value,
					{ id: slugify( text ), message: text, header: header.value.trim() }
				]
			} );
			message.value = '';
			header.value = '';
		}

		function removeCustomModule( entry ) {
			update( {
				customModules: customModules.value.filter(
					( custom ) => getModuleId( custom ) !== entry.id
				),
				modules: removeRules( rail.value.modules, entry.id )
			} );
		}

		return {
			rail,
			disabled,
			entries,
			message,
			header,
			messageError,
			canAdd,
			msg,
			getRules,
			update,
			getModuleDefaults,
			setModuleRules,
			addCustomModule,
			removeCustomModule
		};
	}
} );
</script>
