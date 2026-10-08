<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Skin\Cosmos\CosmosNavigation;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;

class ResetThemeCache extends Maintenance {

	private CosmosNavigation $navigation;
	private ThemeStore $themeStore;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Clear the cached theme and navigation.' );
		$this->requireExtension( 'CosmosBeta' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->navigation = $services->get( 'Cosmos.Navigation' );
		$this->themeStore = $services->get( 'Cosmos.ThemeStore' );
	}

	public function execute(): void {
		$this->initServices();

		$this->navigation->purge();
		$this->themeStore->purgeCache();

		$this->output( "Theme and navigation caches cleared.\n" );
	}
}

// @codeCoverageIgnoreStart
return ResetThemeCache::class;
// @codeCoverageIgnoreEnd
