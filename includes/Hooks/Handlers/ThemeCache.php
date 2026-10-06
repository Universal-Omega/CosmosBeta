<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Output\Hook\OutputPageCheckLastModifiedHook;
use MediaWiki\Skins\CosmosBeta\SkinCosmos;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeStore;

class ThemeCache implements OutputPageCheckLastModifiedHook {

	public function __construct(
		private readonly ThemeStore $store,
	) {
	}

	/** @inheritDoc */
	public function onOutputPageCheckLastModified( &$modifiedTimes, $out ): void {
		if ( !$out->getSkin() instanceof SkinCosmos ) {
			return;
		}

		$timestamp = $this->store->getCurrentTimestamp();
		if ( $timestamp !== '' ) {
			$modifiedTimes['cosmos-theme'] = $timestamp;
		}
	}
}
