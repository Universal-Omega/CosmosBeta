<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\AdminDashboard;

final readonly class DailyActivity {

	public function __construct(
		public string $day,
		public int $edits,
		public int $newPages,
		public int $uploads,
	) {
	}
}
