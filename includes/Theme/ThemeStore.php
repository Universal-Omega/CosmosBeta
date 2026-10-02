<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Theme;

use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use stdClass;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function sha1;
use function wfTimestamp;
use const TS_MW;

/**
 * Stores every published theme as a row. The newest row is the live theme,
 * older rows are kept as history and can be restored.
 *
 * Reads go through a shared cache and a per request copy, so a normal page
 * view costs one cache lookup at most. Saving purges the cache.
 */
class ThemeStore {

	private const string TABLE = 'cosmosbeta_theme';
	private const int CACHE_VERSION = 1;
	private const int MAX_REVISIONS = 100;
	private const int MAX_COMMENT_BYTES = 767;

	private ?ThemeSettings $current = null;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
		private readonly WANObjectCache $cache,
		private readonly ActorNormalization $actorStore,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * The live theme. Returns default settings if nothing was ever saved.
	 */
	public function getCurrent(): ThemeSettings {
		if ( $this->current === null ) {
			$value = $this->cache->getWithSetCallback(
				$this->getCacheKey(),
				WANObjectCache::TTL_DAY,
				function ( mixed $oldValue, int &$ttl, array &$setOpts ): array {
					$dbr = $this->dbProvider->getReplicaDatabase();
					$setOpts += Database::getCacheSetOptions( $dbr );

					try {
						$row = $this->fetchLatest( $dbr, [ 'cth_id', 'cth_data' ] );
					} catch ( DBError $e ) {
						// Table is missing until update.php has run. Fall back to defaults and retry soon.
						$this->logger->error( 'Unable to read Cosmos theme: {message}', [
							'message' => $e->getMessage(),
						] );
						$ttl = 30;

						return [ 'id' => 0, 'json' => '' ];
					}

					return [
						'id' => $row ? (int)$row->cth_id : 0,
						'json' => $row ? (string)$row->cth_data : '',
					];
				},
				[
					'version' => self::CACHE_VERSION,
					'checkKeys' => [ $this->getCheckKey() ],
					'lockTSE' => 30,
					'pcTTL' => WANObjectCache::TTL_PROC_LONG,
				]
			);

			$this->current = $value['json'] === '' ?
				new ThemeSettings() :
				ThemeSettings::newFromJson( $value['json'], $value['id'] );
		}

		return $this->current;
	}

	public function getRevision( int $id ): ?ThemeSettings {
		$row = $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( [ 'cth_id', 'cth_data' ] )
			->from( self::TABLE )
			->where( [ 'cth_id' => $id ] )
			->caller( __METHOD__ )
			->fetchRow();

		return $row ? ThemeSettings::newFromJson( (string)$row->cth_data, (int)$row->cth_id ) : null;
	}

	/** @return array[] Newest first, each with id, timestamp, user and comment */
	public function getHistory( int $limit = 30 ): array {
		$res = $this->dbProvider->getReplicaDatabase()->newSelectQueryBuilder()
			->select( [ 'cth_id', 'cth_timestamp', 'cth_comment', 'actor_name' ] )
			->from( self::TABLE )
			->join( 'actor', null, 'actor_id = cth_actor' )
			->orderBy( 'cth_id', SelectQueryBuilder::SORT_DESC )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$history = [];
		foreach ( $res as $row ) {
			$history[] = [
				'id' => (int)$row->cth_id,
				'timestamp' => wfTimestamp( TS_MW, $row->cth_timestamp ),
				'user' => $row->actor_name,
				'comment' => (string)$row->cth_comment,
			];
		}

		return $history;
	}

	/** @return int Id of the live revision. Unchanged settings do not create a new one. */
	public function save( ThemeSettings $settings, UserIdentity $user, string $comment = '' ): int {
		$dbw = $this->dbProvider->getPrimaryDatabase();

		$json = $settings->toJson();
		$hash = sha1( $json );

		$latest = $this->fetchLatest( $dbw, [ 'cth_id', 'cth_hash' ] );
		if ( $latest && $latest->cth_hash === $hash ) {
			return (int)$latest->cth_id;
		}

		$dbw->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->row( [
				'cth_timestamp' => $dbw->timestamp(),
				'cth_actor' => $this->actorStore->acquireActorId( $user, $dbw ),
				'cth_comment' => mb_strcut( $comment, 0, self::MAX_COMMENT_BYTES ),
				'cth_version' => ThemeSettings::SCHEMA_VERSION,
				'cth_hash' => $hash,
				'cth_data' => $json,
			] )
			->caller( __METHOD__ )
			->execute();

		$id = $dbw->insertId();

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( $dbw->expr( 'cth_id', '<=', $id - self::MAX_REVISIONS ) )
			->caller( __METHOD__ )
			->execute();

		$this->purgeCache();

		return $id;
	}

	/**
	 * Publishes an older revision again as a new one.
	 *
	 * @return int|null New revision id, or null if the revision does not exist
	 */
	public function restore( int $id, UserIdentity $user, string $comment = '' ): ?int {
		$settings = $this->getRevision( $id );

		return $settings ? $this->save( $settings, $user, $comment ) : null;
	}

	/**
	 * Clears cached copies, for this process and for every other one.
	 */
	public function purgeCache(): void {
		$this->cache->touchCheckKey( $this->getCheckKey() );
		$this->cache->delete( $this->getCacheKey() );
		$this->current = null;
	}

	/** @param string[] $fields */
	private function fetchLatest( IReadableDatabase $db, array $fields ): stdClass|false {
		return $db->newSelectQueryBuilder()
			->select( $fields )
			->from( self::TABLE )
			->orderBy( 'cth_id', SelectQueryBuilder::SORT_DESC )
			->limit( 1 )
			->caller( __METHOD__ )
			->fetchRow();
	}

	private function getCacheKey(): string {
		return $this->cache->makeKey( 'CosmosBeta', 'theme', 'current' );
	}

	private function getCheckKey(): string {
		return $this->cache->makeKey( 'CosmosBeta', 'theme', 'check' );
	}
}
