<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\AdminDashboard;

use function array_column;
use function array_sum;

final readonly class DashboardStats {

	/**
	 * @param DailyActivity[] $days Newest day first
	 */
	public function __construct(
		public int $articles,
		public int $pages,
		public int $edits,
		public int $files,
		public int $users,
		public int $activeUsers,
		public array $days,
	) {
	}

	public function getWeeklyEdits(): int {
		return array_sum( array_column( $this->days, 'edits' ) );
	}

	public function getWeeklyNewPages(): int {
		return array_sum( array_column( $this->days, 'newPages' ) );
	}

	public function getWeeklyUploads(): int {
		return array_sum( array_column( $this->days, 'uploads' ) );
	}
}
