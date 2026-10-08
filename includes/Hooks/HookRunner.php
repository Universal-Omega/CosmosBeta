<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks;

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Skin\Cosmos\Rail\RailModuleList;
use MediaWiki\Skin\Skin;

class HookRunner implements CosmosRailBuilderHook {

	public function __construct(
		private readonly HookContainer $container,
	) {
	}

	/** @inheritDoc */
	public function onCosmosRailBuilder( RailModuleList $modules, Skin $skin ): void {
		$this->container->run(
			'CosmosRailBuilder',
			[ $modules, $skin ],
			[ 'abortable' => false ]
		);
	}
}
