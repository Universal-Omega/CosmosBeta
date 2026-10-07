<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\CosmosConfig;
use MediaWiki\Skin\Cosmos\Hooks\HookRunner;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function array_keys;
use function array_map;
use function array_merge;
use function htmlspecialchars;
use function in_array;
use function is_string;
use function preg_replace;
use function strtolower;
use function strtoupper;
use function trim;
use const NS_SPECIAL;
use const NS_USER;

/**
 * Puts the rail of a page together from the modules the wiki, the theme and extensions provide.
 */
class RailBuilder {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::EnabledRailModules,
		ConfigNames::RailSidebarPortlets,
		MainConfigNames::ContentNamespaces,
	];

	private const int RECENT_CHANGES_LIMIT = 4;
	private const int RECENT_CHANGES_CACHE_SECONDS = 30;

	/** @var RailModule[]|null The modules that show on this page, built on first use */
	private ?array $modules = null;

	/** @var RailModule[] */
	private array $sidebarModules = [];

	/** @var array<int, array{html-item: string}> */
	private array $toolItems = [];

	private bool $toolsInRail = false;

	public function __construct(
		private readonly CosmosConfig $cosmosConfig,
		private readonly HookRunner $hookRunner,
		private readonly RailVisibility $visibility,
		private readonly RailRenderer $renderer,
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
		$settings = $this->cosmosConfig->getToolbarSettings();
		return $settings['enabled'] &&
			$settings['style'] === 'rail' &&
			$this->visibility->canShowModule( RailModule::ID_PAGE_TOOLS );
	}

	public function buildRail(): string {
		return $this->renderer->render( $this->getModules() );
	}

	public function hasModules(): bool {
		return $this->getModules() !== [];
	}

	/**
	 * Lists every module the rail can show, so the theme designer can offer a control for each.
	 *
	 * @return RailModuleInfo[]
	 */
	public function getAvailableModules(): array {
		$modules = [
			new RailModuleInfo( RailModule::ID_RECENT_CHANGES, RailModuleOrigin::BuiltIn, '' ),
			new RailModuleInfo( RailModule::ID_PAGE_TOOLS, RailModuleOrigin::BuiltIn, '' ),
		];

		foreach ( array_keys( $this->getConfiguredInterfaceTypes() ) as $message ) {
			$modules[] = new RailModuleInfo( "interface-$message", RailModuleOrigin::Interface, $message );
		}

		foreach ( $this->cosmosConfig->getCustomRailModules() as $custom ) {
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
		return $this->modules ??= ( $this->visibility->isRailHidden() ? [] : $this->resolveModules() );
	}

	/**
	 * Applies what the theme says about each module to the ones the wiki and extensions offer.
	 *
	 * @return RailModule[]
	 */
	private function resolveModules(): array {
		$modules = [];
		foreach ( $this->collectModules() as $module ) {
			if ( !$this->visibility->isModuleShown( $module->id ) ) {
				continue;
			}

			$type = $this->cosmosConfig->getRailRules( $module->id )->type;
			$modules[] = $type === null ? $module : $module->withType( $type );
		}

		return $modules;
	}

	/**
	 * @return RailModule[]
	 */
	private function collectModules(): array {
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
		$modules = [ ...$list, ...$this->sidebarModules ];

		if ( $this->toolsInRail ) {
			$modules[] = RailModule::newWithTools(
				RailModule::ID_PAGE_TOOLS,
				RailModuleType::Normal,
				'page-tools-module',
				'cosmosbeta-rail-page-tools',
				$this->toolItems
			);
		}

		return $modules;
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
	 * The wiki configuration is loose about types, so anything but a known type is normal.
	 */
	private function getConfiguredType( mixed $configured ): RailModuleType {
		return ( is_string( $configured ) ? RailModuleType::tryFrom( $configured ) : null ) ?? RailModuleType::Normal;
	}

	/**
	 * @return array<string, RailModuleType> The configured messages, by the type each is shown as
	 */
	private function getConfiguredInterfaceTypes(): array {
		$configured = $this->options->get( ConfigNames::EnabledRailModules )['interface'] ?? [];
		$types = [];

		foreach ( (array)( $configured[0] ?? $configured ) as $message => $type ) {
			if ( $type ) {
				$types[(string)$message] = $this->getConfiguredType( $type );
			}
		}

		return $types;
	}

	/**
	 * @return RailModule[]
	 */
	private function newInterfaceModules(): array {
		$modules = [];
		foreach ( $this->getConfiguredInterfaceTypes() as $message => $type ) {
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
		foreach ( $this->cosmosConfig->getCustomRailModules() as $custom ) {
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
		$configured = $this->options->get( ConfigNames::EnabledRailModules )[RailModule::ID_RECENT_CHANGES] ?? false;
		$rules = $this->cosmosConfig->getRailRules( RailModule::ID_RECENT_CHANGES );
		$isEnabled = $rules->enabled ?? (bool)$configured;
		if ( !$isEnabled || !$this->visibility->isModuleShown( RailModule::ID_RECENT_CHANGES ) ) {
			return [];
		}

		$entries = $this->getRecentChangeEntries();
		return $entries === [] ?
			[] :
			[ RailModule::newWithRecentChanges( $this->getConfiguredType( $configured ), $entries ) ];
	}

	/**
	 * @return array<int, array{html-page: string, html-user: string, time: string}>
	 */
	private function getRecentChangeEntries(): array {
		$language = $this->context->getLanguage();
		$entries = [];

		foreach ( $this->getRecentChanges() as $change ) {
			$performer = $this->userFactory->newFromActorId( $change['actor'] );
			$target = $performer->isNamed() ?
				new TitleValue( NS_USER, $performer->getTitleKey() ) :
				new TitleValue(
					NS_SPECIAL,
					$this->specialPageFactory->getLocalNameFor( 'Contributions', $performer->getName() )
				);

			$entries[] = [
				'html-page' => $this->linkRenderer->makeKnownLink(
					new TitleValue( $change['namespace'], $change['title'] )
				),
				'html-user' => $this->linkRenderer->makeLink( $target, $performer->getName() ),
				'time' => htmlspecialchars(
					$language->getHumanTimestamp( MWTimestamp::getInstance( $change['timestamp'] ) )
				),
			];
		}

		return $entries;
	}

	/**
	 * @return array<int, array{actor: int, namespace: int, title: string, timestamp: string}>
	 */
	private function getRecentChanges(): array {
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'Cosmos', 'rail-recentchanges', self::RECENT_CHANGES_LIMIT ),
			self::RECENT_CHANGES_CACHE_SECONDS,
			function (): array {
				$rows = $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
					->select( [ 'rc_actor', 'rc_namespace', 'rc_title', 'rc_timestamp' ] )
					->from( 'recentchanges' )
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
					$changes[] = [
						'actor' => (int)$row->rc_actor,
						'namespace' => (int)$row->rc_namespace,
						'title' => $row->rc_title,
						'timestamp' => $row->rc_timestamp,
					];
				}

				return $changes;
			}
		);
	}
}
