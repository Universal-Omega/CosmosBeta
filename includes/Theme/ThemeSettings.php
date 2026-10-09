<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

use MediaWiki\Skin\Cosmos\LessUtil;
use MediaWiki\Skin\Cosmos\Rail\RailModule;
use MediaWiki\Skin\Cosmos\Rail\RailModuleType;
use function array_merge;
use function array_slice;
use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function mb_substr;
use function min;
use function number_format;
use function preg_match;
use function preg_replace;
use function round;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Immutable, validated theme data. Anything unknown or malformed is dropped,
 * so values read from storage are always safe to hand to LESS.
 */
class ThemeSettings {

	public const int SCHEMA_VERSION = 1;

	public const string MODE_LIGHT = 'light';
	public const string MODE_DARK = 'dark';
	private const string MODE_AUTO = 'auto';

	public const array MODES = [ self::MODE_LIGHT, self::MODE_DARK ];
	private const array DEFAULT_MODES = [ self::MODE_LIGHT, self::MODE_DARK, self::MODE_AUTO ];

	public const array COLOR_SLOTS = [
		'banner',
		'header',
		'body',
		'content',
		'button',
		'link',
		'footer',
		'toolbar',
	];

	public const array LIGHT_DEFAULTS = [
		'banner' => '#c0c0c0',
		'header' => '#c0c0c0',
		'body' => '#1a1a1a',
		'content' => '#ffffff',
		'button' => '#c0c0c0',
		'link' => '#0645ad',
		'footer' => '#c0c0c0',
		'toolbar' => '#000000',
	];

	public const array DARK_DEFAULTS = [
		'banner' => '#1b1b1f',
		'header' => '#26262c',
		'body' => '#0f0f12',
		'content' => '#17171b',
		'button' => '#3a6ea5',
		'link' => '#6aa9ff',
		'footer' => '#1b1b1f',
		'toolbar' => '#000000',
	];

	private const array BACKGROUND_SIZES = [ 'auto', 'contain', 'cover' ];
	private const array CONTENT_WIDTHS = [ 'default', 'large', 'full' ];
	private const array BUTTON_STYLES = [ 'default', 'slim', 'pill', 'text' ];
	private const array TOOLBAR_STYLES = [ 'floating', 'bar', 'rail' ];

	private const int MAX_LIST_ITEMS = 100;
	private const int MAX_RAIL_MODULES = 50;
	private const string RAIL_MODULE_ID_PATTERN = '/^[A-Za-z0-9._-]{1,100}$/';
	private const string CUSTOM_RAIL_ID_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
	private const string MESSAGE_KEY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/';

	private readonly array $data;

	public function __construct(
		array $data,
		private readonly int $revisionId,
	) {
		$this->data = self::normalize( $data );
	}

	public static function newFromJson( string $json, int $revisionId ): self {
		$decoded = json_decode( $json, true );
		return new self( is_array( $decoded ) ? $decoded : [], $revisionId );
	}

	/**
	 * Builds settings from the flat values the old ThemeDesigner stored.
	 */
	public static function newFromLegacy( array $values ): self {
		$map = [
			'banner' => 'CosmosBannerBackgroundColor',
			'header' => 'CosmosWikiHeaderBackgroundColor',
			'body' => 'CosmosMainBackgroundColor',
			'content' => 'CosmosContentBackgroundColor',
			'button' => 'CosmosButtonBackgroundColor',
			'link' => 'CosmosLinkColor',
			'footer' => 'CosmosFooterBackgroundColor',
			'toolbar' => 'CosmosToolbarBackgroundColor',
		];

		$light = [];
		foreach ( $map as $slot => $key ) {
			if ( isset( $values[$key] ) && is_string( $values[$key] ) && $values[$key] !== '' ) {
				$light[$slot] = $values[$key];
			}
		}

		$opacity = (int)( $values['CosmosContentOpacityLevel'] ?? 0 );
		return new self( [
			'palettes' => [ self::MODE_LIGHT => $light ],
			'images' => [
				'wordmark' => $values['CosmosWordmark'] ?? '',
				'header' => $values['CosmosWikiHeaderBackgroundImage'] ?? '',
				'background' => $values['CosmosBackgroundImage'] ?? '',
				'backgroundSize' => $values['CosmosBackgroundImageSize'] ?? '',
				'backgroundRepeat' => $values['CosmosBackgroundImageRepeat'] ?? null,
				'backgroundFixed' => $values['CosmosBackgroundImageFixed'] ?? null,
			],
			'layout' => [
				'contentOpacity' => $opacity > 0 ? $opacity : null,
			],
		], 0 );
	}

	/**
	 * @return array<string, mixed>
	 * @suppress PhanPluginMoreSpecificActualReturnType
	 */
	public static function getDefaults(): array {
		return [
			'version' => self::SCHEMA_VERSION,
			'colorMode' => [
				'default' => self::MODE_LIGHT,
				'toggle' => false,
			],
			'palettes' => [
				self::MODE_LIGHT => [],
				self::MODE_DARK => [],
			],
			'presets' => [
				self::MODE_LIGHT => '',
				self::MODE_DARK => '',
			],
			'images' => [
				'wordmark' => '',
				'header' => '',
				'background' => '',
				'backgroundSize' => '',
				'backgroundRepeat' => null,
				'backgroundFixed' => null,
			],
			'layout' => [
				'contentWidth' => '',
				'contentOpacity' => null,
				'font' => [ 'type' => '', 'value' => '' ],
				'headerButtonOpacity' => 20,
				'backdropBlur' => 0,
				'headerBorder' => true,
				'buttonStyle' => 'default',
			],
			'toolbar' => [
				'enabled' => true,
				'style' => 'floating',
				'hiddenItems' => [],
			],
			'footer' => [
				'opacity' => 90,
				'showIcons' => true,
				'hiddenLinks' => [],
			],
			'extensions' => [
				'portableInfoboxEuropa' => null,
			],
			'rail' => [
				'enabled' => true,
				'hideForAnons' => false,
				'disabledNamespaces' => null,
				'disabledPages' => null,
				'modules' => [],
				'customModules' => [],
			],
		];
	}

	private static function normalize( array $raw ): array {
		$data = self::getDefaults();
		$mode = $raw['colorMode'] ?? [];
		if ( is_array( $mode ) ) {
			if ( in_array( $mode['default'] ?? null, self::DEFAULT_MODES, true ) ) {
				$data['colorMode']['default'] = $mode['default'];
			}

			$data['colorMode']['toggle'] = self::toBool( $mode['toggle'] ?? null, false );
		}

		$palettes = $raw['palettes'] ?? [];
		$presets = $raw['presets'] ?? [];
		foreach ( self::MODES as $name ) {
			$palette = is_array( $palettes ) ? ( $palettes[$name] ?? [] ) : [];
			if ( is_array( $palette ) ) {
				foreach ( self::COLOR_SLOTS as $slot ) {
					$color = isset( $palette[$slot] ) && is_string( $palette[$slot] ) ?
						self::normalizeColor( $palette[$slot] ) :
						null;

					if ( $color !== null ) {
						$data['palettes'][$name][$slot] = $color;
					}
				}
			}

			$preset = is_array( $presets ) ? ( $presets[$name] ?? '' ) : '';
			if ( is_string( $preset ) && preg_match( '/^[a-z0-9-]{1,40}$/', $preset ) ) {
				$data['presets'][$name] = $preset;
			}
		}

		$images = $raw['images'] ?? [];
		if ( is_array( $images ) ) {
			foreach ( [ 'wordmark', 'header', 'background' ] as $key ) {
				$data['images'][$key] = self::normalizeImage( $images[$key] ?? '' );
			}

			if ( in_array( $images['backgroundSize'] ?? null, self::BACKGROUND_SIZES, true ) ) {
				$data['images']['backgroundSize'] = $images['backgroundSize'];
			}

			foreach ( [ 'backgroundRepeat', 'backgroundFixed' ] as $key ) {
				$data['images'][$key] = self::toBool( $images[$key] ?? null, null );
			}
		}

		$layout = $raw['layout'] ?? [];
		if ( is_array( $layout ) ) {
			if ( in_array( $layout['contentWidth'] ?? null, self::CONTENT_WIDTHS, true ) ) {
				$data['layout']['contentWidth'] = $layout['contentWidth'];
			}

			$data['layout']['contentOpacity'] = self::toPercent( $layout['contentOpacity'] ?? null, null );
			$data['layout']['font'] = ThemeFont::newFromArray( $layout['font'] ?? null )->toArray();
			$data['layout']['headerButtonOpacity'] = self::toPercent( $layout['headerButtonOpacity'] ?? null, 20 );
			$data['layout']['headerBorder'] = self::toBool( $layout['headerBorder'] ?? null, true );
			$data['layout']['backdropBlur'] = is_numeric( $layout['backdropBlur'] ?? null ) ?
				max( 0, min( 40, (int)round( (float)$layout['backdropBlur'] ) ) ) :
				0;

			if ( in_array( $layout['buttonStyle'] ?? null, self::BUTTON_STYLES, true ) ) {
				$data['layout']['buttonStyle'] = $layout['buttonStyle'];
			} elseif ( self::toBool( $layout['slimButtons'] ?? null, false ) ) {
				$data['layout']['buttonStyle'] = 'slim';
			}
		}

		$toolbar = $raw['toolbar'] ?? [];
		if ( is_array( $toolbar ) ) {
			$data['toolbar']['enabled'] = self::toBool( $toolbar['enabled'] ?? null, true );
			if ( in_array( $toolbar['style'] ?? null, self::TOOLBAR_STYLES, true ) ) {
				$data['toolbar']['style'] = $toolbar['style'];
			}

			$data['toolbar']['hiddenItems'] = self::normalizeIdList( $toolbar['hiddenItems'] ?? [] );
		}

		$footer = $raw['footer'] ?? [];
		if ( is_array( $footer ) ) {
			$data['footer']['opacity'] = self::toPercent( $footer['opacity'] ?? null, 90 );
			$data['footer']['showIcons'] = self::toBool( $footer['showIcons'] ?? null, true );
			$data['footer']['hiddenLinks'] = self::normalizeIdList( $footer['hiddenLinks'] ?? [] );
		}

		$extensions = $raw['extensions'] ?? [];
		if ( is_array( $extensions ) ) {
			$data['extensions']['portableInfoboxEuropa'] = self::toBool(
				$extensions['portableInfoboxEuropa'] ?? null,
				null
			);
		}

		$rail = $raw['rail'] ?? [];
		if ( is_array( $rail ) ) {
			$data['rail']['enabled'] = self::toBool( $rail['enabled'] ?? null, true );
			$data['rail']['hideForAnons'] = self::toBool( $rail['hideForAnons'] ?? null, false );
			$data['rail']['disabledNamespaces'] = self::normalizeNamespaceList( $rail['disabledNamespaces'] ?? null );
			$data['rail']['disabledPages'] = self::normalizePageList( $rail['disabledPages'] ?? null );
			$data['rail']['modules'] = self::normalizeRailModules( $rail );
			$data['rail']['customModules'] = self::normalizeCustomRailModules( $rail['customModules'] ?? null );
		}

		return $data;
	}

	/**
	 * @return ?list<int> Null when the value is not a list, so the wiki default applies
	 */
	private static function normalizeNamespaceList( mixed $value ): ?array {
		if ( !is_array( $value ) ) {
			return null;
		}

		$namespaces = [];
		foreach ( array_slice( $value, 0, self::MAX_LIST_ITEMS ) as $namespace ) {
			if ( is_numeric( $namespace ) && (int)$namespace >= -2 && (int)$namespace < 100000 ) {
				$namespaces[(int)$namespace] = (int)$namespace;
			}
		}

		return array_values( $namespaces );
	}

	/**
	 * @return ?list<non-empty-string> Null when the value is not a list, so the wiki default applies
	 */
	private static function normalizePageList( mixed $value ): ?array {
		if ( !is_array( $value ) ) {
			return null;
		}

		$pages = [];
		foreach ( array_slice( $value, 0, self::MAX_LIST_ITEMS ) as $page ) {
			$page = is_string( $page ) ? trim( $page ) : '';
			if ( $page !== '' && strlen( $page ) <= 255 && !preg_match( '/[\x00-\x1f<>{}\[\]|]/', $page ) ) {
				$pages[$page] = $page;
			}
		}

		return array_values( $pages );
	}

	/**
	 * Keeps only the rules a module really sets, keyed by module id.
	 *
	 * @return array<string, array<string, mixed>>
	 * @suppress PhanPluginMoreSpecificActualReturnType
	 */
	private static function normalizeRailModules( array $rail ): array {
		$raw = is_array( $rail['modules'] ?? null ) ? $rail['modules'] : [];

		// Themes saved before modules had rules of their own only chose a mode for recent changes
		$legacy = $rail['recentChanges'] ?? null;
		if ( !isset( $raw['recentchanges'] ) ) {
			if ( $legacy === 'off' ) {
				$raw['recentchanges'] = [ 'enabled' => false ];
			} elseif ( self::toRailType( $legacy ) !== null ) {
				$raw['recentchanges'] = [ 'enabled' => true, 'type' => $legacy ];
			}
		}

		$modules = [];
		foreach ( array_slice( $raw, 0, self::MAX_RAIL_MODULES, true ) as $id => $rules ) {
			$rules = is_array( $rules ) ? self::normalizeRailRules( $rules ) : [];
			if ( $rules !== [] && preg_match( self::RAIL_MODULE_ID_PATTERN, (string)$id ) ) {
				$modules[(string)$id] = $rules;
			}
		}

		return $modules;
	}

	private static function toRailType( mixed $value ): ?RailModuleType {
		return is_string( $value ) ? RailModuleType::tryFrom( $value ) : null;
	}

	/**
	 * @return array<string, mixed>
	 * @suppress PhanPluginMoreSpecificActualReturnType
	 */
	private static function normalizeRailRules( array $raw ): array {
		$rules = [];
		$enabled = self::toBool( $raw['enabled'] ?? null, null );
		if ( $enabled !== null ) {
			$rules['enabled'] = $enabled;
		}

		$type = self::toRailType( $raw['type'] ?? null );
		if ( $type !== null ) {
			$rules['type'] = $type->value;
		}

		$namespaces = self::normalizeNamespaceList( $raw['disabledNamespaces'] ?? null );
		if ( $namespaces !== null ) {
			$rules['disabledNamespaces'] = $namespaces;
		}

		$pages = self::normalizePageList( $raw['disabledPages'] ?? null );
		if ( $pages !== null ) {
			$rules['disabledPages'] = $pages;
		}

		return $rules;
	}

	/**
	 * Interface modules the wiki added from the designer. Each shows one interface message.
	 *
	 * @return array<int, array{id: string, message: string, header: string}>
	 */
	private static function normalizeCustomRailModules( mixed $value ): array {
		if ( !is_array( $value ) ) {
			return [];
		}

		$modules = [];
		foreach ( array_slice( $value, 0, self::MAX_RAIL_MODULES ) as $module ) {
			$id = is_array( $module ) && is_string( $module['id'] ?? null ) ? $module['id'] : '';
			$message = is_array( $module ) && is_string( $module['message'] ?? null ) ? trim( $module['message'] ) : '';
			if ( strlen( $id ) > 60 || !preg_match( self::CUSTOM_RAIL_ID_PATTERN, $id ) ||
				!preg_match( self::MESSAGE_KEY_PATTERN, $message )
			) {
				continue;
			}

			$header = is_string( $module['header'] ?? null ) ?
				mb_substr( trim( (string)preg_replace( '/[\x00-\x1f<>]/', '', $module['header'] ) ), 0, 80 ) :
				'';

			$modules[$id] = [
				'id' => $id,
				'message' => $message,
				'header' => $header,
			];
		}

		return array_values( $modules );
	}

	/**
	 * Canonical form is #rrggbb, rgba(r, g, b, a) or transparent.
	 *
	 * @return string|null Null if the value is not a usable color
	 */
	public static function normalizeColor( string $color ): ?string {
		$parsed = LessUtil::parseColor( $color );
		if ( $parsed === null ) {
			return null;
		}

		if ( $parsed['a'] <= 0 ) {
			return 'transparent';
		}

		if ( $parsed['a'] >= 1 ) {
			return sprintf( '#%02x%02x%02x', $parsed['r'], $parsed['g'], $parsed['b'] );
		}

		return sprintf(
			'rgba(%d, %d, %d, %s)',
			$parsed['r'],
			$parsed['g'],
			$parsed['b'],
			rtrim( rtrim( number_format( $parsed['a'], 2, '.', '' ), '0' ), '.' )
		);
	}

	/**
	 * Accepts a file name or a http(s) URL.
	 */
	private static function normalizeImage( mixed $value ): string {
		if ( !is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		if ( $value === '' || strlen( $value ) > 1000 || preg_match( '/[\x00-\x1f<>"\'`{}\\\\]/', $value ) ) {
			return '';
		}

		if ( preg_match( '/^[a-z][a-z0-9+.-]*:/i', $value ) && !preg_match( '/^(https?:|file:)/i', $value ) ) {
			return '';
		}

		if ( str_starts_with( strtolower( $value ), 'file:' ) ) {
			$value = trim( substr( $value, 5 ) );
		}

		if ( preg_match( '/^file:/i', $value ) ) {
			return '';
		}

		return $value;
	}

	/** @return string[] */
	private static function normalizeIdList( mixed $value ): array {
		if ( !is_array( $value ) ) {
			return [];
		}

		$ids = [];
		foreach ( array_slice( $value, 0, self::MAX_LIST_ITEMS ) as $id ) {
			if ( is_string( $id ) && preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $id ) ) {
				$ids[$id] = $id;
			}
		}

		return array_values( $ids );
	}

	private static function toBool( mixed $value, ?bool $default ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( $value === 1 || $value === '1' || $value === 'true' ) {
			return true;
		}

		if ( $value === 0 || $value === '0' || $value === 'false' ) {
			return false;
		}

		return $default;
	}

	private static function toPercent( mixed $value, ?int $default ): ?int {
		if ( !is_numeric( $value ) ) {
			return $default;
		}

		return max( 0, min( 100, (int)round( (float)$value ) ) );
	}

	public function toArray(): array {
		return $this->data;
	}

	public function toJson(): string {
		return json_encode( $this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
	}

	public function getRevisionId(): int {
		return $this->revisionId;
	}

	/**
	 * Auto renders the light colors and lets the browser switch to the dark ones.
	 */
	public function getDefaultMode(): string {
		$mode = $this->data['colorMode']['default'];
		return $mode === self::MODE_AUTO ? self::MODE_LIGHT : $mode;
	}

	public function isAutoMode(): bool {
		return $this->data['colorMode']['default'] === self::MODE_AUTO;
	}

	public function isToggleEnabled(): bool {
		return (bool)$this->data['colorMode']['toggle'];
	}

	/** @return array Slot to color, only slots that were set */
	public function getPalette( string $mode ): array {
		return $this->data['palettes'][$mode] ?? [];
	}

	public function getColor( string $mode, string $slot ): ?string {
		return $this->data['palettes'][$mode][$slot] ?? null;
	}

	public function getSection( string $name ): array {
		return $this->data[$name] ?? [];
	}

	public function withDefaults( ThemeDefaults $defaults ): self {
		$data = $this->data;
		$default = $this->getDefaultMode();

		foreach ( self::MODES as $mode ) {
			foreach ( self::COLOR_SLOTS as $slot ) {
				$data['palettes'][$mode][$slot] ??= self::normalizeColor(
					$defaults->getColor( $slot, $mode, $default )
				) ?? self::LIGHT_DEFAULTS[$slot];
			}
		}

		$images = &$data['images'];
		$fallbacks = [
			'wordmark' => $defaults->wordmark,
			'header' => $defaults->headerImage,
			'background' => $defaults->backgroundImage,
		];
		foreach ( $fallbacks as $key => $value ) {
			$images[$key] = $images[$key] !== '' ? $images[$key] : self::normalizeImage( $value );
		}

		$images['backgroundSize'] = $images['backgroundSize'] !== '' ? $images['backgroundSize'] :
			( in_array( $defaults->backgroundSize, self::BACKGROUND_SIZES, true )
				? $defaults->backgroundSize
				: 'cover' );
		$images['backgroundRepeat'] ??= $defaults->backgroundRepeat;
		$images['backgroundFixed'] ??= $defaults->backgroundFixed;
		unset( $images );

		$layout = &$data['layout'];
		$layout['contentWidth'] = $layout['contentWidth'] !== '' ? $layout['contentWidth'] :
			( in_array( $defaults->contentWidth, self::CONTENT_WIDTHS, true ) ? $defaults->contentWidth : 'default' );
		$layout['contentOpacity'] ??= max( 0, min( 100, $defaults->contentOpacity ) );
		unset( $layout );

		$data['extensions']['portableInfoboxEuropa'] ??= $defaults->europa;
		$data['rail']['disabledNamespaces'] ??= $defaults->railDisabledNamespaces;
		$data['rail']['disabledPages'] ??= $defaults->railDisabledPages;

		$rail = $data['rail'];
		$modules = $rail['modules'] ?? [];
		$modules[RailModule::ID_RECENT_CHANGES] = array_merge(
			[ 'enabled' => $defaults->recentChanges, 'type' => $defaults->recentChangesType->value ],
			$modules[RailModule::ID_RECENT_CHANGES] ?? []
		);

		$custom = [];
		foreach ( $rail['customModules'] ?? [] as $module ) {
			$custom[$module['id']] = $module;
		}

		foreach ( $defaults->interfaceModules as $message => $type ) {
			$id = self::slugify( $message );
			if ( $id === '' || strlen( $id ) > 60 || !preg_match( self::MESSAGE_KEY_PATTERN, $message ) ) {
				continue;
			}

			$custom[$id] ??= [ 'id' => $id, 'message' => $message, 'header' => '' ];
			$old = $modules["interface-$message"] ?? [];
			unset( $modules["interface-$message"] );
			$modules["custom-$id"] = array_merge( [ 'type' => $type->value ], $old, $modules["custom-$id"] ?? [] );
		}

		$data['rail']['customModules'] = array_values( $custom );
		$data['rail']['modules'] = $modules;

		return new self( $data, $this->revisionId );
	}

	/** Lowercase words joined by dashes, as the ids of custom modules are written */
	private static function slugify( string $text ): string {
		return substr( trim( (string)preg_replace( '/[^a-z0-9]+/', '-', strtolower( $text ) ), '-' ), 0, 60 );
	}

	/** @return bool Whether nothing has been customized */
	public function isDefault(): bool {
		return $this->data === self::getDefaults();
	}
}
