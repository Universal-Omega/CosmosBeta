<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Skin\Cosmos\CosmosNavigation;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;

class ResetThemeCache extends Maintenance {

	private ThemeStore $themeStore;

	private CosmosNavigation $navigation;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Clear the cached theme and navigation.' );
		$this->requireExtension( 'CosmosBeta' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->themeStore = $services->get( 'CosmosBetaThemeStore' );
		$this->navigation = $services->get( 'CosmosBetaNavigation' );
	}

	public function execute(): void {
		$this->initServices();

		$this->themeStore->purgeCache();
		$this->navigation->purge();

		$this->output( "Theme and navigation caches cleared.\n" );
	}
}

// @codeCoverageIgnoreStart
return ResetThemeCache::class;
// @codeCoverageIgnoreEnd
