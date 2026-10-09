<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use function in_array;

class ColorModeResolver {

	public const string OPTION = 'cosmos-colormode';

	public function __construct(
		private readonly ThemeStore $store,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/**
	 * Anonymous and temporary users always get the wiki default so cached pages stay identical for everyone.
	 */
	public function getRenderMode( User $user ): string {
		$theme = $this->store->getCurrent();
		$default = $theme->getDefaultMode();
		if ( !$theme->isToggleEnabled() || !$user->isNamed() ) {
			return $default;
		}

		$preference = $this->userOptionsLookup->getOption( $user, self::OPTION, '' );
		return in_array( $preference, ThemeSettings::MODES, true ) ? $preference : $default;
	}

	/**
	 * Whether a registered user picked light or dark themselves.
	 */
	public function hasPreference( User $user ): bool {
		if ( !$this->store->getCurrent()->isToggleEnabled() || !$user->isNamed() ) {
			return false;
		}

		return in_array( $this->userOptionsLookup->getOption( $user, self::OPTION, '' ), ThemeSettings::MODES, true );
	}

	public static function getOpposite( string $mode ): string {
		return $mode === ThemeSettings::MODE_DARK ? ThemeSettings::MODE_LIGHT : ThemeSettings::MODE_DARK;
	}
}
