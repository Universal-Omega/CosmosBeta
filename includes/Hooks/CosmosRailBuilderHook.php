<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks;

use MediaWiki\Skin\Skin;

/**
 * Hook interface for adding or changing the modules shown in the CosmosBeta rail.
 *
 * This hook is called after the recent changes, interface and custom modules have been built,
 * but before the sidebar and page tools modules are added and the rail is rendered.
 * Each module can be hidden, made sticky or limited to pages from the theme designer, by its key.
 */
interface CosmosRailBuilderHook {

	/**
	 * This hook is triggered while the rail modules are being built.
	 *
	 * @param array<string, array<string, mixed>> &$modules
	 *   The rail modules, keyed by a unique module name. Each module can have:
	 *   [
	 *     'class' => 'Extra CSS class or an array of classes',
	 *     'type' => 'normal or sticky',
	 *     'header' => 'Message key or text of the module heading',
	 *     'body' => 'HTML of the module (must be properly escaped)',
	 *   ]
	 *   This array is passed by reference and can be modified.
	 * @param Skin $skin The current skin object rendering the page.
	 *
	 * @return void This hook must not abort, it must return no value.
	 */
	public function onCosmosRailBuilder( array &$modules, Skin $skin ): void;
}
