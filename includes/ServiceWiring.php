<?php

declare( strict_types = 1 );

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Skin\Cosmos\AdminDashboard\AdminDashboardStats;
use MediaWiki\Skin\Cosmos\AdminDashboard\AdvancedSectionBuilder;
use MediaWiki\Skin\Cosmos\AdminDashboard\DashboardControlRegistry;
use MediaWiki\Skin\Cosmos\BackgroundLookup;
use MediaWiki\Skin\Cosmos\CosmosConfig;
use MediaWiki\Skin\Cosmos\CosmosNavigation;
use MediaWiki\Skin\Cosmos\CosmosRailBuilder;
use MediaWiki\Skin\Cosmos\Hooks\CosmosHookRunner;
use MediaWiki\Skin\Cosmos\LessUtil;
use MediaWiki\Skin\Cosmos\Theme\AltModules;
use MediaWiki\Skin\Cosmos\Theme\ColorModeResolver;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;
use MediaWiki\Skin\Cosmos\WordmarkLookup;

// PHPUnit does not understand coverage for this file.
// It is covered though, see ServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'CosmosBetaAdminDashboardControls' => static function ( MediaWikiServices $services ): DashboardControlRegistry {
		return new DashboardControlRegistry(
			$services->get( 'ExtensionRegistry' ),
			$services->getPermissionManager(),
			new ServiceOptions(
				DashboardControlRegistry::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->getSpecialPageFactory(),
			$services->getTitleFactory()
		);
	},

	'CosmosBetaAdminDashboardStats' => static function ( MediaWikiServices $services ): AdminDashboardStats {
		return new AdminDashboardStats(
			$services->getMainWANObjectCache(),
			$services->getConnectionProvider()
		);
	},

	'CosmosBetaAdvancedSectionBuilder' => static function ( MediaWikiServices $services ): AdvancedSectionBuilder {
		return new AdvancedSectionBuilder( $services->getSpecialPageFactory() );
	},

	'CosmosBetaAltModules' => static function ( MediaWikiServices $services ): AltModules {
		return new AltModules( $services->get( 'ExtensionRegistry' ) );
	},

	'CosmosBetaBackgroundLookup' => static function ( MediaWikiServices $services ): BackgroundLookup {
		$config = $services->get( 'CosmosBetaConfig' );
		return new BackgroundLookup(
			$services->getRepoGroup(),
			$services->getTitleFactory(),
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
			$services->get( 'CosmosBetaColorModeResolver' ),
			$services->get( 'CosmosBetaThemeStore' ),
			new ServiceOptions(
				CosmosConfig::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' ),
				$services->getMainConfig()
			)
		);
	},

	'CosmosBetaHookRunner' => static function ( MediaWikiServices $services ): CosmosHookRunner {
		return new CosmosHookRunner( $services->getHookContainer() );
	},

	'CosmosBetaLessUtil' => static function ( MediaWikiServices $services ): LessUtil {
		return new LessUtil( $services->get( 'CosmosBetaConfig' ) );
	},

	'CosmosBetaNavigation' => static function ( MediaWikiServices $services ): CosmosNavigation {
		return new CosmosNavigation(
			$services->getMainWANObjectCache(),
			$services->getContentLanguage(),
			$services->getUrlUtils(),
			$services->getTitleFactory(),
			$services->get( 'ExtensionRegistry' ),
			new ServiceOptions(
				CosmosNavigation::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' )
			)
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
			LoggerFactory::getInstance( 'Cosmos' )
		);
	},

	'CosmosBetaWordmarkLookup' => static function ( MediaWikiServices $services ): WordmarkLookup {
		return new WordmarkLookup(
			$services->getRepoGroup(),
			$services->getTitleFactory(),
			$services->get( 'CosmosBetaConfig' )->getWordmark()
		);
	},
];

// @codeCoverageIgnoreEnd
