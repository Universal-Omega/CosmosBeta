<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Context\IContextSource;
use MediaWiki\Skin\Cosmos\CosmosConfig;
use MediaWiki\Skin\Cosmos\Hooks\CosmosHookRunner;
use function array_keys;

/**
 * Puts the rail of a page together from the modules the wiki, the theme and extensions provide.
 */
class RailBuilder {

	/** @var RailModule[]|null The modules that show on this page, built on first use */
	private ?array $modules = null;

	/** @var RailModule[] */
	private array $sidebarModules = [];

	/** @var array<int, array{html-item: string}> */
	private array $toolItems = [];

	private bool $toolsInRail = false;

	public function __construct(
		private readonly CosmosConfig $cosmosConfig,
		private readonly CosmosHookRunner $hookRunner,
		private readonly RailVisibility $visibility,
		private readonly RailRenderer $renderer,
		private readonly RecentChangesModuleFactory $recentChanges,
		private readonly MessageModuleFactory $messageModules,
		private readonly SidebarModuleFactory $sidebarFactory,
		private readonly IContextSource $context,
	) {
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
		return $this->setSidebarModules( $this->sidebarFactory->newPlaceholders( $sections ) );
	}

	/**
	 * @param array $sidebar The sidebar template data of the skin
	 */
	public function setSidebarModulesFromPortlets( array $sidebar ): self {
		return $this->setSidebarModules( $this->sidebarFactory->newFromPortlets( $sidebar ) );
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

		foreach ( array_keys( $this->messageModules->getConfiguredTypes() ) as $message ) {
			$modules[] = new RailModuleInfo(
				MessageModuleFactory::getInterfaceModuleId( $message ),
				RailModuleOrigin::Interface,
				$message
			);
		}

		foreach ( $this->cosmosConfig->getCustomRailModules() as $custom ) {
			$modules[] = new RailModuleInfo(
				MessageModuleFactory::getCustomModuleId( $custom['id'] ),
				RailModuleOrigin::Custom,
				$custom['header'] !== '' ? $custom['header'] : $custom['message']
			);
		}

		foreach ( array_keys( $this->runHook( [] ) ) as $id ) {
			$modules[] = new RailModuleInfo( (string)$id, RailModuleOrigin::Hook, (string)$id );
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
		$modules = [
			...$this->getRecentChangesModules(),
			...$this->messageModules->newInterfaceModules(),
			...$this->messageModules->newCustomModules(),
		];

		$modules = [ ...$this->applyHook( $modules ), ...$this->sidebarModules ];

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
	 * Skips the query when the module is hidden on this page.
	 *
	 * @return RailModule[]
	 */
	private function getRecentChangesModules(): array {
		if ( !$this->visibility->isModuleShown( RailModule::ID_RECENT_CHANGES ) ) {
			return [];
		}

		$module = $this->recentChanges->newModule();
		return $module === null ? [] : [ $module ];
	}

	/**
	 * Lets extensions change the modules so far and add their own.
	 *
	 * @param RailModule[] $modules
	 * @return RailModule[]
	 */
	private function applyHook( array $modules ): array {
		$data = [];
		foreach ( $modules as $module ) {
			$data[$module->id] = $module->toHookData();
		}

		$result = [];
		foreach ( $this->runHook( $data ) as $id => $moduleData ) {
			$result[] = RailModule::newFromHookData( (string)$id, $moduleData );
		}

		return $result;
	}

	/**
	 * @param array<string, array> $modules
	 * @return array<string|int, array>
	 */
	private function runHook( array $modules ): array {
		$this->hookRunner->onCosmosRailBuilder( $modules, $this->context->getSkin() );
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
}
