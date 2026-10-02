<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Theme;

use MediaWiki\Skins\CosmosBeta\LessUtil;
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
use function min;
use function number_format;
use function preg_match;
use function round;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;
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
	public const array MODES = [ self::MODE_LIGHT, self::MODE_DARK ];

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

	public const array BACKGROUND_SIZES = [ 'auto', 'contain', 'cover' ];
	public const array CONTENT_WIDTHS = [ 'default', 'large', 'full' ];
	public const array TOOLBAR_STYLES = [ 'floating', 'bar', 'rail' ];
	public const array RAIL_RECENT_CHANGES = [ 'off', 'normal', 'sticky' ];

	private const int MAX_LIST_ITEMS = 100;

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
				'headerButtonOpacity' => 20,
				'backdropBlur' => 0,
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
			'rail' => [
				'enabled' => true,
				'hideForAnons' => false,
				'recentChanges' => '',
				'disabledNamespaces' => null,
				'disabledPages' => null,
			],
		];
	}

	public static function normalize( array $raw ): array {
		$data = self::getDefaults();

		$mode = $raw['colorMode'] ?? [];
		if ( is_array( $mode ) ) {
			if ( in_array( $mode['default'] ?? null, self::MODES, true ) ) {
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
			$data['layout']['headerButtonOpacity'] = self::toPercent( $layout['headerButtonOpacity'] ?? null, 20 );
			$data['layout']['backdropBlur'] = is_numeric( $layout['backdropBlur'] ?? null ) ?
				max( 0, min( 40, (int)round( (float)$layout['backdropBlur'] ) ) ) :
				0;
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

		$rail = $raw['rail'] ?? [];
		if ( is_array( $rail ) ) {
			$data['rail']['enabled'] = self::toBool( $rail['enabled'] ?? null, true );
			$data['rail']['hideForAnons'] = self::toBool( $rail['hideForAnons'] ?? null, false );

			if ( in_array( $rail['recentChanges'] ?? null, self::RAIL_RECENT_CHANGES, true ) ) {
				$data['rail']['recentChanges'] = $rail['recentChanges'];
			}

			if ( isset( $rail['disabledNamespaces'] ) && is_array( $rail['disabledNamespaces'] ) ) {
				$namespaces = [];
				foreach ( array_slice( $rail['disabledNamespaces'], 0, self::MAX_LIST_ITEMS ) as $ns ) {
					if ( is_numeric( $ns ) && (int)$ns >= -2 && (int)$ns < 100000 ) {
						$namespaces[(int)$ns] = (int)$ns;
					}
				}
				$data['rail']['disabledNamespaces'] = array_values( $namespaces );
			}

			if ( isset( $rail['disabledPages'] ) && is_array( $rail['disabledPages'] ) ) {
				$pages = [];
				foreach ( array_slice( $rail['disabledPages'], 0, self::MAX_LIST_ITEMS ) as $page ) {
					$page = is_string( $page ) ? trim( $page ) : '';
					if ( $page !== '' && strlen( $page ) <= 255 && !preg_match( '/[\x00-\x1f<>{}\[\]|]/', $page ) ) {
						$pages[$page] = $page;
					}
				}
				$data['rail']['disabledPages'] = array_values( $pages );
			}
		}

		return $data;
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
	public static function normalizeImage( $value ): string {
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
	private static function normalizeIdList( $value ): array {
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

	private static function toBool( $value, ?bool $default ): ?bool {
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

	private static function toPercent( $value, ?int $default ): ?int {
		if ( !is_numeric( $value ) ) {
			return $default;
		}

		return max( 0, min( 100, (int)round( (float)$value ) ) );
	}

	public function toArray(): array {
		return $this->data;
	}

	public function toJson(): string {
		return json_encode( $this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	public function getRevisionId(): int {
		return $this->revisionId;
	}

	public function getDefaultMode(): string {
		return $this->data['colorMode']['default'];
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

	/** @return bool Whether nothing has been customized */
	public function isDefault(): bool {
		return $this->data === self::getDefaults();
	}
}
