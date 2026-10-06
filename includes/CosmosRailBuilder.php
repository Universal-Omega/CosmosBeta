<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Skins\CosmosBeta\Hooks\CosmosHookRunner;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function array_unique;
use function htmlspecialchars;
use function implode;
use function in_array;
use const NS_SPECIAL;
use const NS_USER;

class CosmosRailBuilder {

	public const array CONSTRUCTOR_OPTIONS = [
		MainConfigNames::ContentNamespaces,
		ConfigNames::EnabledRailModules,
		ConfigNames::RailDisabledNamespaces,
		ConfigNames::RailDisabledPages,
	];

	private const int RECENT_CHANGES_LIMIT = 4;

	private array $disabledModules = [];
	private array $sidebarModules = [];
	private array $toolItems = [];
	private bool $toolsInRail = false;

	public function __construct(
		private readonly CosmosHookRunner $hookRunner,
		private readonly IConnectionProvider $dbProvider,
		private readonly LinkRenderer $linkRenderer,
		private readonly IContextSource $context,
		private readonly ServiceOptions $options,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserFactory $userFactory,
		private readonly WANObjectCache $cache,
		private readonly CosmosConfig $cosmosConfig,
		private readonly TemplateParser $templateParser,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function setToolsModule( bool $enabled, array $items ): self {
		$this->toolsInRail = $enabled;
		$this->toolItems = $items;
		return $this;
	}

	/**
	 * @param array[] $modules Each with a label and a list of html-item entries
	 */
	public function setSidebarModules( array $modules ): self {
		$this->sidebarModules = $modules;
		return $this;
	}

	public function buildRail(): string {
		$modules = [];
		foreach ( $this->getModules() as $module ) {
			$modules[] = [
				'class' => $this->getModuleClasses( (array)( $module['class'] ?? 'custom-module' ) ),
				'is-sticky' => ( $module['type'] ?? 'normal' ) === 'sticky',
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
		$this->disableModule( 'recentchanges' );
		$hasRecentChangesModule = ( $this->getEnabledModules()['recentchanges'] ?? false ) &&
			$this->getRecentChanges() !== [];

		$hasModules = $hasRecentChangesModule || $this->toolsInRail || $this->sidebarModules !== [] ||
			$this->getModules() !== [];

		$this->resetDisabledModules();
		return $hasModules;
	}

	public function disableModule( string $module ): self {
		$this->disabledModules[] = $module;
		return $this;
	}

	public function resetDisabledModules(): self {
		$this->disabledModules = [];
		return $this;
	}

	public function isHidden(): bool {
		$railSettings = $this->cosmosConfig->getRailSettings();
		if ( !$railSettings['enabled'] ) {
			return true;
		}

		if ( $railSettings['hideForAnons'] && !$this->context->getUser()->isNamed() ) {
			return true;
		}

		$disabledNamespaces = $railSettings['disabledNamespaces'] ??
			$this->options->get( ConfigNames::RailDisabledNamespaces );
		$disabledPages = $railSettings['disabledPages'] ??
			$this->options->get( ConfigNames::RailDisabledPages );

		$title = $this->context->getTitle();
		return $title->inNamespaces( $disabledNamespaces ) ||
			( $title->isMainPage() && in_array( 'mainpage', $disabledPages, true ) ) ||
			in_array( $title->getFullText(), $disabledPages, true ) ||
			(bool)$this->context->getOutput()->getProperty( 'norail' );
	}

	protected function getModules(): array {
		$modules = [];
		if ( $this->isHidden() ) {
			return $modules;
		}

		if ( !in_array( 'recentchanges', $this->disabledModules, true ) ) {
			$this->buildRecentChangesModule( $modules );
		}

		if ( !in_array( 'interface', $this->disabledModules, true ) ) {
			$this->buildInterfaceModules( $modules );
		}

		$this->hookRunner->onCosmosRailBuilder( $modules, $this->context->getSkin() );
		foreach ( $this->sidebarModules as $index => $sidebar ) {
			$modules["sidebar-$index"] = [
				'class' => 'sidebar-module',
				'header' => $sidebar['label'],
				'tools' => $sidebar['items'],
			];
		}

		if ( $this->toolsInRail && $this->toolItems ) {
			$modules['page-tools'] = [
				'class' => 'page-tools-module',
				'header' => 'cosmosbeta-rail-page-tools',
				'tools' => $this->toolItems,
			];
		}

		return $modules;
	}

	protected function getEnabledModules(): array {
		$modules = $this->options->get( ConfigNames::EnabledRailModules );
		$recentChanges = $this->cosmosConfig->getRailSettings()['recentChanges'];
		if ( $recentChanges === 'off' ) {
			$modules['recentchanges'] = false;
		} elseif ( $recentChanges !== '' ) {
			$modules['recentchanges'] = $recentChanges;
		}

		return $modules;
	}

	protected function buildInterfaceModules( array &$modules ): void {
		$interfaceModules = $this->getEnabledModules()['interface'] ?? [];
		$interfaceModules = $interfaceModules[0] ?? $interfaceModules;
		foreach ( (array)$interfaceModules as $message => $type ) {
			if ( $type && !$this->context->msg( $message )->isDisabled() ) {
				$modules["interface-$message"] = [
					'body' => $this->context->msg( $message )->parse(),
					'class' => 'interface-module',
					'type' => $type,
				];
			}
		}
	}

	protected function buildRecentChangesModule( array &$modules ): void {
		$moduleType = $this->getEnabledModules()['recentchanges'] ?? false;
		if ( !$moduleType ) {
			return;
		}

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

		$modules['recentchanges'] = [
			'class' => 'recentchanges-module',
			'header' => 'recentchanges',
			'type' => $moduleType,
			'recentchanges' => $entries,
		];
	}

	protected function getRecentChanges(): array {
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

	private function getHeader( string $label ): string {
		$message = $this->context->msg( $label );
		return $message->isDisabled() ? $label : $message->text();
	}
}
