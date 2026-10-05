<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Skins\CosmosBeta\ProfileBioLookup;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;
use MediaWiki\User\User;

class UserProfileV2 {

	public function __construct(
		private readonly ProfileBioLookup $bioLookup,
	) {
	}

	/**
	 * Hook provided by the UserProfileV2 extension. Runs below the masthead.
	 *
	 * @param User $user Owner of the profile
	 * @param string|null &$html
	 */
	public function onUserProfileV2OnProfileAfterMasthead( User $user, &$html ): void {
		if ( !RequestContext::getMain()->getSkin() instanceof SkinCosmosBeta ) {
			return;
		}

		$bio = $this->bioLookup->getBio( $user );
		if ( $bio !== null && $bio !== '' ) {
			$html .= Html::element( 'p', [ 'class' => 'skin-cosmos-profile-bio bio' ], $bio );
		}
	}
}
