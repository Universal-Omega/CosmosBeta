<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\AdminDashboard;

use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\SiteStats\SiteStats;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;
use function array_fill_keys;
use function array_map;
use function gmdate;
use function range;

final readonly class AdminDashboardStats {

	private const int DAYS = 7;

	private const int SECONDS_PER_DAY = 86400;

	public function __construct(
		private WANObjectCache $cache,
		private IConnectionProvider $dbProvider,
	) {
	}

	public function getStats(): DashboardStats {
		$activity = $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'CosmosBeta', 'admindashboard', 'activity', self::DAYS ),
			WANObjectCache::TTL_HOUR,
			$this->fetchActivity( ... )
		);

		return new DashboardStats(
			articles: SiteStats::articles(),
			pages: SiteStats::pages(),
			edits: SiteStats::edits(),
			files: SiteStats::images(),
			users: SiteStats::users(),
			activeUsers: SiteStats::activeUsers(),
			days: array_map(
				static fn ( array $row ): DailyActivity => new DailyActivity( ...$row ),
				$activity
			),
		);
	}

	/**
	 * @return list<array{day: string, edits: int, newPages: int, uploads: int}>
	 */
	private function fetchActivity(): array {
		$days = $this->getDays();
		$dbr = $this->dbProvider->getReplicaDatabase();
		$day = $dbr->buildSubString( 'rc_timestamp', 1, 8 );

		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'day' => $day, 'rc_source', 'rc_log_type', 'total' => 'COUNT(*)' ] )
			->from( 'recentchanges' )
			->where( [
				$dbr->expr( 'rc_timestamp', '>=', $dbr->timestamp( $days[self::DAYS - 1] . '000000' ) ),
				$dbr->expr( 'rc_source', '=', [ RecentChange::SRC_EDIT, RecentChange::SRC_NEW, RecentChange::SRC_LOG ] ),
			] )
			->groupBy( [ $day, 'rc_source', 'rc_log_type' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$activity = array_fill_keys( $days, [ 'edits' => 0, 'newPages' => 0, 'uploads' => 0 ] );

		foreach ( $res as $row ) {
			if ( !isset( $activity[$row->day] ) ) {
				continue;
			}

			match ( true ) {
				$row->rc_source === RecentChange::SRC_EDIT => $activity[$row->day]['edits'] += (int)$row->total,
				$row->rc_source === RecentChange::SRC_NEW => $activity[$row->day]['newPages'] += (int)$row->total,
				$row->rc_log_type === 'upload' => $activity[$row->day]['uploads'] += (int)$row->total,
				default => null,
			};
		}

		$rows = [];

		foreach ( $activity as $date => $counts ) {
			$rows[] = [ 'day' => (string)$date ] + $counts;
		}

		return $rows;
	}

	/**
	 * @return list<string> UTC dates as YYYYMMDD, newest first
	 */
	private function getDays(): array {
		$now = (int)ConvertibleTimestamp::time();

		return array_map(
			static fn ( int $offset ): string => gmdate( 'Ymd', $now - $offset * self::SECONDS_PER_DAY ),
			range( 0, self::DAYS - 1 )
		);
	}
}
