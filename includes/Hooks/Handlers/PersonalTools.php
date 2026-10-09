<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\Skin\Cosmos\SkinCosmos;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;

class PersonalTools implements SkinTemplateNavigation__UniversalHook {

	private const array LABELS = [
		'anontalk' => 'cosmos-personaltools-anontalk',
		'mytalk' => 'cosmos-personaltools-usertalk',
		'userpage' => 'cosmos-personaltools-userpage',
	];

	private const array MENUS = [
		'user-interface-preferences',
		'user-page',
		'user-menu',
	];

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$sktemplate instanceof SkinCosmos ) {
			return;
		}

		foreach ( self::MENUS as $menu ) {
			foreach ( self::LABELS as $key => $message ) {
				if ( isset( $links[$menu][$key] ) ) {
					$links[$menu][$key]['text'] = $sktemplate->msg( $message )->text();
				}
			}
		}

		unset( $links['user-menu']['adminlinks'] );
	}
}
