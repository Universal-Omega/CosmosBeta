<template>
	<div class="skin-cosmos-td-layout">
		<div class="skin-cosmos-td-panel">
			<cdx-message v-if="!designer.canEdit" type="warning">
				{{ msg( 'readonly' ) }}
			</cdx-message>

			<cdx-tabs v-model:active="activeTab" :framed="false">
				<cdx-tab name="themes" :label="msg( 'tab-themes' )">
					<p>{{ msg( 'presets-intro' ) }}</p>
					<div class="skin-cosmos-td-presets">
						<button
							v-for="preset in designer.presets"
							:key="preset.name"
							type="button"
							class="skin-cosmos-td-preset"
							:class="{ 'is-active': state.presets[ preset.mode ] === preset.name }"
							:disabled="!designer.canEdit"
							@click="applyPreset( preset )"
						>
							<span class="skin-cosmos-td-preset-swatches">
								<span
									v-for="slot in swatchSlots"
									:key="slot"
									class="skin-cosmos-td-swatch"
									:style="{ background: preset.colors[ slot ] }"
								></span>
							</span>
							<span class="skin-cosmos-td-preset-name">
								{{ msg( 'preset-' + preset.name ) }}
							</span>
							<span class="skin-cosmos-td-preset-mode">
								{{ msg( 'mode-' + preset.mode ) }}
							</span>
						</button>
					</div>
				</cdx-tab>

				<cdx-tab name="colors" :label="msg( 'tab-colors' )">
					<div class="skin-cosmos-td-modes">
						<span>{{ msg( 'editing' ) }}</span>
						<cdx-button
							v-for="mode in modes"
							:key="mode"
							type="button"
							:action="editing === mode ? 'progressive' : 'default'"
							:weight="editing === mode ? 'primary' : 'normal'"
							@click="editing = mode"
						>
							{{ msg( 'mode-' + mode ) }}
						</cdx-button>
					</div>
					<p>{{ msg( 'colors-intro' ) }}</p>

					<cdx-field
						v-for="slot in designer.slots"
						:key="slot"
						:status="hasColorError( slot ) ? 'error' : 'default'"
						:messages="{ error: msg( 'color-invalid' ) }"
					>
						<template #label>
							{{ msg( 'color-' + slot ) }}
						</template>
						<template #description>
							{{ msg( 'color-' + slot + '-help' ) }}
						</template>
						<div class="skin-cosmos-td-colorinputs">
							<input
								type="color"
								:value="pickerValue( slot )"
								:disabled="!designer.canEdit"
								:aria-label="msg( 'color-' + slot )"
								@input="setPicked( slot, $event.target.value )"
							>
							<cdx-text-input
								:model-value="colorText( slot )"
								:placeholder="designer.fallbacks[ editing ][ slot ]"
								:disabled="!designer.canEdit"
								@update:model-value="setColor( slot, $event )"
							></cdx-text-input>
							<cdx-button
								type="button"
								weight="quiet"
								:disabled="!designer.canEdit"
								@click="resetColor( slot )"
							>
								{{ msg( 'color-reset' ) }}
							</cdx-button>
						</div>
						<div class="skin-cosmos-td-range skin-cosmos-td-alpha">
							<span>{{ msg( 'color-opacity' ) }}</span>
							<input
								type="range"
								min="0"
								max="100"
								step="1"
								:value="alphaValue( slot )"
								:disabled="!designer.canEdit"
								:aria-label="msg( 'color-opacity' )"
								@input="setAlpha( slot, Number( $event.target.value ) )"
							>
							<output>{{ alphaValue( slot ) }}%</output>
						</div>
					</cdx-field>
				</cdx-tab>

				<cdx-tab name="images" :label="msg( 'tab-images' )">
					<p>{{ msg( 'images-intro' ) }}</p>
					<cdx-field v-for="key in imageFields" :key="key">
						<template #label>
							{{ msg( 'image-' + key ) }}
						</template>
						<image-field
							v-model="state.images[ key ]"
							:placeholder="msg( 'image-default' )"
							:disabled="!designer.canEdit"
							:upload="designer.upload"
							:messages="imageMessages"
						></image-field>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'image-size' ) }}
						</template>
						<cdx-select
							v-model:selected="state.images.backgroundSize"
							:menu-items="sizeItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'image-repeat' ) }}
						</template>
						<cdx-select
							v-model:selected="repeatModel"
							:menu-items="triStateItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'image-fixed' ) }}
						</template>
						<cdx-select
							v-model:selected="fixedModel"
							:menu-items="triStateItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
				</cdx-tab>

				<cdx-tab name="layout" :label="msg( 'tab-layout' )">
					<cdx-field>
						<template #label>
							{{ msg( 'layout-width' ) }}
						</template>
						<cdx-select
							v-model:selected="state.layout.contentWidth"
							:menu-items="widthItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'layout-button-style' ) }}
						</template>
						<template #help-text>
							{{ msg( 'layout-button-style-help' ) }}
						</template>
						<cdx-select
							v-model:selected="state.layout.buttonStyle"
							:menu-items="buttonStyleItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
					<cdx-field>
						<cdx-toggle-switch
							v-model="state.layout.headerBorder"
							:disabled="!designer.canEdit"
						>
							{{ msg( 'layout-header-border' ) }}
						</cdx-toggle-switch>
						<template #help-text>
							{{ msg( 'layout-header-border-help' ) }}
						</template>
					</cdx-field>
					<cdx-field v-for="range in layoutRanges" :key="range.key">
						<template #label>
							{{ msg( range.label ) }}
						</template>
						<template v-if="range.help" #help-text>
							{{ msg( range.help ) }}
						</template>
						<div class="skin-cosmos-td-range">
							<input
								type="range"
								min="0"
								:max="range.max"
								step="1"
								:value="range.get()"
								:disabled="!designer.canEdit"
								@input="range.set( Number( $event.target.value ) )"
							>
							<output>{{ range.get() }}{{ range.unit }}</output>
						</div>
					</cdx-field>
				</cdx-tab>

				<cdx-tab name="chrome" :label="msg( 'tab-chrome' )">
					<cdx-field>
						<cdx-toggle-switch
							v-model="state.toolbar.enabled"
							:disabled="!designer.canEdit"
						>
							{{ msg( 'toolbar-enabled' ) }}
						</cdx-toggle-switch>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'toolbar-style' ) }}
						</template>
						<template #help-text>
							{{ msg( 'toolbar-style-help' ) }}
						</template>
						<cdx-select
							v-model:selected="state.toolbar.style"
							:menu-items="toolbarStyleItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
					<cdx-field :is-fieldset="true">
						<template #label>
							{{ msg( 'toolbar-hidden' ) }}
						</template>
						<template #description>
							{{ msg( 'toolbar-hidden-help' ) }}
						</template>
						<cdx-checkbox
							v-for="item in designer.toolbarItems"
							:key="item.name"
							v-model="state.toolbar.hiddenItems"
							:input-value="item.name"
							:disabled="!designer.canEdit"
						>
							{{ item.label }}
						</cdx-checkbox>
					</cdx-field>

					<cdx-field>
						<template #label>
							{{ msg( 'footer-opacity' ) }}
						</template>
						<div class="skin-cosmos-td-range">
							<input
								type="range"
								min="0"
								max="100"
								step="1"
								:value="state.footer.opacity"
								:disabled="!designer.canEdit"
								@input="state.footer.opacity = Number( $event.target.value )"
							>
							<output>{{ state.footer.opacity }}%</output>
						</div>
					</cdx-field>
					<cdx-field :help-text="designer.canHideFooterIcons ? '' : msg( 'footer-icons-locked' )">
						<cdx-toggle-switch
							v-model="state.footer.showIcons"
							:disabled="!designer.canEdit || !designer.canHideFooterIcons"
						>
							{{ msg( 'footer-icons' ) }}
						</cdx-toggle-switch>
					</cdx-field>
					<cdx-field :is-fieldset="true">
						<template #label>
							{{ msg( 'footer-hidden' ) }}
						</template>
						<template #description>
							{{ msg( 'footer-hidden-help' ) }}
						</template>
						<cdx-checkbox
							v-for="item in designer.footerLinks"
							:key="item.name"
							v-model="state.footer.hiddenLinks"
							:input-value="item.name"
							:disabled="!designer.canEdit || item.protected"
							:title="item.protected ? msg( 'protected' ) : null"
						>
							{{ item.label }}
						</cdx-checkbox>
					</cdx-field>
				</cdx-tab>

				<cdx-tab name="rail" :label="msg( 'tab-rail' )">
					<cdx-field>
						<cdx-toggle-switch v-model="state.rail.enabled" :disabled="!designer.canEdit">
							{{ msg( 'rail-enabled' ) }}
						</cdx-toggle-switch>
					</cdx-field>
					<cdx-field>
						<cdx-toggle-switch v-model="state.rail.hideForAnons" :disabled="!designer.canEdit">
							{{ msg( 'rail-anons' ) }}
						</cdx-toggle-switch>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'rail-recentchanges' ) }}
						</template>
						<cdx-select
							v-model:selected="state.rail.recentChanges"
							:menu-items="recentChangesItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'rail-namespaces' ) }}
						</template>
						<template #help-text>
							{{ msg( 'rail-namespaces-help' ) }}
						</template>
						<cdx-multiselect-lookup
							v-model:selected="namespaceSelected"
							v-model:input-chips="namespaceChips"
							v-model:input-value="namespaceInput"
							:menu-items="namespaceItems"
							:menu-config="{ visibleItemLimit: 8 }"
							:disabled="!designer.canEdit"
							:placeholder="msg( 'rail-namespaces-placeholder' )"
						>
							<template #no-results>
								{{ msg( 'rail-no-results' ) }}
							</template>
						</cdx-multiselect-lookup>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'rail-pages' ) }}
						</template>
						<template #help-text>
							{{ msg( 'rail-pages-help' ) }}
						</template>
						<cdx-multiselect-lookup
							v-model:selected="pageSelected"
							v-model:input-chips="pageChips"
							v-model:input-value="pageInput"
							:menu-items="pageItems"
							:menu-config="{ visibleItemLimit: 8 }"
							:disabled="!designer.canEdit"
							:placeholder="msg( 'rail-pages-placeholder' )"
						>
							<template #no-results>
								{{ msg( 'rail-no-results' ) }}
							</template>
						</cdx-multiselect-lookup>
					</cdx-field>
				</cdx-tab>

				<cdx-tab name="darkmode" :label="msg( 'tab-darkmode' )">
					<p>{{ msg( 'darkmode-intro' ) }}</p>
					<cdx-field>
						<cdx-toggle-switch v-model="state.colorMode.toggle" :disabled="!designer.canEdit">
							{{ msg( 'darkmode-toggle' ) }}
						</cdx-toggle-switch>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'darkmode-default' ) }}
						</template>
						<template #help-text>
							{{ msg( 'darkmode-auto-help' ) }}
						</template>
						<cdx-select
							v-model:selected="state.colorMode.default"
							:menu-items="modeItems"
							:disabled="!designer.canEdit"
						></cdx-select>
					</cdx-field>
					<cdx-message>
						{{ msg( 'darkmode-builtin' ) }}
					</cdx-message>
					<p>
						<cdx-button type="button" :disabled="!designer.canEdit" @click="generateDark">
							{{ msg( 'darkmode-generate' ) }}
						</cdx-button>
					</p>
				</cdx-tab>

				<cdx-tab name="history" :label="msg( 'tab-history' )">
					<p>{{ msg( 'history-intro' ) }}</p>
					<p v-if="!designer.history.length">
						{{ msg( 'history-empty' ) }}
					</p>
					<ul v-else class="skin-cosmos-td-history">
						<li v-for="row in designer.history" :key="row.id">
							<span class="skin-cosmos-td-history-main">
								{{ row.time }} &middot; {{ row.user }}
							</span>
							<span v-if="row.comment" class="skin-cosmos-td-help">
								{{ row.comment }}
							</span>
							<span v-if="row.id === designer.revisionId" class="skin-cosmos-td-badge">
								{{ msg( 'history-live' ) }}
							</span>
							<cdx-button
							type="button"
								v-else-if="designer.canEdit"
								weight="quiet"
								action="progressive"
								@click="restore( row.id )"
							>
								{{ msg( 'history-restore' ) }}
							</cdx-button>
						</li>
					</ul>
				</cdx-tab>
			</cdx-tabs>
		</div>

		<div class="skin-cosmos-td-side">
			<div class="skin-cosmos-td-preview" :style="preview.root" :aria-label="msg( 'preview' )">
				<div class="skin-cosmos-td-pv-banner" :style="preview.banner">
					<span>{{ msg( 'preview-wordmark' ) }}</span>
				</div>
				<div class="skin-cosmos-td-pv-header" :style="preview.header">
					<span>Menu&nbsp;&nbsp;Menu&nbsp;&nbsp;Menu</span>
				</div>
				<div class="skin-cosmos-td-pv-body">
					<div class="skin-cosmos-td-pv-main" :style="preview.content">
						<div class="skin-cosmos-td-pv-heading">
							{{ msg( 'preview-heading' ) }}
						</div>
						<div>{{ msg( 'preview-text' ) }}</div>
						<div class="skin-cosmos-td-pv-link" :style="preview.link">
							{{ msg( 'preview-link' ) }}
						</div>
						<div class="skin-cosmos-td-pv-button" :style="preview.button">
							{{ msg( 'preview-button' ) }}
						</div>
					</div>
					<div v-if="state.rail.enabled" class="skin-cosmos-td-pv-rail" :style="preview.content">
						{{ msg( 'preview-rail' ) }}
						<div
							v-if="state.toolbar.enabled && state.toolbar.style === 'rail'"
							class="skin-cosmos-td-pv-railtools"
							:style="preview.toolbar"
						>
							{{ msg( 'preview-toolbar' ) }}
						</div>
					</div>
				</div>
				<div class="skin-cosmos-td-pv-footer" :style="preview.footer">
					{{ msg( 'preview-footer' ) }}
				</div>
				<div
					v-if="state.toolbar.enabled && ( state.toolbar.style !== 'rail' || !state.rail.enabled )"
					class="skin-cosmos-td-pv-toolbar"
					:class="'skin-cosmos-td-pv-toolbar--' + ( state.toolbar.style === 'floating' ? 'floating' : 'bar' )"
					:style="preview.toolbar"
				>
					{{ msg( 'preview-toolbar' ) }}
				</div>
			</div>
		</div>
	</div>
</template>

<script>
const {
		CdxButton,
		CdxCheckbox,
		CdxField,
		CdxMessage,
		CdxMultiselectLookup,
		CdxSelect,
		CdxTab,
		CdxTabs,
		CdxTextInput,
		CdxToggleSwitch
	} = mw.loader.require( 'skins.cosmosbeta.themedesigner.codex' ),
	{ computed, defineComponent, onMounted, reactive, ref, watch } = require( 'vue' ),
	colors = require( './colors.js' ),
	ImageField = require( './ImageField.vue' );

const MODES = [ 'light', 'dark' ];

function clone( value ) {
	return JSON.parse( JSON.stringify( value ) );
}

function isObject( value ) {
	return value !== null && typeof value === 'object' && !Array.isArray( value );
}

function merge( base, extra ) {
	Object.keys( extra ).forEach( ( key ) => {
		base[ key ] = isObject( base[ key ] ) && isObject( extra[ key ] ) ?
			merge( base[ key ], extra[ key ] ) :
			extra[ key ];
	} );

	return base;
}

function msg( key ) {
	// Messages used here: cosmosbeta-themedesigner-*
	// eslint-disable-next-line mediawiki/msg-doc
	return mw.msg( 'cosmosbeta-themedesigner-' + key );
}

// @vue/component
module.exports = exports = defineComponent( {
	name: 'ThemeDesigner',
	compilerOptions: {
		whitespace: 'condense'
	},
	components: {
		CdxButton,
		CdxCheckbox,
		CdxField,
		CdxMessage,
		CdxMultiselectLookup,
		CdxSelect,
		CdxTab,
		CdxTabs,
		CdxTextInput,
		CdxToggleSwitch,
		ImageField
	},
	props: {
		designer: {
			type: Object,
			required: true
		},
		jsonField: {
			type: HTMLTextAreaElement,
			required: true
		},
		form: {
			type: HTMLFormElement,
			required: true
		}
	},
	setup( props ) {
		const state = reactive( clone( props.designer.settings ) ),
			activeTab = ref( 'themes' ),
			editing = ref( state.colorMode.default === 'dark' ? 'dark' : 'light' ),
			rawColors = reactive( {} ),
			colorErrors = reactive( {} );

		const swatchSlots = [ 'banner', 'body', 'content', 'button', 'link' ],
			imageFields = [ 'wordmark', 'header', 'background' ];

		const items = ( pairs ) => pairs.map( ( [ value, label ] ) => ( { value, label } ) );
		const inherit = msg( 'inherit' );

		const sizeItems = items( [ [ '', inherit ], [ 'auto', 'auto' ], [ 'contain', 'contain' ], [ 'cover', 'cover' ] ] ),
			triStateItems = items( [ [ '', inherit ], [ 'true', msg( 'yes' ) ], [ 'false', msg( 'no' ) ] ] ),
			widthItems = items( [
				[ '', inherit ],
				[ 'default', msg( 'layout-width-default' ) ],
				[ 'large', msg( 'layout-width-large' ) ],
				[ 'full', msg( 'layout-width-full' ) ]
			] ),
			buttonStyleItems = items( [
				[ 'default', msg( 'layout-button-style-default' ) ],
				[ 'slim', msg( 'layout-button-style-slim' ) ],
				[ 'pill', msg( 'layout-button-style-pill' ) ],
				[ 'text', msg( 'layout-button-style-text' ) ]
			] ),
			toolbarStyleItems = items( [
				[ 'floating', msg( 'toolbar-style-floating' ) ],
				[ 'bar', msg( 'toolbar-style-bar' ) ],
				[ 'rail', msg( 'toolbar-style-rail' ) ]
			] ),
			recentChangesItems = items( [
				[ '', msg( 'rail-recentchanges-config' ) ],
				[ 'off', msg( 'rail-recentchanges-off' ) ],
				[ 'normal', msg( 'rail-recentchanges-normal' ) ],
				[ 'sticky', msg( 'rail-recentchanges-sticky' ) ]
			] ),
			modeItems = items( [ ...MODES, 'auto' ].map( ( mode ) => [ mode, msg( 'mode-' + mode ) ] ) );

		const triModel = ( key ) => computed( {
			get: () => state.images[ key ] === null ? '' : String( state.images[ key ] ),
			set: ( value ) => {
				state.images[ key ] = value === '' ? null : value === 'true';
			}
		} );

		const repeatModel = triModel( 'backgroundRepeat' ),
			fixedModel = triModel( 'backgroundFixed' );

		const imageMessages = {
			upload: msg( 'image-upload' ),
			uploading: msg( 'image-uploading' ),
			exists: msg( 'image-upload-exists' ),
			failed: msg( 'image-upload-failed' ),
			comment: msg( 'image-upload-comment' ),
			noResults: msg( 'rail-no-results' )
		};

		const mainPageValue = 'mainpage',
			namespaceLabels = {};

		props.designer.namespaces.forEach( ( ns ) => {
			namespaceLabels[ ns.value ] = ns.label;
		} );

		const pageLabel = ( value ) => value === mainPageValue ? msg( 'rail-pages-mainpage' ) : value,
			sameList = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

		const namespaceSelected = ref( ( state.rail.disabledNamespaces || [] ).slice() ),
			namespaceChips = ref( namespaceSelected.value.map( ( value ) => ( { value, label: namespaceLabels[ value ] || String( value ) } ) ) ),
			namespaceInput = ref( '' ),
			pageSelected = ref( ( state.rail.disabledPages || [] ).slice() ),
			pageChips = ref( pageSelected.value.map( ( value ) => ( { value, label: pageLabel( value ) } ) ) ),
			pageInput = ref( '' ),
			pageResults = ref( [] );

		const namespaceItems = computed( () => {
			const term = String( namespaceInput.value || '' ).trim().toLowerCase();

			return props.designer.namespaces.filter( ( ns ) => term === '' || ns.label.toLowerCase().includes( term ) );
		} );

		const pageItems = computed( () => {
			const term = String( pageInput.value || '' ).trim(),
				list = [ { value: mainPageValue, label: pageLabel( mainPageValue ) } ];

			pageResults.value.forEach( ( title ) => list.push( { value: title, label: title } ) );

			if ( term !== '' && !list.some( ( item ) => item.value === term ) ) {
				list.push( { value: term, label: term } );
			}

			return list.filter( ( item ) => term === '' || item.label.toLowerCase().includes( term.toLowerCase() ) );
		} );

		const mainNamespaces = props.designer.namespaces.map( ( ns ) => ns.value ).filter( ( id ) => id >= 0 );
		let pageTimer = null,
			pageRequest = 0;

		watch( pageInput, ( value ) => {
			const term = String( value || '' ).trim(),
				id = ++pageRequest;

			clearTimeout( pageTimer );

			if ( term === '' ) {
				pageResults.value = [];
				return;
			}

			pageTimer = setTimeout( () => {
				new mw.Api().get( {
					action: 'opensearch',
					search: term,
					limit: 10,
					namespace: mainNamespaces.join( '|' )
				} ).then( ( data ) => {
					if ( id === pageRequest ) {
						pageResults.value = data[ 1 ] || [];
					}
				} );
			}, 250 );
		} );

		watch( namespaceSelected, ( value ) => {
			state.rail.disabledNamespaces = value.length ? value.map( Number ) : null;
		}, { deep: true } );

		watch( pageSelected, ( value ) => {
			state.rail.disabledPages = value.length ? value.slice() : null;
		}, { deep: true } );

		watch( () => state.rail.disabledNamespaces, ( value ) => {
			const list = value || [];

			if ( !sameList( list, namespaceSelected.value ) ) {
				namespaceSelected.value = list.slice();
				namespaceChips.value = list.map( ( id ) => ( { value: id, label: namespaceLabels[ id ] || String( id ) } ) );
			}
		}, { deep: true } );

		watch( () => state.rail.disabledPages, ( value ) => {
			const list = value || [];

			if ( !sameList( list, pageSelected.value ) ) {
				pageSelected.value = list.slice();
				pageChips.value = list.map( ( title ) => ( { value: title, label: pageLabel( title ) } ) );
			}
		}, { deep: true } );

		const layoutRanges = [
			{
				key: 'content',
				max: 100,
				unit: '%',
				label: 'layout-opacity',
				get: () => state.layout.contentOpacity === null ? props.designer.config.contentOpacity : state.layout.contentOpacity,
				set: ( value ) => {
					state.layout.contentOpacity = value;
				}
			},
			{
				key: 'header',
				max: 100,
				unit: '%',
				label: 'layout-header-button-opacity',
				get: () => state.layout.headerButtonOpacity,
				set: ( value ) => {
					state.layout.headerButtonOpacity = value;
				}
			},
			{
				key: 'blur',
				max: 40,
				unit: 'px',
				label: 'layout-backdrop-blur',
				help: 'layout-backdrop-blur-help',
				get: () => state.layout.backdropBlur,
				set: ( value ) => {
					state.layout.backdropBlur = value;
				}
			}
		];

		const effectiveColor = ( mode, slot ) => state.palettes[ mode ][ slot ] || props.designer.fallbacks[ mode ][ slot ],
			rawKey = ( slot ) => editing.value + ':' + slot;

		const colorText = ( slot ) => rawColors[ rawKey( slot ) ] ?? state.palettes[ editing.value ][ slot ] ?? '',
			hasColorError = ( slot ) => !!colorErrors[ rawKey( slot ) ],
			pickerValue = ( slot ) => {
				const parsed = colors.parse( effectiveColor( editing.value, slot ) );

				return parsed ? colors.toHex( parsed ) : '#000000';
			};

		function setPicked( slot, hex ) {
			const alpha = colors.parse( effectiveColor( editing.value, slot ) )?.a ?? 1,
				picked = colors.parse( hex );

			if ( !picked ) {
				return;
			}

			picked.a = alpha;
			delete rawColors[ rawKey( slot ) ];
			colorErrors[ rawKey( slot ) ] = false;
			state.palettes[ editing.value ][ slot ] = colors.toCanonical( picked );
			state.presets[ editing.value ] = '';
		}

		function setColor( slot, value ) {
			const key = rawKey( slot );

			rawColors[ key ] = value;

			if ( value.trim() === '' ) {
				colorErrors[ key ] = false;
				delete state.palettes[ editing.value ][ slot ];
				return;
			}

			const normal = colors.normalize( value );

			if ( normal === null ) {
				colorErrors[ key ] = true;
				return;
			}

			colorErrors[ key ] = false;
			state.palettes[ editing.value ][ slot ] = normal;
			state.presets[ editing.value ] = '';
		}

		function alphaValue( slot ) {
			const parsed = colors.parse( effectiveColor( editing.value, slot ) );

			return parsed ? Math.round( parsed.a * 100 ) : 100;
		}

		function setAlpha( slot, percent ) {
			const parsed = colors.parse( effectiveColor( editing.value, slot ) );

			if ( !parsed ) {
				return;
			}

			parsed.a = percent / 100;
			delete rawColors[ rawKey( slot ) ];
			colorErrors[ rawKey( slot ) ] = false;
			state.palettes[ editing.value ][ slot ] = colors.toCanonical( parsed );
			state.presets[ editing.value ] = '';
		}

		function resetColor( slot ) {
			const key = rawKey( slot );

			delete rawColors[ key ];
			colorErrors[ key ] = false;
			delete state.palettes[ editing.value ][ slot ];
		}

		function applyPreset( preset ) {
			state.palettes[ preset.mode ] = Object.assign( {}, preset.colors );
			state.presets[ preset.mode ] = preset.name;
			editing.value = preset.mode;
			Object.keys( rawColors ).forEach( ( key ) => delete rawColors[ key ] );
			mw.notify( msg( 'preset-applied' ) );
		}

		function generateDark() {
			state.palettes.dark = colors.deriveDark( state.palettes.light );
			state.presets.dark = '';
			editing.value = 'dark';
			Object.keys( rawColors ).forEach( ( key ) => delete rawColors[ key ] );
			mw.notify( msg( 'darkmode-generated' ) );
		}

		function restore( id ) {
			const input = document.createElement( 'input' );

			input.type = 'hidden';
			input.name = 'wpRevertTo';
			input.value = String( id );
			props.form.appendChild( input );
			props.form.submit();
		}

		const preview = computed( () => {
			const color = ( slot ) => effectiveColor( editing.value, slot ),
				content = color( 'content' ),
				parsed = colors.parse( content ),
				opacity = ( state.layout.contentOpacity === null ?
					props.designer.config.contentOpacity :
					state.layout.contentOpacity ) / 100,
				on = ( slot ) => ( { background: color( slot ), color: colors.readableOn( color( slot ) ) } );

			return {
				root: { background: color( 'body' ) },
				blur: state.layout.backdropBlur > 0 ? { backdropFilter: 'blur(' + state.layout.backdropBlur + 'px)' } : {},
				banner: on( 'banner' ),
				header: on( 'header' ),
				content: {
					background: parsed ?
						'rgba(' + parsed.r + ',' + parsed.g + ',' + parsed.b + ',' + opacity + ')' :
						content,
					color: colors.contentTextOn( content )
				},
				link: { color: color( 'link' ) },
				button: on( 'button' ),
				footer: on( 'footer' ),
				toolbar: on( 'toolbar' )
			};
		} );

		function load( text ) {
			let parsed;

			try {
				parsed = JSON.parse( text );
			} catch ( e ) {
				mw.notify( msg( 'error-json' ), { type: 'error' } );
				return;
			}

			if ( !isObject( parsed ) ) {
				mw.notify( msg( 'error-json' ), { type: 'error' } );
				return;
			}

			const next = merge( clone( props.designer.defaults ), parsed );

			Object.keys( next ).forEach( ( key ) => {
				state[ key ] = next[ key ];
			} );
		}

		watch( state, () => {
			props.jsonField.value = JSON.stringify( state, null, '\t' );
		}, { deep: true, immediate: true } );

		onMounted( () => {
			props.jsonField.addEventListener( 'change', () => load( props.jsonField.value ) );
		} );

		return {
			state,
			activeTab,
			editing,
			modes: MODES,
			swatchSlots,
			imageFields,
			sizeItems,
			triStateItems,
			widthItems,
			toolbarStyleItems,
			recentChangesItems,
			modeItems,
			repeatModel,
			fixedModel,
			buttonStyleItems,
			imageMessages,
			namespaceSelected,
			namespaceChips,
			namespaceInput,
			namespaceItems,
			pageSelected,
			pageChips,
			pageInput,
			pageItems,
			layoutRanges,
			preview,
			msg,
			colorText,
			hasColorError,
			pickerValue,
			setColor,
			setPicked,
			setAlpha,
			alphaValue,
			resetColor,
			applyPreset,
			generateDark,
			restore
		};
	}
} );
</script>
