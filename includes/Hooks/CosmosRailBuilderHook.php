<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks;

use MediaWiki\Skin\Skin;

interface CosmosRailBuilderHook {

	public function onCosmosRailBuilder( array &$modules, Skin $skin ): void;
}
