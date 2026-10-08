<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use AdminLinksHook;
use ALItem;
use ALRow;
use ALSection;
use ALTree;
use function wfMessage;

class AdminLinks implements AdminLinksHook {

	/** @inheritDoc */
	public function onAdminLinks( ALTree &$adminLinksTree ): void {
		$section = new ALSection( wfMessage( 'skinname-cosmos' )->text() );
		$row = new ALRow( 'cosmos' );

		$links = [
			'cosmos-navigation' => 'cosmos-adminlinks-edit-navigation',
			'cosmos-tagline' => 'cosmos-adminlinks-edit-tagline',
			'Cosmos.css' => 'cosmos-adminlinks-edit-css',
			'Cosmos.js' => 'cosmos-adminlinks-edit-js',
		];

		foreach ( $links as $page => $message ) {
			$row->addItem( ALItem::newFromEditLink( $page, wfMessage( $message )->text() ) );
		}

		$section->addRow( $row );
		$adminLinksTree->addSection( $section, wfMessage( 'adminlinks_users' )->text() );
	}
}
