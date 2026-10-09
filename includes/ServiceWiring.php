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
use MediaWiki\Skin\Cosmos\CosmosNavigation;
use MediaWiki\Skin\Cosmos\Hooks\HookRunner;
use MediaWiki\Skin\Cosmos\Legacy\LegacyConverter;
use MediaWiki\Skin\Cosmos\LessUtil;
use MediaWiki\Skin\Cosmos\Lookup\BackgroundLookup;
use MediaWiki\Skin\Cosmos\Lookup\FontLookup;
use MediaWiki\Skin\Cosmos\Lookup\WordmarkLookup;
use MediaWiki\Skin\Cosmos\Rail\RailBuilder;
use MediaWiki\Skin\Cosmos\Theme\AltModules;
use MediaWiki\Skin\Cosmos\Theme\ColorModeResolver;
use MediaWiki\Skin\Cosmos\Theme\ConfigDefaults;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\Skin\Cosmos\Theme\ThemeDefaults;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;
use Wikimedia\Assert\Assert;

// PHPUnit does not understand coverage for this file.
// It is covered though, see ServiceWiringTest.
// @codeCoverageIgnoreStart

/** @phpcs-require-sorted-array */
return [
	'Cosmos.AdminDashboardControls' => static function ( MediaWikiServices $services ): DashboardControlRegistry {
		return new DashboardControlRegistry(
			$services->getExtensionRegistry(),
			$services->getPermissionManager(),
			$services->getSpecialPageFactory(),
			$services->getTitleFactory(),
			new ServiceOptions(
				DashboardControlRegistry::CONSTRUCTOR_OPTIONS,
				$services->get( 'Cosmos.Config' )
			)
		);
	},

	'Cosmos.AdminDashboardStats' => static function ( MediaWikiServices $services ): AdminDashboardStats {
		return new AdminDashboardStats(
			$services->getConnectionProvider(),
			$services->getMainWANObjectCache()
		);
	},

	'Cosmos.AdvancedSectionBuilder' => static function ( MediaWikiServices $services ): AdvancedSectionBuilder {
		return new AdvancedSectionBuilder( $services->getSpecialPageFactory() );
	},

	'Cosmos.AltModules' => static function ( MediaWikiServices $services ): AltModules {
		return new AltModules( $services->getExtensionRegistry() );
	},

	'Cosmos.BackgroundLookup' => static function ( MediaWikiServices $services ): BackgroundLookup {
		$theme = $services->get( 'Cosmos.EffectiveTheme' );
		Assert::postcondition( $theme instanceof EffectiveTheme, 'Cosmos.EffectiveTheme is an EffectiveTheme' );
		return new BackgroundLookup(
			$services->getRepoGroup(),
			$services->getTitleFactory(),
			$theme->getBackgroundImage(),
			$theme->getWikiHeaderBackgroundImage()
		);
	},

	'Cosmos.ColorModeResolver' => static function ( MediaWikiServices $services ): ColorModeResolver {
		return new ColorModeResolver(
			$services->get( 'Cosmos.ThemeStore' ),
			$services->getUserOptionsLookup()
		);
	},

	'Cosmos.Config' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'CosmosBeta' );
	},

	'Cosmos.ConfigDefaults' => static function ( MediaWikiServices $services ): ConfigDefaults {
		return new ConfigDefaults(
			new ServiceOptions(
				ConfigDefaults::CONSTRUCTOR_OPTIONS,
				$services->get( 'Cosmos.Config' )
			)
		);
	},

	'Cosmos.EffectiveTheme' => static function ( MediaWikiServices $services ): EffectiveTheme {
		return new EffectiveTheme(
			$services->get( 'Cosmos.ColorModeResolver' ),
			$services->get( 'Cosmos.ThemeDefaults' ),
			$services->get( 'Cosmos.ThemeStore' ),
			new ServiceOptions(
				EffectiveTheme::CONSTRUCTOR_OPTIONS,
				$services->get( 'Cosmos.Config' )
			)
		);
	},

	'Cosmos.FontLookup' => static function ( MediaWikiServices $services ): FontLookup {
		return new FontLookup(
			$services->getRepoGroup(),
			$services->getTitleFactory()
		);
	},

	'Cosmos.HookRunner' => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner( $services->getHookContainer() );
	},

	'Cosmos.LegacyConverter' => static function (): LegacyConverter {
		return new LegacyConverter();
	},

	'Cosmos.LessUtil' => static function ( MediaWikiServices $services ): LessUtil {
		return new LessUtil( $services->get( 'Cosmos.EffectiveTheme' ) );
	},

	'Cosmos.Navigation' => static function ( MediaWikiServices $services ): CosmosNavigation {
		return new CosmosNavigation(
			$services->getExtensionRegistry(),
			$services->getContentLanguageCode(),
			$services->getTitleFactory(),
			$services->getUrlUtils(),
			$services->getMainWANObjectCache(),
			new ServiceOptions(
				CosmosNavigation::CONSTRUCTOR_OPTIONS,
				$services->get( 'Cosmos.Config' )
			)
		);
	},

	'Cosmos.RailBuilder' => static function ( MediaWikiServices $services ): RailBuilder {
		return new RailBuilder(
			$services->get( 'Cosmos.EffectiveTheme' ),
			$services->get( 'Cosmos.HookRunner' ),
			$services->get( 'Cosmos.TemplateParser' ),
			$services->getConnectionProvider(),
			$services->getLinkRenderer(),
			$services->getSpecialPageFactory(),
			$services->getUserFactory(),
			$services->getMainWANObjectCache(),
			RequestContext::getMain(),
			new ServiceOptions(
				RailBuilder::CONSTRUCTOR_OPTIONS,
				$services->get( 'Cosmos.Config' )
			),
		);
	},

	'Cosmos.TemplateParser' => static function (): TemplateParser {
		$parser = new TemplateParser( __DIR__ . '/../templates' );
		$parser->enableRecursivePartials( true );
		return $parser;
	},

	'Cosmos.ThemeDefaults' => static function ( MediaWikiServices $services ): ThemeDefaults {
		$configDefaults = $services->get( 'Cosmos.ConfigDefaults' );
		Assert::postcondition( $configDefaults instanceof ConfigDefaults, 'Cosmos.ConfigDefaults is a config' );
		return $configDefaults->isThemeDesignerOnly() ? ThemeDefaults::newBuiltIn() : $configDefaults->getDefaults();
	},

	'Cosmos.ThemeStore' => static function ( MediaWikiServices $services ): ThemeStore {
		return new ThemeStore(
			$services->getActorNormalization(),
			$services->getConnectionProvider(),
			$services->getMainWANObjectCache(),
			LoggerFactory::getInstance( 'Cosmos' )
		);
	},

	'Cosmos.WordmarkLookup' => static function ( MediaWikiServices $services ): WordmarkLookup {
		$theme = $services->get( 'Cosmos.EffectiveTheme' );
		Assert::postcondition( $theme instanceof EffectiveTheme, 'Cosmos.EffectiveTheme is an EffectiveTheme' );
		return new WordmarkLookup(
			$services->getRepoGroup(),
			$services->getTitleFactory(),
			$theme->getWordmark()
		);
	},
];

// @codeCoverageIgnoreEnd
