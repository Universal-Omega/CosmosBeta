<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\Skin\Cosmos\Maintenance\ImportLegacyTheme;

class Installer implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Tested by updating or installing MediaWiki.
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		$dir = __DIR__ . '/../../../sql';
		$type = $updater->getDB()->getType();

		$updater->addExtensionTable( 'cosmos_theme', "$dir/$type/tables-generated.sql" );
		$updater->addPostDatabaseUpdateMaintenance( ImportLegacyTheme::class );
	}
}
