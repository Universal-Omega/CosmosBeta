<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Theme;

use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;
use function in_array;

final readonly class LayoutStyleResolver {

	public const string OPTION = 'cosmosbeta-style';

	public function __construct(
		private ThemeStore $store,
		private UserOptionsLookup $userOptionsLookup,
	) {
	}

	/**
	 * Anonymous users always get the wiki default so cached pages stay identical for everyone.
	 */
	public function getStyle( ?UserIdentity $user ): string {
		$layout = $this->store->getCurrent()->getSection( 'layout' );

		if ( !$layout['userStyle'] || $user === null || !$user->isRegistered() ) {
			return $layout['style'];
		}

		$preference = $this->userOptionsLookup->getOption( $user, self::OPTION, '' );

		return in_array( $preference, ThemeSettings::LAYOUT_STYLES, true ) ? $preference : $layout['style'];
	}

	public function isChoiceEnabled(): bool {
		return (bool)$this->store->getCurrent()->getSection( 'layout' )['userStyle'];
	}
}
