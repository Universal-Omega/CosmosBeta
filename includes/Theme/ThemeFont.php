<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

use function array_slice;
use function array_unique;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function pathinfo;
use function preg_match;
use function preg_replace;
use function str_replace;
use function strlen;
use function strtolower;
use function trim;
use const PATHINFO_EXTENSION;

final readonly class ThemeFont {

	private const string PRESET = 'preset';
	private const string FILE = 'file';
	private const string CUSTOM = 'custom';

	public const string FILE_FAMILY = 'Cosmos Uploaded Font';

	public const array EXTENSIONS = [ 'woff2', 'woff', 'ttf', 'otf' ];

	public const array PRESETS = [
		'system' => "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
		'arial' => "Arial, 'Helvetica Neue', Helvetica, sans-serif",
		'verdana' => 'Verdana, Geneva, sans-serif',
		'georgia' => "Georgia, 'Times New Roman', serif",
		'times' => "'Times New Roman', Times, serif",
		'monospace' => "ui-monospace, 'Cascadia Code', Menlo, Consolas, monospace",
	];

	private const array GENERIC_FAMILIES = [
		'cursive',
		'fantasy',
		'monospace',
		'sans-serif',
		'serif',
		'system-ui',
		'ui-monospace',
		'ui-rounded',
		'ui-sans-serif',
		'ui-serif',
	];

	private const int MAX_FAMILIES = 8;

	public function __construct(
		public string $type,
		public string $value,
	) {
	}

	/**
	 * Anything that is not a usable font is the default font.
	 */
	public static function newFromArray( mixed $data ): self {
		if ( !is_array( $data ) || !is_string( $data['value'] ?? null ) ) {
			return new self( type: '', value: '' );
		}

		$value = match ( $data['type'] ?? null ) {
			self::PRESET => isset( self::PRESETS[$data['value']] ) ? $data['value'] : '',
			self::FILE => self::normalizeFile( $data['value'] ),
			self::CUSTOM => self::normalizeFamilies( $data['value'] ),
			default => '',
		};

		return $value === '' ? new self( type: '', value: '' ) : new self( $data['type'], $value );
	}

	/** @return array{type: string, value: string} */
	public function toArray(): array {
		return [ 'type' => $this->type, 'value' => $this->value ];
	}

	public function getFileName(): string {
		return $this->type === self::FILE ? $this->value : '';
	}

	/**
	 * @param string $default The font family when the theme does not set one
	 * @param bool $fileFound Whether the uploaded font file exists
	 */
	public function getFamily( string $default, bool $fileFound ): string {
		return match ( $this->type ) {
			self::PRESET => self::PRESETS[$this->value],
			self::CUSTOM => $this->value,
			self::FILE => $fileFound ? "'" . self::FILE_FAMILY . "', $default" : $default,
			default => $default,
		};
	}

	/**
	 * @return string A file name with a font extension, or an empty string
	 */
	private static function normalizeFile( string $name ): string {
		$name = trim( (string)preg_replace( '/^(?:file|image):/i', '', trim( $name ) ) );
		$name = str_replace( '_', ' ', $name );

		if ( strlen( $name ) > 240 || preg_match( '/[\x00-\x1f<>"\'`{}\[\]|#\/\\\\]/', $name ) ) {
			return '';
		}

		return in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), self::EXTENSIONS, true ) ? $name : '';
	}

	/**
	 * @return string Font names that are safe in CSS, quoted unless they are generic families
	 */
	private static function normalizeFamilies( string $value ): string {
		$families = [];
		foreach ( array_slice( explode( ',', $value ), 0, self::MAX_FAMILIES ) as $name ) {
			$name = trim( $name, " \t\n\r\"'" );
			if ( in_array( strtolower( $name ), self::GENERIC_FAMILIES, true ) ) {
				$families[] = strtolower( $name );
			} elseif ( preg_match( '/^[\p{L}\p{N} _-]{1,64}$/u', $name ) ) {
				$families[] = "'" . preg_replace( '/ {2,}/', ' ', $name ) . "'";
			}
		}

		return implode( ', ', array_unique( $families ) );
	}
}
