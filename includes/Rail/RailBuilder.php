<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\Hooks\HookRunner;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function htmlspecialchars;
use function implode;
use function in_array;
use function preg_replace;
use function str_replace;
use function strtolower;
use function strtoupper;
use function trim;
use const NS_SPECIAL;
use const NS_USER;

class RailBuilder {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::RailSidebarPortlets,
		MainConfigNames::ContentNamespaces,
	];

	private const int RECENT_CHANGES_CACHE_SECONDS = 30;
	private const int RECENT_CHANGES_CACHE_VERSION = 1;
	private const int RECENT_CHANGES_LIMIT = 4;

	/** @var RailModule[]|null The modules that do not depend on the page being built, built on first use */
	private ?array $baseModules = null;

	/** @var RailModule[]|null The modules that show on this page, built on first use */
	private ?array $modules = null;

	/** @var RailModule[] */
	private array $sidebarModules = [];

	/** @var array<int, array{html-item: string}> */
	private array $toolItems = [];
	private bool $toolsInRail = false;

	public function __construct(
		private readonly EffectiveTheme $theme,
		private readonly HookRunner $hookRunner,
		private readonly TemplateParser $templateParser,
		private readonly IConnectionProvider $dbProvider,
		private readonly LinkRenderer $linkRenderer,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserFactory $userFactory,
		private readonly WANObjectCache $cache,
		private readonly IContextSource $context,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public static function getSidebarModuleId( string $name ): string {
		return 'sidebar-' . trim( (string)preg_replace( '/[^a-z0-9._-]+/', '-', strtolower( $name ) ), '-' );
	}

	public static function getRecentChangesCacheKey( WANObjectCache $cache ): string {
		return $cache->makeKey( 'Cosmos', 'rail-recentchanges', self::RECENT_CHANGES_CACHE_VERSION );
	}

	/**
	 * @param array<int, array{html-item: string}> $items
	 */
	public function setToolsModule( bool $enabled, array $items ): self {
		$this->toolsInRail = $enabled;
		$this->toolItems = $items;
		$this->modules = null;
		return $this;
	}

	/**
	 * Stands in for the sidebar modules before the page is built, so the rail styles load only when there is a rail.
	 *
	 * @param array<string, array> $sections The sidebar as core builds it, by section name
	 */
	public function setSidebarModulesFromSections( array $sections ): self {
		$names = $this->getSidebarNames();
		$modules = [];
		foreach ( $sections as $name => $items ) {
			if ( $items && in_array( strtoupper( (string)$name ), $names, true ) ) {
				$modules[] = $this->newSidebarModule( (string)$name, (string)$name, [] );
			}
		}

		return $this->setSidebarModules( $modules );
	}

	/**
	 * Sidebar sections such as the dynamic user sidebar are moved out of the top navigation into the rail.
	 *
	 * @param array $sidebar The sidebar template data of the skin
	 */
	public function setSidebarModulesFromPortlets( array $sidebar ): self {
		$portlets = array_merge( [ $sidebar['data-portlets-first'] ?? null ], $sidebar['array-portlets-rest'] ?? [] );
		$modules = [];
		foreach ( $portlets as $portlet ) {
			$name = $portlet ? $this->findSidebarName( $portlet ) : null;
			$items = array_map(
				static fn ( array $item ): array => [ 'html-item' => $item['html-item'] ?? '' ],
				$portlet['array-items'] ?? []
			);

			if ( $name !== null && $items !== [] ) {
				$modules[] = $this->newSidebarModule( $name, (string)( $portlet['label'] ?? '' ), $items );
			}
		}

		return $this->setSidebarModules( $modules );
	}

	public function shouldPutToolsInRail(): bool {
		$settings = $this->theme->getToolbarSettings();
		return $settings['enabled'] &&
			$settings['style'] === 'rail' &&
			$this->isModuleAllowed( RailModule::ID_PAGE_TOOLS );
	}

	public function buildRail(): string {
		$modules = $this->getModules();
		if ( $modules === [] ) {
			return '';
		}

		return $this->templateParser->processTemplate( 'Rail', [
			'array-modules' => array_map( $this->getTemplateData( ... ), $modules ),
		] );
	}

	public function hasModules(): bool {
		return $this->getModules() !== [];
	}

	/**
	 * Whether the rail as a whole is off, whatever the page. Modules can still be hidden one by one.
	 */
	public function isHidden(): bool {
		$settings = $this->theme->getRailSettings();
		return !$settings['enabled'] ||
			( $settings['hideForAnons'] && !$this->context->getUser()->isNamed() ) ||
			(bool)$this->context->getOutput()->getProperty( 'norail' );
	}

	/**
	 * Whether the rail may show this module on the current page.
	 */
	public function isModuleAllowed( string $id ): bool {
		return !$this->isHidden() && $this->isAllowed( $id );
	}

	/**
	 * Lists every module the rail can show, so the theme designer can offer a control for each.
	 * Sidebar modules are known once the skin has built its template data, so call this after that.
	 *
	 * @return RailModuleInfo[]
	 */
	public function getAvailableModules(): array {
		$modules = [
			new RailModuleInfo( RailModule::ID_RECENT_CHANGES, RailModuleOrigin::BuiltIn, '' ),
			new RailModuleInfo( RailModule::ID_PAGE_TOOLS, RailModuleOrigin::BuiltIn, '' ),
		];

		foreach ( array_keys( $this->theme->getDefaults()->interfaceModules ) as $message ) {
			$modules[] = new RailModuleInfo( "interface-$message", RailModuleOrigin::Interface, $message );
		}

		foreach ( $this->theme->getCustomRailModules() as $custom ) {
			$modules[] = new RailModuleInfo(
				"custom-{$custom['id']}",
				RailModuleOrigin::Custom,
				$custom['header'] !== '' ? $custom['header'] : $custom['message']
			);
		}

		$fromHooks = new RailModuleList();
		$this->hookRunner->onCosmosRailBuilder( $fromHooks, $this->context->getSkin() );
		foreach ( $fromHooks->getIds() as $id ) {
			$modules[] = new RailModuleInfo( $id, RailModuleOrigin::Hook, $id );
		}

		foreach ( $this->sidebarModules as $module ) {
			$modules[] = new RailModuleInfo( $module->id, RailModuleOrigin::Sidebar, (string)$module->header );
		}

		return $modules;
	}

	/**
	 * @return RailModule[]
	 */
	private function getModules(): array {
		return $this->modules ??= ( $this->isHidden() ? [] : $this->resolveModules() );
	}

	/**
	 * Applies what the theme says about each module to the ones the wiki and extensions offer.
	 *
	 * @return RailModule[]
	 */
	private function resolveModules(): array {
		$modules = [];
		foreach ( $this->collectModules() as $module ) {
			if ( !$this->isAllowed( $module->id ) ) {
				continue;
			}

			$type = $this->theme->getRailRules( $module->id )->type;
			$modules[] = $type === null ? $module : $module->withType( $type );
		}

		return $modules;
	}

	/**
	 * @return RailModule[]
	 */
	private function collectModules(): array {
		$modules = [ ...$this->getBaseModules(), ...$this->sidebarModules ];
		if ( $this->toolsInRail ) {
			$modules[] = RailModule::newWithTools(
				RailModule::ID_PAGE_TOOLS,
				RailModuleType::Normal,
				'page-tools-module',
				'cosmos-rail-page-tools',
				$this->toolItems
			);
		}

		return $modules;
	}

	/**
	 * The sidebar and the tools change while the skin builds the page, these do not.
	 *
	 * @return RailModule[]
	 */
	private function getBaseModules(): array {
		if ( $this->baseModules !== null ) {
			return $this->baseModules;
		}

		$list = new RailModuleList();
		$builtIn = [
			...$this->newRecentChangesModules(),
			...$this->newInterfaceModules(),
			...$this->newCustomModules(),
		];

		foreach ( $builtIn as $module ) {
			$list->add( $module );
		}

		$this->hookRunner->onCosmosRailBuilder( $list, $this->context->getSkin() );
		return $this->baseModules = $list->getAll();
	}

	private function isAllowed( string $id ): bool {
		// Page tools in the rail are the only way to reach them, so by default they show on every page
		$isEverywhere = $id === RailModule::ID_PAGE_TOOLS;
		$settings = $this->theme->getRailSettings();
		$defaults = $this->theme->getDefaults();

		return $this->theme->getRailRules( $id )->isShownOn(
			$this->context->getTitle(),
			$settings['disabledNamespaces'] ?? ( $isEverywhere ? [] : $defaults->railDisabledNamespaces ),
			$settings['disabledPages'] ?? ( $isEverywhere ? [] : $defaults->railDisabledPages )
		);
	}

	/**
	 * @param RailModule[] $modules
	 */
	private function setSidebarModules( array $modules ): self {
		$this->sidebarModules = $modules;
		$this->modules = null;
		return $this;
	}

	/**
	 * @return string[] The sections to show in the rail, in capitals
	 */
	private function getSidebarNames(): array {
		return array_map( strtoupper( ... ), (array)$this->options->get( ConfigNames::RailSidebarPortlets ) );
	}

	/**
	 * Sections are told apart by the id of the portlet, or by its label when the id is not one of them.
	 */
	private function findSidebarName( array $portlet ): ?string {
		$names = $this->getSidebarNames();
		$id = strtoupper( (string)preg_replace( '/^p-/i', '', (string)( $portlet['id'] ?? '' ) ) );
		$label = strtoupper( trim( (string)( $portlet['label'] ?? '' ) ) );

		return match ( true ) {
			in_array( $id, $names, true ) => $id,
			in_array( $label, $names, true ) => $label,
			default => null,
		};
	}

	/**
	 * @param array<int, array{html-item: string}> $items
	 */
	private function newSidebarModule( string $name, string $label, array $items ): RailModule {
		return RailModule::newWithTools(
			self::getSidebarModuleId( $name ),
			RailModuleType::Normal,
			'sidebar-module',
			$label,
			$items
		);
	}

	/**
	 * @return RailModule[]
	 */
	private function newInterfaceModules(): array {
		$modules = [];
		foreach ( $this->theme->getDefaults()->interfaceModules as $message => $type ) {
			$module = $this->newMessageModule( "interface-$message", $message, null, $type );
			if ( $module !== null ) {
				$modules[] = $module;
			}
		}

		return $modules;
	}

	/**
	 * @return RailModule[]
	 */
	private function newCustomModules(): array {
		$modules = [];
		foreach ( $this->theme->getCustomRailModules() as $custom ) {
			$module = $this->newMessageModule(
				"custom-{$custom['id']}",
				$custom['message'],
				$custom['header'] !== '' ? $custom['header'] : null,
				RailModuleType::Normal
			);

			if ( $module !== null ) {
				$modules[] = $module;
			}
		}

		return $modules;
	}

	private function newMessageModule(
		string $id,
		string $message,
		?string $header,
		RailModuleType $type
	): ?RailModule {
		$text = $this->context->msg( $message );
		if ( $text->isDisabled() ) {
			return null;
		}

		return RailModule::newWithBody( $id, $type, 'interface-module', $header, $text->parse() );
	}

	/**
	 * Skips the query when the module is hidden on this page.
	 *
	 * @return RailModule[]
	 */
	private function newRecentChangesModules(): array {
		$defaults = $this->theme->getDefaults();
		$rules = $this->theme->getRailRules( RailModule::ID_RECENT_CHANGES );
		$isEnabled = $rules->enabled ?? $defaults->recentChanges;
		if ( !$isEnabled || !$this->isAllowed( RailModule::ID_RECENT_CHANGES ) ) {
			return [];
		}

		$entries = $this->getRecentChangeEntries();
		return $entries === [] ?
			[] :
			[ RailModule::newWithRecentChanges( $defaults->recentChangesType, $entries ) ];
	}

	/**
	 * @return array<int, array{html-page: string, html-user: string, time: string}>
	 */
	private function getRecentChangeEntries(): array {
		$language = $this->context->getLanguage();
		$entries = [];
		foreach ( $this->getRecentChanges() as $change ) {
			$target = $change['named'] ?
				new TitleValue( NS_USER, str_replace( ' ', '_', $change['name'] ) ) :
				new TitleValue(
					NS_SPECIAL,
					$this->specialPageFactory->getLocalNameFor( 'Contributions', $change['name'] )
				);

			$entries[] = [
				'html-page' => $this->linkRenderer->makeKnownLink(
					new TitleValue( $change['namespace'], $change['title'] )
				),
				'html-user' => $this->linkRenderer->makeLink( $target, $change['name'] ),
				'time' => htmlspecialchars(
					$language->getHumanTimestamp( MWTimestamp::getInstance( $change['timestamp'] ) )
				),
			];
		}

		return $entries;
	}

	/**
	 * The names are cached with the rows. The cache is emptied when a user is renamed or hidden,
	 * and when the visibility of a revision changes.
	 *
	 * @return array<int, array{name: string, named: bool, namespace: int, title: string, timestamp: string}>
	 */
	private function getRecentChanges(): array {
		return $this->cache->getWithSetCallback(
			self::getRecentChangesCacheKey( $this->cache ),
			self::RECENT_CHANGES_CACHE_SECONDS,
			function (): array {
				$rows = $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
					->select( [ 'actor_name', 'actor_user', 'rc_namespace', 'rc_title', 'rc_timestamp' ] )
					->from( 'recentchanges' )
					->join( 'actor', null, 'actor_id = rc_actor' )
					->where( [
						'rc_namespace' => $this->options->get( MainConfigNames::ContentNamespaces ),
						'rc_source' => [ RecentChange::SRC_NEW, RecentChange::SRC_EDIT ],
						'rc_bot' => 0,
						'rc_deleted' => 0,
					] )
					->orderBy( 'rc_timestamp', SelectQueryBuilder::SORT_DESC )
					->limit( self::RECENT_CHANGES_LIMIT )
					->caller( __METHOD__ )
					->fetchResultSet();

				$changes = [];
				foreach ( $rows as $row ) {
					$performer = $this->userFactory->newFromUserIdentity(
						new UserIdentityValue( (int)$row->actor_user, $row->actor_name )
					);

					$changes[] = [
						'name' => $row->actor_name,
						'named' => $performer->isNamed(),
						'namespace' => (int)$row->rc_namespace,
						'title' => $row->rc_title,
						'timestamp' => $row->rc_timestamp,
					];
				}

				return $changes;
			}
		);
	}

	private function getTemplateData( RailModule $module ): array {
		return [
			'class' => $this->getClasses( $module->classes ),
			'is-sticky' => $module->type === RailModuleType::Sticky,
			'header' => $module->header === null ? null : $this->getHeader( $module->header ),
			'array-recentchanges' => $module->recentChanges ?: null,
			'data-tools' => $module->tools ? [ 'array-items' => $module->tools ] : null,
			'html-body' => $module->body,
		];
	}

	/**
	 * @param string[] $classes
	 */
	private function getClasses( array $classes ): string {
		$all = [];
		foreach ( $classes as $class ) {
			$all[] = $class;
			$all[] = 'skin-cosmos-rail__module--' . preg_replace( '/-module$/', '', $class );
		}

		return implode( ' ', array_unique( $all ) );
	}

	/**
	 * Headers are message keys, or plain text when no such message exists.
	 */
	private function getHeader( string $header ): string {
		$message = $this->context->msg( $header );
		return $message->exists() && !$message->isDisabled() ? $message->text() : $header;
	}
}
