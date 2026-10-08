<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

use MediaWiki\Skin\Cosmos\Rail\RailModuleType;
use const NS_MEDIAWIKI;
use const NS_MEDIAWIKI_TALK;
use const NS_SPECIAL;

/**
 * What a theme setting is when the theme does not set it.
 */
final readonly class ThemeDefaults {

	/**
	 * @param array<string, string>|null $colors Colors of the default mode by slot, null for the built in ones
	 * @param int[] $railDisabledNamespaces
	 * @param string[] $railDisabledPages
	 * @param array<string, RailModuleType> $interfaceModules Interface messages by how each is shown
	 */
	public function __construct(
		public ?array $colors = null,
		public string $wordmark = '',
		public string $headerImage = '',
		public string $backgroundImage = '',
		public string $backgroundSize = 'cover',
		public bool $backgroundRepeat = false,
		public bool $backgroundFixed = true,
		public string $contentWidth = 'default',
		public int $contentOpacity = 100,
		public bool $europa = true,
		public array $railDisabledNamespaces = [ NS_SPECIAL, NS_MEDIAWIKI, NS_MEDIAWIKI_TALK ],
		public array $railDisabledPages = [ 'mainpage' ],
		public bool $recentChanges = false,
		public RailModuleType $recentChangesType = RailModuleType::Normal,
		public array $interfaceModules = [],
	) {
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
