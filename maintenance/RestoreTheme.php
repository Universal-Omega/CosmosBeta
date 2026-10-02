<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeStore;
use MediaWiki\User\User;

class RestoreTheme extends Maintenance {

	private ThemeStore $themeStore;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Publish an earlier theme revision again as the newest one.' );
		$this->addOption( 'revision', 'The revision id to restore.', false, true );
		$this->addOption( 'list', 'List recent revisions instead of restoring.' );
		$this->requireExtension( 'CosmosBeta' );
	}

	private function initServices(): void {
		$this->themeStore = $this->getServiceContainer()->get( 'CosmosBetaThemeStore' );
	}

	public function execute(): void {
		$this->initServices();

		if ( $this->hasOption( 'list' ) ) {
			foreach ( $this->themeStore->getHistory() as $row ) {
				$this->output( "{$row['id']}\t{$row['timestamp']}\t{$row['user']}\t{$row['comment']}\n" );
			}

			return;
		}

		$revision = (int)$this->getOption( 'revision', 0 );

		if ( $revision <= 0 ) {
			$this->fatalError( 'Pass --revision with a revision id, or use --list.' );
		}

		$user = User::newSystemUser( 'CosmosBeta ThemeDesigner', [ 'steal' => true ] );

		if ( $this->themeStore->restore( $revision, $user, "Restored revision $revision" ) === null ) {
			$this->fatalError( "Revision $revision does not exist." );
		}

		$this->output( "Restored revision $revision.\n" );
	}
}

// @codeCoverageIgnoreStart
return RestoreTheme::class;
// @codeCoverageIgnoreEnd
