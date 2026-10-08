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
use MediaWiki\Skin\Cosmos\CosmosNavigation;
use MediaWiki\Skin\Cosmos\Hooks\HookRunner;
use MediaWiki\Skin\Cosmos\Legacy\LegacyConverter;
use MediaWiki\Skin\Cosmos\LessUtil;
use MediaWiki\Skin\Cosmos\Rail\RailBuilder;
use MediaWiki\Skin\Cosmos\Theme\AltModules;
use MediaWiki\Skin\Cosmos\Theme\ColorModeResolver;
use MediaWiki\Skin\Cosmos\Theme\ConfigDefaults;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\Skin\Cosmos\Theme\ThemeDefaults;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;
use MediaWiki\Skin\Cosmos\WordmarkLookup;

// PHPUnit does not understand coverage for this file.
// It is covered though, see ServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'CosmosBetaAdminDashboardControls' => static function ( MediaWikiServices $services ): DashboardControlRegistry {
		return new DashboardControlRegistry(
			$services->getExtensionRegistry(),
			$services->getPermissionManager(),
			$services->getSpecialPageFactory(),
			$services->getTitleFactory(),
			new ServiceOptions(
				DashboardControlRegistry::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' )
			)
		);
	},

	'CosmosBetaAdminDashboardStats' => static function ( MediaWikiServices $services ): AdminDashboardStats {
		return new AdminDashboardStats(
			$services->getConnectionProvider(),
			$services->getMainWANObjectCache()
		);
	},

	'CosmosBetaAdvancedSectionBuilder' => static function ( MediaWikiServices $services ): AdvancedSectionBuilder {
		return new AdvancedSectionBuilder( $services->getSpecialPageFactory() );
	},

	'CosmosBetaAltModules' => static function ( MediaWikiServices $services ): AltModules {
		return new AltModules( $services->getExtensionRegistry() );
	},

	'CosmosBetaBackgroundLookup' => static function ( MediaWikiServices $services ): BackgroundLookup {
		$theme = $services->get( 'CosmosBetaEffectiveTheme' );
		return new BackgroundLookup(
			$services->getRepoGroup(),
			$services->getTitleFactory(),
			$theme->getBackgroundImage(),
			$theme->getWikiHeaderBackgroundImage()
		);
	},

	'CosmosBetaColorModeResolver' => static function ( MediaWikiServices $services ): ColorModeResolver {
		return new ColorModeResolver(
			$services->get( 'CosmosBetaThemeStore' ),
			$services->getUserOptionsLookup()
		);
	},

	'CosmosBetaConfigDefaults' => static function ( MediaWikiServices $services ): ConfigDefaults {
		return new ConfigDefaults(
			new ServiceOptions(
				ConfigDefaults::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' )
			)
		);
	},

	'CosmosBetaEffectiveTheme' => static function ( MediaWikiServices $services ): EffectiveTheme {
		return new EffectiveTheme(
			$services->get( 'CosmosBetaColorModeResolver' ),
			$services->get( 'CosmosBetaThemeDefaults' ),
			$services->get( 'CosmosBetaThemeStore' ),
			new ServiceOptions(
				EffectiveTheme::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' )
			)
		);
	},

	'CosmosBetaHookRunner' => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner( $services->getHookContainer() );
	},

	'CosmosBetaLegacyConverter' => static function (): LegacyConverter {
		return new LegacyConverter();
	},

	'CosmosBetaLessUtil' => static function ( MediaWikiServices $services ): LessUtil {
		return new LessUtil( $services->get( 'CosmosBetaEffectiveTheme' ) );
	},

	'CosmosBetaNavigation' => static function ( MediaWikiServices $services ): CosmosNavigation {
		return new CosmosNavigation(
			$services->getExtensionRegistry(),
			$services->getContentLanguageCode(),
			$services->getTitleFactory(),
			$services->getUrlUtils(),
			$services->getMainWANObjectCache(),
			new ServiceOptions(
				CosmosNavigation::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' )
			)
		);
	},

	'CosmosBetaOptions' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'CosmosBeta' );
	},

	'CosmosBetaRailBuilder' => static function ( MediaWikiServices $services ): RailBuilder {
		return new RailBuilder(
			$services->get( 'CosmosBetaEffectiveTheme' ),
			$services->get( 'CosmosBetaHookRunner' ),
			$services->get( 'CosmosBetaTemplateParser' ),
			$services->getConnectionProvider(),
			$services->getLinkRenderer(),
			$services->getSpecialPageFactory(),
			$services->getUserFactory(),
			$services->getMainWANObjectCache(),
			RequestContext::getMain(),
			new ServiceOptions(
				RailBuilder::CONSTRUCTOR_OPTIONS,
				$services->get( 'CosmosBetaOptions' )
			),
		);
	},

	'CosmosBetaTemplateParser' => static function (): TemplateParser {
		$parser = new TemplateParser( __DIR__ . '/../templates' );
		$parser->enableRecursivePartials( true );
		return $parser;
	},

	'CosmosBetaThemeDefaults' => static function ( MediaWikiServices $services ): ThemeDefaults {
		$configDefaults = $services->get( 'CosmosBetaConfigDefaults' );
		return $configDefaults->isThemeDesignerOnly() ? new ThemeDefaults() : $configDefaults->getDefaults();
	},

	'CosmosBetaThemeStore' => static function ( MediaWikiServices $services ): ThemeStore {
		return new ThemeStore(
			$services->getActorNormalization(),
			$services->getConnectionProvider(),
			$services->getMainWANObjectCache(),
			LoggerFactory::getInstance( 'Cosmos' )
		);
	},

	'CosmosBetaWordmarkLookup' => static function ( MediaWikiServices $services ): WordmarkLookup {
		return new WordmarkLookup(
			$services->getRepoGroup(),
			$services->getTitleFactory(),
			$services->get( 'CosmosBetaEffectiveTheme' )->getWordmark()
		);
	},
];

// @codeCoverageIgnoreEnd
