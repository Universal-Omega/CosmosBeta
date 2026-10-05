<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use AdminLinksHook;
use ALItem;
use ALRow;
use ALSection;
use ALTree;
use function wfMessage;

class AdminLinks implements AdminLinksHook {

	/** @inheritDoc */
	public function onAdminLinks( ALTree &$adminLinksTree ): void {
		$section = new ALSection( wfMessage( 'skinname-cosmosbeta' )->text() );
		$row = new ALRow( 'cosmosbeta' );

		$links = [
			'Cosmosbeta-navigation' => 'cosmosbeta-adminlinks-edit-navigation',
			'Cosmosbeta-tagline' => 'cosmosbeta-adminlinks-edit-tagline',
			'Cosmosbeta.css' => 'cosmosbeta-adminlinks-edit-css',
			'Cosmosbeta.js' => 'cosmosbeta-adminlinks-edit-js',
		];

		foreach ( $links as $page => $message ) {
			$row->addItem( ALItem::newFromEditLink( $page, wfMessage( $message )->text() ) );
		}

		$section->addRow( $row );
		$adminLinksTree->addSection( $section, wfMessage( 'adminlinks_users' )->text() );
	}
}
