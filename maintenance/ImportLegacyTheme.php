<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Maintenance\LoggedUpdateOutcome;
use MediaWiki\Skin\Cosmos\Theme\ThemeSettings;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;
use MediaWiki\User\User;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use const MW_INSTALL_PATH;

class ImportLegacyTheme extends LoggedUpdateMaintenance {

	private ThemeStore $themeStore;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Import a theme saved by the previous theme designer into the database.' );
		$this->requireExtension( 'CosmosBeta' );
	}

	protected function getUpdateKey(): string {
		return self::class;
	}

	private function initServices(): void {
		$this->themeStore = $this->getServiceContainer()->get( 'Cosmos.ThemeStore' );
	}

	protected function doDBUpdates(): LoggedUpdateOutcome {
		$this->initServices();
		if ( $this->themeStore->getCurrent()->getRevisionId() !== 0 ) {
			$this->output( "A theme is already stored, nothing to import.\n" );
			return LoggedUpdateOutcome::COMPLETE;
		}

		$config = $this->getConfig();
		$cacheDirectory = $config->get( MainConfigNames::CacheDirectory ) ?: MW_INSTALL_PATH . '/cache';
		$file = "$cacheDirectory/cosmos-themedesigner/{$config->get( MainConfigNames::DBname )}.json";

		if ( !is_file( $file ) ) {
			$this->output( "No theme file found.\n" );
			return LoggedUpdateOutcome::COMPLETE;
		}

		$decoded = json_decode( (string)file_get_contents( $file ), true );
		$values = is_array( $decoded ) ? ( $decoded['values'] ?? null ) : null;

		if ( !is_array( $values ) ) {
			$this->output( "The theme file could not be read.\n" );
			return LoggedUpdateOutcome::COMPLETE;
		}

		$this->themeStore->save(
			ThemeSettings::newFromLegacy( $values ),
			User::newSystemUser( 'Cosmos ThemeDesigner', [ 'steal' => true ] ),
			'Imported from a theme file'
		);

		$this->output( "Imported the theme file.\n" );
		return LoggedUpdateOutcome::COMPLETE;
	}
}

// @codeCoverageIgnoreStart
return ImportLegacyTheme::class;
// @codeCoverageIgnoreEnd
