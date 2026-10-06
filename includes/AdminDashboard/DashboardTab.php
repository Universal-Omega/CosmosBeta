<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\AdminDashboard;

use function strtolower;
use function trim;

enum DashboardTab: string {

	case General = 'general';
	case Advanced = 'advanced';

	public static function fromSubPage( ?string $subPage ): ?self {
		return self::tryFrom( strtolower( trim( $subPage ?? '', '/' ) ) );
	}

	public function getMessageKey(): string {
		return "cosmosbeta-admindashboard-tab-{$this->value}";
	}
}
