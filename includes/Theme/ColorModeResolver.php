<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Theme;

use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use function in_array;

class ColorModeResolver {

	public const string OPTION = 'cosmosbeta-colormode';

	public function __construct(
		private readonly ThemeStore $store,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/**
	 * Anonymous users always get the wiki default so cached pages stay identical for everyone.
	 */
	public function getRenderMode( ?UserIdentity $user ): string {
		$theme = $this->store->getCurrent();
		$default = $theme->getDefaultMode();

		if ( !$theme->isToggleEnabled() || $user === null || !$user->isRegistered() ) {
			return $default;
		}

		$preference = $this->userOptionsLookup->getOption( $user, self::OPTION, '' );

		return in_array( $preference, ThemeSettings::MODES, true ) ? $preference : $default;
	}

	public static function getOpposite( string $mode ): string {
		return $mode === ThemeSettings::MODE_DARK ? ThemeSettings::MODE_LIGHT : ThemeSettings::MODE_DARK;
	}
}
