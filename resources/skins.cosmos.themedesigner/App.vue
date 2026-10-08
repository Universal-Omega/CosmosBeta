<template>
	<div class="skin-cosmos-themedesigner__layout">
		<div class="skin-cosmos-themedesigner__panel">
			<cdx-message v-if="!designer.canEdit" type="warning">
				{{ msg( 'readonly' ) }}
			</cdx-message>
			<cdx-message v-if="designer.themeOnly">
				{{ msg( 'theme-only' ) }}
			</cdx-message>

			<cdx-tabs v-model:active="activeTab" :framed="false">
				<cdx-tab name="themes" :label="msg( 'tab-themes' )">
					<p>{{ msg( 'presets-intro' ) }}</p>
					<div class="skin-cosmos-themedesigner__presets">
						<button
							v-for="preset in designer.presets"
							:key="preset.name"
							type="button"
							class="skin-cosmos-themedesigner__preset"
							:class="{ 'skin-cosmos-is-active': state.presets[ preset.mode ] === preset.name }"
							:disabled="!designer.canEdit"
							@click="applyPreset( preset )"
						>
							<span class="skin-cosmos-themedesigner__preset-swatches">
								<span
									v-for="slot in swatchSlots"
									:key="slot"
									class="skin-cosmos-themedesigner__swatch"
									:style="{ backgroundColor: preset.colors[ slot ] }"
								></span>
							</span>
							<span class="skin-cosmos-themedesigner__preset-name">
								{{ msg( 'preset-' + preset.name ) }}
							</span>
							<span class="skin-cosmos-themedesigner__preset-mode">
								{{ msg( 'mode-' + preset.mode ) }}
							</span>
						</button>
					</div>
					<p>
						<reset-button
							:path="Object.keys( designer.defaults )"
							message="reset-all"
							confirm="reset-all-confirm"
							@reset="clearColorInputs"
						></reset-button>
					</p>
				</cdx-tab>

				<cdx-tab name="colors" :label="msg( 'tab-colors' )">
					<div class="skin-cosmos-themedesigner__modes">
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
					<p>
						<reset-button
							:path="[ 'palettes/' + editing, 'presets/' + editing ]"
							message="reset-colors"
							@reset="clearColorInputs"
						></reset-button>
					</p>

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
						<div class="skin-cosmos-themedesigner__colorinputs">
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
							<reset-button
								:path="'palettes/' + editing + '/' + slot"
								@reset="clearColorInput( slot )"
							></reset-button>
						</div>
						<div class="skin-cosmos-themedesigner__range skin-cosmos-themedesigner__alpha">
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
						<file-field
							v-model="state.images[ key ]"
							:placeholder="designer.effective.images[ key ] || msg( 'image-default' )"
							:disabled="!designer.canEdit"
							:upload="designer.upload"
							:messages="fileMessages"
						></file-field>
						<reset-button :path="'images/' + key"></reset-button>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'image-size' ) }}
						</template>
						<cdx-select
							v-model:selected="sizeModel"
							:menu-items="sizeItems"
							:disabled="!designer.canEdit"
						></cdx-select>
						<reset-button path="images/backgroundSize"></reset-button>
					</cdx-field>
					<cdx-field>
						<cdx-toggle-switch v-model="repeatModel" :disabled="!designer.canEdit">
							{{ msg( 'image-repeat' ) }}
						</cdx-toggle-switch>
						<reset-button path="images/backgroundRepeat"></reset-button>
					</cdx-field>
					<cdx-field>
						<cdx-toggle-switch v-model="fixedModel" :disabled="!designer.canEdit">
							{{ msg( 'image-fixed' ) }}
						</cdx-toggle-switch>
						<reset-button path="images/backgroundFixed"></reset-button>
					</cdx-field>
				</cdx-tab>

				<cdx-tab name="layout" :label="msg( 'tab-layout' )">
					<cdx-field>
						<template #label>
							{{ msg( 'layout-width' ) }}
						</template>
						<cdx-select
							v-model:selected="widthModel"
							:menu-items="widthItems"
							:disabled="!designer.canEdit"
						></cdx-select>
						<reset-button path="layout/contentWidth"></reset-button>
					</cdx-field>
					<cdx-field>
						<template #label>
							{{ msg( 'layout-font' ) }}
						</template>
						<template #help-text>
							{{ msg( 'layout-font-help' ) }}
						</template>
						<font-picker
							v-model="state.layout.font"
							:presets="designer.fontPresets"
							:fallback="designer.effective.layout.fontFamily"
							:disabled="!designer.canEdit"
						></font-picker>
						<reset-button path="layout/font"></reset-button>
					</cdx-field>
					<cdx-message v-if="state.layout.font.type === 'file' && !fontUpload.extensions.length" type="warning">
						{{ msg( 'font-file-unavailable' ) }}
					</cdx-message>
					<cdx-field v-else-if="state.layout.font.type === 'file'">
						<template #label>
							{{ msg( 'font-file-label' ) }}
						</template>
						<template #help-text>
							{{ msg( 'font-file-help' ) }}
						</template>
						<file-field
							v-model="state.layout.font.value"
							:disabled="!designer.canEdit"
							:upload="fontUpload"
							:messages="fileMessages"
						></file-field>
					</cdx-field>
					<cdx-field v-else-if="state.layout.font.type === 'custom'">
						<template #label>
							{{ msg( 'font-custom-label' ) }}
						</template>
						<template #help-text>
							{{ msg( 'font-custom-help' ) }}
						</template>
						<cdx-text-input
							v-model="state.layout.font.value"
							:disabled="!designer.canEdit"
						></cdx-text-input>
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
						<reset-button path="layout/buttonStyle"></reset-button>
					</cdx-field>
					<cdx-field>
						<cdx-toggle-switch
							v-model="state.layout.headerBorder"
							:disabled="!designer.canEdit"
						>
							{{ msg( 'layout-header-border' ) }}
						</cdx-toggle-switch>
						<reset-button path="layout/headerBorder"></reset-button>
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
						<div class="skin-cosmos-themedesigner__range">
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
							<reset-button :path="range.path"></reset-button>
						</div>
					</cdx-field>
					<cdx-field v-if="designer.portableInfobox">
						<cdx-toggle-switch v-model="europaModel" :disabled="!designer.canEdit">
							{{ msg( 'europa' ) }}
						</cdx-toggle-switch>
						<template #help-text>
							{{ msg( 'europa-help' ) }}
						</template>
						<reset-button path="extensions/portableInfoboxEuropa"></reset-button>
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
						<reset-button path="toolbar/enabled"></reset-button>
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
						<reset-button path="toolbar/style"></reset-button>
					</cdx-field>
					<cdx-message v-if="toolsLost" type="warning">
						{{ msg( 'toolbar-rail-off' ) }}
					</cdx-message>
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
						<reset-button path="toolbar/hiddenItems"></reset-button>
					</cdx-field>

					<cdx-field>
						<template #label>
							{{ msg( 'footer-opacity' ) }}
						</template>
						<div class="skin-cosmos-themedesigner__range">
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
							<reset-button path="footer/opacity"></reset-button>
						</div>
					</cdx-field>
					<cdx-field :help-text="designer.canHideFooterIcons ? '' : msg( 'footer-icons-locked' )">
						<cdx-toggle-switch
							v-model="state.footer.showIcons"
							:disabled="!designer.canEdit || !designer.canHideFooterIcons"
						>
							{{ msg( 'footer-icons' ) }}
						</cdx-toggle-switch>
						<reset-button path="footer/showIcons"></reset-button>
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
						<reset-button path="footer/hiddenLinks"></reset-button>
					</cdx-field>
				</cdx-tab>

				<cdx-tab name="rail" :label="msg( 'tab-rail' )">
					<rail-tab v-model="state.rail" :toolbar="state.toolbar" :designer="designer"></rail-tab>
				</cdx-tab>

				<cdx-tab name="darkmode" :label="msg( 'tab-darkmode' )">
					<p>{{ msg( 'darkmode-intro' ) }}</p>
					<cdx-field>
						<cdx-toggle-switch v-model="state.colorMode.toggle" :disabled="!designer.canEdit">
							{{ msg( 'darkmode-toggle' ) }}
						</cdx-toggle-switch>
						<reset-button path="colorMode/toggle"></reset-button>
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
						<reset-button path="colorMode/default"></reset-button>
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
					<ul v-else class="skin-cosmos-themedesigner__history">
						<li v-for="row in designer.history" :key="row.id">
							<span class="skin-cosmos-themedesigner__history-main">
								{{ row.time }} &middot; {{ row.user }}
							</span>
							<span v-if="row.comment" class="skin-cosmos-themedesigner__help">
								{{ row.comment }}
							</span>
							<span v-if="row.id === designer.revisionId" class="skin-cosmos-themedesigner__badge">
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

		<div class="skin-cosmos-themedesigner__side">
			<div class="skin-cosmos-themedesigner__preview" :style="preview.root" :aria-label="msg( 'preview' )">
				<div class="skin-cosmos-themedesigner__preview-banner" :style="preview.banner">
					<span>{{ msg( 'preview-wordmark' ) }}</span>
				</div>
				<div class="skin-cosmos-themedesigner__preview-header" :style="preview.header">
					<span>Menu&nbsp;&nbsp;Menu&nbsp;&nbsp;Menu</span>
				</div>
				<div class="skin-cosmos-themedesigner__preview-body">
					<div class="skin-cosmos-themedesigner__preview-main" :style="preview.content">
						<div class="skin-cosmos-themedesigner__preview-heading">
							{{ msg( 'preview-heading' ) }}
						</div>
						<div>{{ msg( 'preview-text' ) }}</div>
						<div class="skin-cosmos-themedesigner__preview-link" :style="preview.link">
							{{ msg( 'preview-link' ) }}
						</div>
						<div class="skin-cosmos-themedesigner__preview-button" :style="preview.button">
							{{ msg( 'preview-button' ) }}
						</div>
					</div>
					<div v-if="state.rail.enabled" class="skin-cosmos-themedesigner__preview-rail" :style="preview.content">
						{{ msg( 'preview-rail' ) }}
						<div
							v-if="state.toolbar.enabled && state.toolbar.style === 'rail'"
							class="skin-cosmos-themedesigner__preview-rail-tools"
							:style="preview.toolbar"
						>
							{{ msg( 'preview-toolbar' ) }}
						</div>
					</div>
				</div>
				<div class="skin-cosmos-themedesigner__preview-footer" :style="preview.footer">
					{{ msg( 'preview-footer' ) }}
				</div>
				<div
					v-if="state.toolbar.enabled && state.toolbar.style !== 'rail'"
					class="skin-cosmos-themedesigner__preview-toolbar"
					:class="'skin-cosmos-themedesigner__preview-toolbar--' + ( state.toolbar.style === 'floating' ? 'floating' : 'bar' )"
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
		CdxSelect,
		CdxTab,
		CdxTabs,
		CdxTextInput,
		CdxToggleSwitch
	} = mw.loader.require( 'skins.cosmos.themedesigner.codex' ),
	{ computed, defineComponent, onMounted, reactive, ref, watch } = require( 'vue' ),
	colors = require( './colors.js' ),
	FileField = require( './FileField.vue' ),
	FontPicker = require( './FontPicker.vue' ),
	{ getStack: getFontStack } = require( './font.js' ),
	msg = require( './msg.js' ),
	RailTab = require( './rail/RailTab.vue' ),
	ResetButton = require( './ResetButton.vue' ),
	{ provideReset } = require( './reset.js' );

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
		CdxSelect,
		CdxTab,
		CdxTabs,
		CdxTextInput,
		CdxToggleSwitch,
		FileField,
		FontPicker,
		RailTab,
		ResetButton
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

		const items = ( pairs ) => pairs.map( ( [ value, label ] ) => ( { value, label } ) ),
			effective = props.designer.effective;

		const sizeItems = items( [ [ 'auto', 'auto' ], [ 'contain', 'contain' ], [ 'cover', 'cover' ] ] ),
			widthItems = items( [
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
			modeItems = items( [ ...MODES, 'auto' ].map( ( mode ) => [ mode, msg( 'mode-' + mode ) ] ) );

		// What the theme does not set shows as the default it falls back to
		const fallbackModel = ( section, key, fallback ) => computed( {
			get: () => state[ section ][ key ] || fallback,
			set: ( value ) => {
				state[ section ][ key ] = value;
			}
		} );

		const flagModel = ( section, key, fallback ) => computed( {
			get: () => state[ section ][ key ] ?? fallback,
			set: ( value ) => {
				state[ section ][ key ] = value;
			}
		} );

		const sizeModel = fallbackModel( 'images', 'backgroundSize', effective.images.backgroundSize ),
			widthModel = fallbackModel( 'layout', 'contentWidth', effective.layout.contentWidth ),
			repeatModel = flagModel( 'images', 'backgroundRepeat', effective.images.backgroundRepeat ),
			fixedModel = flagModel( 'images', 'backgroundFixed', effective.images.backgroundFixed ),
			europaModel = flagModel( 'extensions', 'portableInfoboxEuropa', effective.extensions.portableInfoboxEuropa );

		provideReset( state, props.designer.defaults, () => !props.designer.canEdit );

		// Page tools in the rail have nowhere to show once the rail is off
		const toolsLost = computed( () => state.toolbar.enabled && state.toolbar.style === 'rail' && !state.rail.enabled );

		const fileMessages = {
			upload: msg( 'file-upload' ),
			uploading: msg( 'file-uploading' ),
			exists: msg( 'file-upload-exists' ),
			failed: msg( 'file-upload-failed' ),
			comment: msg( 'file-upload-comment' ),
			noResults: msg( 'rail-no-results' )
		};

		const fontUpload = {
			enabled: props.designer.upload.enabled,
			extensions: props.designer.upload.fontExtensions
		};

		const fontStack = computed( () => getFontStack( state.layout.font, props.designer.fontPresets, effective.layout.fontFamily ) );

		const layoutRanges = [
			{
				key: 'content',
				max: 100,
				unit: '%',
				label: 'layout-opacity',
				path: 'layout/contentOpacity',
				get: () => state.layout.contentOpacity === null ? effective.layout.contentOpacity : state.layout.contentOpacity,
				set: ( value ) => {
					state.layout.contentOpacity = value;
				}
			},
			{
				key: 'header',
				max: 100,
				unit: '%',
				label: 'layout-header-button-opacity',
				path: 'layout/headerButtonOpacity',
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
				path: 'layout/backdropBlur',
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

		function clearColorInput( slot ) {
			delete rawColors[ rawKey( slot ) ];
			colorErrors[ rawKey( slot ) ] = false;
		}

		function clearColorInputs() {
			Object.keys( rawColors ).forEach( ( key ) => delete rawColors[ key ] );
			Object.keys( colorErrors ).forEach( ( key ) => delete colorErrors[ key ] );
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
					effective.layout.contentOpacity :
					state.layout.contentOpacity ) / 100,
				on = ( slot ) => ( { background: color( slot ), color: colors.readableOn( color( slot ) ) } );

			return {
				root: { background: color( 'body' ), fontFamily: fontStack.value },
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
			widthItems,
			toolbarStyleItems,
			modeItems,
			sizeModel,
			widthModel,
			repeatModel,
			fixedModel,
			europaModel,
			toolsLost,
			buttonStyleItems,
			fileMessages,
			fontUpload,
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
			clearColorInput,
			clearColorInputs,
			applyPreset,
			generateDark,
			restore
		};
	}
} );
</script>
