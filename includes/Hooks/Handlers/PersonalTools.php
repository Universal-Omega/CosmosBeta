<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;

class PersonalTools implements SkinTemplateNavigation__UniversalHook {

	private const array LABELS = [
		'userpage' => 'cosmosbeta-personaltools-userpage',
		'mytalk' => 'cosmosbeta-personaltools-usertalk',
		'anontalk' => 'cosmosbeta-personaltools-anontalk',
	];

	private const array MENUS = [
		'user-interface-preferences',
		'user-page',
		'user-menu',
	];

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$sktemplate instanceof SkinCosmosBeta ) {
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
