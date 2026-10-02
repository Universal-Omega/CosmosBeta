<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Html\Html;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Skins\CosmosBeta\CosmosConfig;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;
use MediaWiki\Skins\CosmosBeta\Theme\AltModules;
use MediaWiki\Skins\CosmosBeta\Theme\ColorModeResolver;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeSettings;
use MediaWiki\SpecialPage\SpecialPage;
use function array_merge;
use function array_unique;

class ColorMode implements
	BeforePageDisplayHook,
	GetPreferencesHook,
	ResourceLoaderRegisterModulesHook,
	SkinTemplateNavigation__UniversalHook
{

	private const string ITEM_KEY = 'cosmosbeta-colormode';

	public function __construct(
		private readonly CosmosConfig $config,
		private readonly AltModules $altModules,
	) {
	}

	/** @inheritDoc */
	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		$rl->register( $this->altModules->getDefinitions() );
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ): void {
		if ( !$this->config->isColorModeToggleEnabled() ) {
			return;
		}

		$preferences[ColorModeResolver::OPTION] = [
			'type' => 'radio',
			'section' => 'rendering/skin/skin-prefs',
			'label-message' => 'cosmosbeta-pref-colormode',
			'options-messages' => [
				'cosmosbeta-pref-colormode-default' => '',
				'cosmosbeta-pref-colormode-light' => ThemeSettings::MODE_LIGHT,
				'cosmosbeta-pref-colormode-dark' => ThemeSettings::MODE_DARK,
			],
			'hide-if' => [ '!==', 'skin', 'cosmosbeta' ],
		];
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if (
			!$sktemplate instanceof SkinCosmosBeta ||
			!isset( $links['user-menu'] ) ||
			!$this->config->isColorModeToggleEnabled()
		) {
			return;
		}

		$mode = $this->config->getRenderMode();
		$item = [
			'text' => $sktemplate->msg( "cosmosbeta-colormode-switch-$mode" )->text(),
			'href' => $sktemplate->getUser()->isRegistered() ?
				SpecialPage::getTitleFor( 'Preferences' )->getLocalURL() . '#mw-prefsection-rendering' :
				'#',
			'class' => 'skin-cosmos-colormode-toggle',
		];

		$menu = [];
		foreach ( $links['user-menu'] as $key => $value ) {
			if ( $key === 'logout' ) {
				$menu[self::ITEM_KEY] = $item;
			}

			$menu[$key] = $value;
		}

		$menu[self::ITEM_KEY] ??= $item;
		$links['user-menu'] = $menu;
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$skin instanceof SkinCosmosBeta ) {
			return;
		}

		$mode = $this->config->getRenderMode();
		$out->addHtmlClasses( "skin-cosmos-colormode-$mode" );

		if ( !$this->config->isColorModeToggleEnabled() ) {
			return;
		}

		$registered = $out->getUser()->isRegistered();
		$altModules = [];

		if ( !$registered ) {
			$styles = $out->getModuleStyles();
			foreach ( $skin->getDefaultModules()['styles'] as $group ) {
				$styles = array_merge( $styles, $group );
			}

			foreach ( array_unique( $styles ) as $name ) {
				$twin = $this->altModules->getTwinName( $name );

				if ( $twin !== null ) {
					$altModules[] = $twin;
				}
			}

			$script = '(function(){try{var m=localStorage.getItem("skin-cosmos-colormode");' .
				'var d=document.documentElement;' .
				'if((m==="light"||m==="dark")&&d.className.indexOf("skin-cosmos-colormode-"+m)===-1){' .
				'd.className+=" skin-cosmos-colormode-pending";' .
				'setTimeout(function(){d.className=d.className.replace(" skin-cosmos-colormode-pending","")},2500)}' .
				'}catch(e){}}());';

			$out->addHeadItem(
				'skin-cosmos-colormode',
				Html::inlineStyle( 'html.skin-cosmos-colormode-pending body{opacity:0}' ) .
				Html::inlineScript( $script, $out->getCSP()->getNonce() )
			);
		}

		$out->addJsConfigVars( 'wgCosmosBetaColorMode', [
			'render' => $mode,
			'default' => $this->config->getDefaultMode(),
			'registered' => $registered,
			'altModules' => $altModules,
			'option' => ColorModeResolver::OPTION,
		] );
	}
}
