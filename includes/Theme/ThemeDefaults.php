<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

use MediaWiki\Skin\Cosmos\Rail\RailModuleType;
use const NS_MEDIAWIKI;
use const NS_MEDIAWIKI_TALK;
use const NS_SPECIAL;

final readonly class ThemeDefaults {

	public const string FONT_FAMILY = "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";

	/**
	 * @param array<string, string>|null $colors Colors of the default mode by slot, null for the built in ones
	 * @param string $wordmark
	 * @param string $headerImage
	 * @param string $backgroundImage
	 * @param string $backgroundSize
	 * @param bool $backgroundRepeat
	 * @param bool $backgroundFixed
	 * @param string $contentWidth
	 * @param int $contentOpacity
	 * @param string $fontFamily
	 * @param bool $europa
	 * @param int[] $railDisabledNamespaces
	 * @param string[] $railDisabledPages
	 * @param bool $recentChanges
	 * @param RailModuleType $recentChangesType
	 * @param array<string, RailModuleType> $interfaceModules Interface messages by how each is shown
	 */
	public function __construct(
		public ?array $colors,
		public string $wordmark,
		public string $headerImage,
		public string $backgroundImage,
		public string $backgroundSize,
		public bool $backgroundRepeat,
		public bool $backgroundFixed,
		public string $contentWidth,
		public int $contentOpacity,
		public string $fontFamily,
		public bool $europa,
		public array $railDisabledNamespaces,
		public array $railDisabledPages,
		public bool $recentChanges,
		public RailModuleType $recentChangesType,
		public array $interfaceModules,
	) {
	}

	/**
	 * The defaults of a wiki that has not configured anything.
	 */
	public static function newBuiltIn(): self {
		return new self(
			colors: null,
			wordmark: '',
			headerImage: '',
			backgroundImage: '',
			backgroundSize: 'cover',
			backgroundRepeat: false,
			backgroundFixed: true,
			contentWidth: 'default',
			contentOpacity: 100,
			fontFamily: self::FONT_FAMILY,
			europa: true,
			railDisabledNamespaces: [ NS_SPECIAL, NS_MEDIAWIKI, NS_MEDIAWIKI_TALK ],
			railDisabledPages: [ 'mainpage' ],
			recentChanges: false,
			recentChangesType: RailModuleType::Normal,
			interfaceModules: [],
		);
	}

	public function getColor( string $slot, string $mode, string $defaultMode ): string {
		if ( $this->colors === null || $mode !== $defaultMode ) {
			return $mode === ThemeSettings::MODE_DARK ?
				ThemeSettings::DARK_DEFAULTS[$slot] :
				ThemeSettings::LIGHT_DEFAULTS[$slot];
		}

		return $this->colors[$slot];
	}
}
