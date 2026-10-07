<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Skin\Cosmos\Hooks\CosmosHookRunner;
use MediaWiki\Skin\Cosmos\Rail\RailRules;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function array_keys;
use function array_unique;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_string;
use function preg_replace;
use function strtolower;
use function trim;
use const NS_SPECIAL;
use const NS_USER;

class CosmosRailBuilder {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::EnabledRailModules,
		ConfigNames::RailDisabledNamespaces,
		ConfigNames::RailDisabledPages,
		MainConfigNames::ContentNamespaces,
	];

	public const string MODULE_RECENT_CHANGES = 'recentchanges';
	public const string MODULE_PAGE_TOOLS = 'page-tools';

	public const string ORIGIN_BUILT_IN = 'builtin';
	public const string ORIGIN_INTERFACE = 'interface';
	public const string ORIGIN_CUSTOM = 'custom';
	public const string ORIGIN_HOOK = 'hook';
	public const string ORIGIN_SIDEBAR = 'sidebar';

	private const int RECENT_CHANGES_LIMIT = 4;

	/** @var array<string, array>|null Modules that show on this page, built on first use */
	private ?array $modules = null;
	private array $sidebarModules = [];
	private array $toolItems = [];
	private bool $toolsInRail = false;

	public function __construct(
		private readonly CosmosConfig $cosmosConfig,
		private readonly CosmosHookRunner $hookRunner,
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

	public function setToolsModule( bool $enabled, array $items ): self {
		$this->toolsInRail = $enabled;
		$this->toolItems = $items;
		$this->modules = null;
		return $this;
	}

	/**
	 * @param array[] $modules Each with an id, a label and a list of html-item entries
	 */
	public function setSidebarModules( array $modules ): self {
		$this->sidebarModules = $modules;
		$this->modules = null;
		return $this;
	}

	public function buildRail(): string {
		$modules = [];
		foreach ( $this->getModules() as $module ) {
			$modules[] = [
				'class' => $this->getModuleClasses( (array)( $module['class'] ?? 'custom-module' ) ),
				'is-sticky' => $module['type'] === RailRules::TYPE_STICKY,
				'header' => isset( $module['header'] ) ? $this->getHeader( $module['header'] ) : null,
				'array-recentchanges' => $module['recentchanges'] ?? null,
				'data-tools' => isset( $module['tools'] ) ? [ 'array-items' => $module['tools'] ] : null,
				'html-body' => $module['body'] ?? '',
			];
		}

		if ( !$modules ) {
			return '';
		}

		return $this->templateParser->processTemplate( 'Rail', [ 'array-modules' => $modules ] );
	}

	public function hasModules(): bool {
		return $this->getModules() !== [];
	}

	/**
	 * Whether the rail as a whole is off, whatever the page. Modules can still be hidden one by one.
	 */
	public function isHidden(): bool {
		$settings = $this->cosmosConfig->getRailSettings();
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
	 * @return array<int, array{id: string, origin: string, label: string}>
	 */
	public function getAvailableModules(): array {
		$modules = [
			$this->describeModule( self::MODULE_RECENT_CHANGES, self::ORIGIN_BUILT_IN, '' ),
			$this->describeModule( self::MODULE_PAGE_TOOLS, self::ORIGIN_BUILT_IN, '' ),
		];

		foreach ( array_keys( $this->getConfiguredInterfaceModules() ) as $message ) {
			$modules[] = $this->describeModule( "interface-$message", self::ORIGIN_INTERFACE, (string)$message );
		}

		foreach ( $this->cosmosConfig->getCustomRailModules() as $custom ) {
			$modules[] = $this->describeModule(
				"custom-{$custom['id']}",
				self::ORIGIN_CUSTOM,
				$custom['header'] !== '' ? $custom['header'] : $custom['message']
			);
		}

		$fromHooks = [];
		$this->hookRunner->onCosmosRailBuilder( $fromHooks, $this->context->getSkin() );
		foreach ( array_keys( $fromHooks ) as $id ) {
			$modules[] = $this->describeModule( (string)$id, self::ORIGIN_HOOK, (string)$id );
		}

		foreach ( $this->sidebarModules as $sidebar ) {
			$modules[] = $this->describeModule( $sidebar['id'], self::ORIGIN_SIDEBAR, $sidebar['label'] );
		}

		return $modules;
	}

	/** @return array<string, array> */
	private function getModules(): array {
		return $this->modules ??= ( $this->isHidden() ? [] : $this->resolveModules() );
	}

	/**
	 * Applies what the theme says about each module to the ones the wiki and extensions offer.
	 *
	 * @return array<string, array>
	 */
	private function resolveModules(): array {
		$modules = [];
		foreach ( $this->collectModules() as $id => $module ) {
			$id = (string)$id;
			if ( !$this->isAllowed( $id ) ) {
				continue;
			}

			$type = $this->cosmosConfig->getRailRules( $id )->type ?? $module['type'] ?? null;
			$module['type'] = in_array( $type, RailRules::TYPES, true ) ? $type : RailRules::TYPE_NORMAL;
			$modules[$id] = $module;
		}

		return $modules;
	}

	/** @return array<string|int, array> */
	private function collectModules(): array {
		$modules = [];
		$this->addRecentChangesModule( $modules );
		$this->addInterfaceModules( $modules );
		$this->addCustomModules( $modules );
		$this->hookRunner->onCosmosRailBuilder( $modules, $this->context->getSkin() );
		$this->addSidebarModules( $modules );
		$this->addPageToolsModule( $modules );

		return $modules;
	}

	private function isAllowed( string $id ): bool {
		$settings = $this->cosmosConfig->getRailSettings();

		return $this->cosmosConfig->getRailRules( $id )->isShownOn(
			$this->context->getTitle(),
			$settings['disabledNamespaces'] ?? $this->options->get( ConfigNames::RailDisabledNamespaces ),
			$settings['disabledPages'] ?? $this->options->get( ConfigNames::RailDisabledPages )
		);
	}

	private function describeModule( string $id, string $origin, string $label ): array {
		return [ 'id' => $id, 'origin' => $origin, 'label' => $label ];
	}

	/** @return array<string, string|bool> Interface message to the type it is shown as */
	private function getConfiguredInterfaceModules(): array {
		$modules = $this->options->get( ConfigNames::EnabledRailModules )['interface'] ?? [];
		return (array)( $modules[0] ?? $modules );
	}

	private function addRecentChangesModule( array &$modules ): void {
		$configured = $this->options->get( ConfigNames::EnabledRailModules )[self::MODULE_RECENT_CHANGES] ?? false;
		$isEnabled = $this->cosmosConfig->getRailRules( self::MODULE_RECENT_CHANGES )->enabled ?? (bool)$configured;
		if ( !$isEnabled || !$this->isAllowed( self::MODULE_RECENT_CHANGES ) ) {
			return;
		}

		$entries = $this->getRecentChangeEntries();
		if ( $entries === [] ) {
			return;
		}

		$modules[self::MODULE_RECENT_CHANGES] = [
			'class' => 'recentchanges-module',
			'header' => 'recentchanges',
			'type' => is_string( $configured ) ? $configured : RailRules::TYPE_NORMAL,
			'recentchanges' => $entries,
		];
	}

	private function addInterfaceModules( array &$modules ): void {
		foreach ( $this->getConfiguredInterfaceModules() as $message => $type ) {
			$module = $type ? $this->buildMessageModule( (string)$message, null, (string)$type ) : null;
			if ( $module !== null ) {
				$modules["interface-$message"] = $module;
			}
		}
	}

	private function addCustomModules( array &$modules ): void {
		foreach ( $this->cosmosConfig->getCustomRailModules() as $custom ) {
			$module = $this->buildMessageModule(
				$custom['message'],
				$custom['header'] !== '' ? $custom['header'] : null,
				RailRules::TYPE_NORMAL
			);

			if ( $module !== null ) {
				$modules["custom-{$custom['id']}"] = $module;
			}
		}
	}

	private function addSidebarModules( array &$modules ): void {
		foreach ( $this->sidebarModules as $sidebar ) {
			$modules[$sidebar['id']] = [
				'class' => 'sidebar-module',
				'header' => $sidebar['label'],
				'tools' => $sidebar['items'],
			];
		}
	}

	private function addPageToolsModule( array &$modules ): void {
		if ( $this->toolsInRail ) {
			$modules[self::MODULE_PAGE_TOOLS] = [
				'class' => 'page-tools-module',
				'header' => 'cosmosbeta-rail-page-tools',
				'tools' => $this->toolItems,
			];
		}
	}

	private function buildMessageModule( string $message, ?string $header, string $type ): ?array {
		$text = $this->context->msg( $message );
		if ( $text->isDisabled() ) {
			return null;
		}

		$module = [
			'body' => $text->parse(),
			'class' => 'interface-module',
			'type' => $type,
		];

		return $header === null ? $module : $module + [ 'header' => $header ];
	}

	/** @return array<int, array{html-page: string, html-user: string, time: string}> */
	private function getRecentChangeEntries(): array {
		$language = $this->context->getLanguage();
		$entries = [];

		foreach ( $this->getRecentChanges() as $change ) {
			$performer = $change['performer'];
			$target = $performer->isNamed() ?
				new TitleValue( NS_USER, $performer->getTitleKey() ) :
				new TitleValue(
					NS_SPECIAL,
					$this->specialPageFactory->getLocalNameFor( 'Contributions', $performer->getName() )
				);

			$entries[] = [
				'html-page' => $this->linkRenderer->makeKnownLink(
					new TitleValue( (int)$change['namespace'], $change['title'] )
				),
				'html-user' => $this->linkRenderer->makeLink( $target, $performer->getName() ),
				'time' => htmlspecialchars(
					$language->getHumanTimestamp( MWTimestamp::getInstance( $change['timestamp'] ) )
				),
			];
		}

		return $entries;
	}

	private function getRecentChanges(): array {
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'Cosmos', 'recentchanges', self::RECENT_CHANGES_LIMIT ),
			30,
			function (): array {
				$res = $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
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
				foreach ( $res as $row ) {
					$changes[] = [
						'performer' => $this->userFactory->newFromActorId( (int)$row->rc_actor ),
						'timestamp' => $row->rc_timestamp,
						'namespace' => $row->rc_namespace,
						'title' => $row->rc_title,
					];
				}

				return $changes;
			}
		);
	}

	/**
	 * @param string[] $classes
	 */
	private function getModuleClasses( array $classes ): string {
		$all = [];
		foreach ( $classes as $class ) {
			$all[] = $class;
			$all[] = "skin-cosmos-$class";
		}

		return implode( ' ', array_unique( $all ) );
	}

	/**
	 * Headers are message keys, or plain text when no such message exists.
	 */
	private function getHeader( string $label ): string {
		$message = $this->context->msg( $label );
		return $message->exists() && !$message->isDisabled() ? $message->text() : $label;
	}
}
