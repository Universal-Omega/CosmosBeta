<?php

declare( strict_types = 1 );

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Skins\CosmosBeta\CosmosBackgroundLookup;
use MediaWiki\Skins\CosmosBeta\CosmosConfig;
use MediaWiki\Skins\CosmosBeta\CosmosNavigation;
use MediaWiki\Skins\CosmosBeta\CosmosRailBuilder;
use MediaWiki\Skins\CosmosBeta\CosmosWordmarkLookup;
use MediaWiki\Skins\CosmosBeta\Hooks\CosmosHookRunner;
use MediaWiki\Skins\CosmosBeta\Theme\AltModules;
use MediaWiki\Skins\CosmosBeta\Theme\ColorModeResolver;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeStore;

// @codeCoverageIgnoreStart

return [
	'CosmosBetaAltModules' => static function ( MediaWikiServices $services ): AltModules {
		return new AltModules( $services->get( 'ExtensionRegistry' ) );
	},

	'CosmosBetaBackgroundLookup' => static function ( MediaWikiServices $services ): CosmosBackgroundLookup {
		$config = $services->get( 'CosmosBetaConfig' );

		return new CosmosBackgroundLookup(
			$services->getTitleFactory(),
			$services->getRepoGroup(),
			$config->getBackgroundImage(),
			$config->getWikiHeaderBackgroundImage()
		);
	},

	'CosmosBetaColorModeResolver' => static function ( MediaWikiServices $services ): ColorModeResolver {
		return new ColorModeResolver(
			$services->get( 'CosmosBetaThemeStore' ),
			$services->getUserOptionsLookup()
		);
	},

	'CosmosBetaConfig' => static function ( MediaWikiServices $services ): CosmosConfig {
		return new CosmosConfig(
			new ServiceOptions(
				CosmosConfig::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' ),
				$services->getMainConfig()
			),
			$services->get( 'CosmosBetaThemeStore' ),
			$services->get( 'CosmosBetaColorModeResolver' )
		);
	},

	'CosmosBetaHookRunner' => static function ( MediaWikiServices $services ): CosmosHookRunner {
		return new CosmosHookRunner( $services->getHookContainer() );
	},

	'CosmosBetaNavigation' => static function ( MediaWikiServices $services ): CosmosNavigation {
		return new CosmosNavigation(
			$services->getMainWANObjectCache(),
			$services->getContentLanguage(),
			$services->getUrlUtils(),
			$services->getTitleFactory(),
			$services->get( 'ExtensionRegistry' )
		);
	},

	'CosmosBetaOptions' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'CosmosBeta' );
	},

	'CosmosBetaRailBuilder' => static function ( MediaWikiServices $services ): CosmosRailBuilder {
		return new CosmosRailBuilder(
			$services->get( 'CosmosBetaHookRunner' ),
			$services->getConnectionProvider(),
			$services->getLinkRenderer(),
			RequestContext::getMain(),
			new ServiceOptions(
				CosmosRailBuilder::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' ),
				$services->getMainConfig()
			),
			$services->getSpecialPageFactory(),
			$services->getUserFactory(),
			$services->getMainWANObjectCache(),
			$services->get( 'CosmosBetaConfig' ),
			$services->get( 'CosmosBetaTemplateParser' )
		);
	},

	'CosmosBetaTemplateParser' => static function (): TemplateParser {
		$parser = new TemplateParser( __DIR__ . '/../templates' );
		$parser->enableRecursivePartials( true );

		return $parser;
	},

	'CosmosBetaThemeStore' => static function ( MediaWikiServices $services ): ThemeStore {
		return new ThemeStore(
			$services->getConnectionProvider(),
			$services->getMainWANObjectCache(),
			$services->getActorNormalization(),
			LoggerFactory::getInstance( 'CosmosBeta' )
		);
	},

	'CosmosBetaWordmarkLookup' => static function ( MediaWikiServices $services ): CosmosWordmarkLookup {
		return new CosmosWordmarkLookup(
			$services->getTitleFactory(),
			$services->getRepoGroup(),
			$services->get( 'CosmosBetaConfig' )->getWordmark()
		);
	},
];

// @codeCoverageIgnoreEnd
