<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\Html\Html;
use MediaWiki\MainConfigNames;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Skin\Cosmos\CosmosConfig;
use MediaWiki\Skin\Cosmos\SkinCosmos;
use MediaWiki\Skin\Cosmos\Theme\AltModules;
use MediaWiki\Skin\Cosmos\Theme\ColorModeResolver;
use MediaWiki\Skin\Cosmos\Theme\ThemeSettings;
use function array_merge;
use function array_unique;
use function wfAppendQuery;

class ColorMode implements
	BeforePageDisplayHook,
	GetPreferencesHook,
	ResourceLoaderRegisterModulesHook
{

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
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$skin instanceof SkinCosmos ) {
			return;
		}

		$mode = $this->config->getRenderMode();
		$out->addHtmlClasses( "skin-cosmos-colormode--$mode" );

		$auto = $this->config->isAutoColorMode() && !$this->config->hasColorModePreference();

		// Core and extensions style their dark mode through these classes, so they follow the skin
		$out->addHtmlClasses( $auto ? 'skin-theme-clientpref-os' : ( $mode === ThemeSettings::MODE_DARK ?
			'skin-theme-clientpref-night' :
			'skin-theme-clientpref-day' ) );
		$toggle = $this->config->isColorModeToggleEnabled();
		$bodyColors = $this->getBodyColors();

		$out->addHeadItem( 'skin-cosmos-canvas', Html::inlineStyle(
			$this->getCanvasRule( $bodyColors[$mode], $mode ) .
			( $auto ? '@media (prefers-color-scheme:dark){' .
				$this->getCanvasRule( $bodyColors[ThemeSettings::MODE_DARK], ThemeSettings::MODE_DARK ) . '}' : '' )
		) );

		if ( !$toggle && !$auto ) {
			return;
		}

		$registered = $out->getUser()->isNamed();
		$altModules = [];

		if ( $auto || !$registered ) {
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
		}

		$headItems = '';
		$script = '';

		if ( $auto && $altModules !== [] ) {
			// The dark styles only apply when the browser asks for a dark color scheme
			$url = wfAppendQuery( $out->getConfig()->get( MainConfigNames::LoadScript ), [
				'lang' => $out->getLanguage()->getCode(),
				'modules' => ResourceLoader::makePackedModulesString( $altModules ),
				'only' => 'styles',
				'skin' => $skin->getSkinName(),
			] );
			$headItems .= Html::element( 'link', [
				'id' => 'skin-cosmos-auto-dark',
				'rel' => 'stylesheet',
				'media' => '(prefers-color-scheme: dark)',
				'href' => $url,
			] );
			$out->addHtmlClasses( 'skin-cosmos-colormode--auto' );
		}

		if ( !$registered && $toggle ) {
			$rendered = $mode === ThemeSettings::MODE_DARK ? 'night' : 'day';
			$other = ColorModeResolver::getOpposite( $mode );
			$script = '(function(){var d=document.documentElement,' .
				'm=d.className.match(/skin-theme-clientpref-(day|night|os)/);' .
				'if(!m||m[1]==="os"){return}' .
				'var l=document.getElementById("skin-cosmos-auto-dark");' .
				'if(l){l.parentNode.removeChild(l)}' .
				'if(m[1]!=="' . $rendered . '"){' .
				'd.style.backgroundColor=' . json_encode( $bodyColors[$other] ) . ';' .
				'd.style.colorScheme="' . ( $other === ThemeSettings::MODE_DARK ? 'dark' : 'light' ) . '";' .
				'd.className+=" skin-cosmos-colormode--pending";' .
				'setTimeout(function(){d.className=d.className.replace(" skin-cosmos-colormode--pending","")},2500)}' .
				'}());';
			$headItems .= Html::inlineStyle( 'html.skin-cosmos-colormode--pending body{opacity:0}' ) .
				Html::inlineScript( $script, $out->getCSP()->getNonce() );
		}

		if ( $headItems !== '' ) {
			$out->addHeadItem( 'skin-cosmos-colormode', $headItems );
		}

		$out->addJsConfigVars( 'wgCosmosColorMode', [
			'render' => $mode,
			'default' => $this->config->getDefaultMode(),
			'auto' => $auto,
			'autoDefault' => $this->config->isAutoColorMode(),
			'toggle' => $toggle,
			'registered' => $registered,
			'altModules' => $altModules,
			'option' => ColorModeResolver::OPTION,
			'bodyColors' => $bodyColors,
		] );
	}

	/** @return array<string,string> Page background of each mode, safe to print into CSS */
	private function getBodyColors(): array {
		$colors = [];
		foreach ( ThemeSettings::MODES as $name ) {
			$colors[$name] = ThemeSettings::normalizeColor( $this->config->getColor( 'body', $name ) ) ?? 'transparent';
		}

		return $colors;
	}

	private function getCanvasRule( string $color, string $mode ): string {
		return "html{background-color:$color;" . ( $mode === ThemeSettings::MODE_DARK ? 'color-scheme:dark;' : '' ) . '}';
	}
}
