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
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function htmlspecialchars;
use const NS_SPECIAL;
use const NS_USER;

class RecentChangesModuleFactory {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::EnabledRailModules,
		MainConfigNames::ContentNamespaces,
	];

	private const int LIMIT = 4;
	private const int CACHE_SECONDS = 30;

	public function __construct(
		private readonly CosmosConfig $cosmosConfig,
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

	/**
	 * @return RailModule|null Null when the module is off, or there is nothing to list
	 */
	public function newModule(): ?RailModule {
		$configured = $this->options->get( ConfigNames::EnabledRailModules )[RailModule::ID_RECENT_CHANGES] ?? false;
		$rules = $this->cosmosConfig->getRailRules( RailModule::ID_RECENT_CHANGES );
		if ( !( $rules->enabled ?? (bool)$configured ) ) {
			return null;
		}

		$entries = $this->getEntries();
		return $entries === [] ?
			null :
			RailModule::newWithRecentChanges( RailModuleType::fromMixed( $configured ), $entries );
	}

	/**
	 * @return array<int, array{html-page: string, html-user: string, time: string}>
	 */
	private function getEntries(): array {
		$language = $this->context->getLanguage();
		$entries = [];
		foreach ( $this->getChanges() as $change ) {
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
	private function getChanges(): array {
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'Cosmos', 'rail-recentchanges', self::LIMIT ),
			self::CACHE_SECONDS,
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
					->limit( self::LIMIT )
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
