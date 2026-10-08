<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks;

use MediaWiki\Skin\Cosmos\Rail\RailModuleList;
use MediaWiki\Skin\Skin;

/**
 * Hook interface for adding or changing the modules shown in the CosmosBeta rail.
 *
 * This hook is called after the recent changes, interface and custom modules have been built,
 * but before the sidebar and page tools modules are added and the rail is rendered.
 * Each module can be hidden, made sticky or limited to pages from the theme designer, by its id.
 */
interface CosmosRailBuilderHook {

	/**
	 * This hook is triggered while the rail modules are being built.
	 *
	 * @param RailModuleList $modules The modules so far. Add to it, replace modules by adding one with
	 *   the same id, or remove them. Build modules with the named constructors of RailModule.
	 * @param Skin $skin The current skin object rendering the page.
	 *
	 * @return void This hook must not abort, it must return no value.
	 */
	public function onCosmosRailBuilder( RailModuleList $modules, Skin $skin ): void;
}
