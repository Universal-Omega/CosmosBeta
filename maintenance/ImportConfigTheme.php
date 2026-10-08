<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Skin\Cosmos\Theme\ConfigDefaults;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;
use MediaWiki\User\User;

class ImportConfigTheme extends Maintenance {

	private ConfigDefaults $configDefaults;
	private ThemeStore $themeStore;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Save the theme settings that come from the wiki configuration into the theme itself. ' .
			'Settings already published are kept.'
		);

		$this->requireExtension( 'CosmosBeta' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->configDefaults = $services->get( 'CosmosBetaConfigDefaults' );
		$this->themeStore = $services->get( 'CosmosBetaThemeStore' );
	}

	public function execute(): void {
		$this->initServices();

		$current = $this->themeStore->getCurrent();
		$id = $this->themeStore->save(
			$current->withDefaults( $this->configDefaults->getDefaults() ),
			User::newSystemUser( 'Cosmos ThemeDesigner', [ 'steal' => true ] ),
			'Imported from the wiki configuration'
		);

		$this->output( $id === $current->getRevisionId() ?
			"The published theme already holds these settings (revision $id).\n" :
			"Published the configuration as theme revision $id.\n"
		);

		if ( !$this->configDefaults->isThemeDesignerOnly() ) {
			$this->output( "Set \$wgCosmosBetaThemeDesignerOnly to true to stop using the configuration for the theme.\n" );
		}
	}
}

// @codeCoverageIgnoreStart
return ImportConfigTheme::class;
// @codeCoverageIgnoreEnd
