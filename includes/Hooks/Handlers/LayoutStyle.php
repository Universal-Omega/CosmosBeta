<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Skins\CosmosBeta\CosmosConfig;
use MediaWiki\Skins\CosmosBeta\Theme\LayoutStyleResolver;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeSettings;

class LayoutStyle implements GetPreferencesHook {

	public function __construct(
		private readonly CosmosConfig $config,
	) {
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ): void {
		if ( !$this->config->isLayoutStyleChoiceEnabled() ) {
			return;
		}

		$preferences[LayoutStyleResolver::OPTION] = [
			'type' => 'radio',
			'section' => 'rendering/skin/skin-prefs',
			'label-message' => 'cosmosbeta-pref-style',
			'options-messages' => [
				'cosmosbeta-pref-style-default' => '',
				'cosmosbeta-pref-style-cosmos' => ThemeSettings::STYLE_COSMOS,
				'cosmosbeta-pref-style-fandomdesktop' => ThemeSettings::STYLE_FANDOMDESKTOP,
			],
			'hide-if' => [ '!==', 'skin', 'cosmosbeta' ],
		];
	}
}
