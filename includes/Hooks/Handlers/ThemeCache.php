<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Output\Hook\OutputPageCheckLastModifiedHook;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeStore;

class ThemeCache implements OutputPageCheckLastModifiedHook {

	public function __construct(
		private readonly ThemeStore $store,
	) {
	}

	/** @inheritDoc */
	public function onOutputPageCheckLastModified( &$modifiedTimes, $out ): void {
		if ( !$out->getSkin() instanceof SkinCosmosBeta ) {
			return;
		}

		$timestamp = $this->store->getCurrentTimestamp();
		if ( $timestamp !== '' ) {
			$modifiedTimes['cosmosbeta-theme'] = $timestamp;
		}
	}
}
