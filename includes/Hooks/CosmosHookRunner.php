<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Skin\Skin;

class CosmosHookRunner implements CosmosRailBuilderHook {

	public function __construct(
		private readonly HookContainer $container,
	) {
	}

	/** @inheritDoc */
	public function onCosmosRailBuilder( array &$modules, Skin $skin ): void {
		$this->container->run(
			'CosmosRailBuilder',
			[ &$modules, $skin ],
			[ 'abortable' => false ]
		);
	}
}
