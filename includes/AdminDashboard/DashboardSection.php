<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\AdminDashboard;

enum DashboardSection: string {

	case Wiki = 'wiki';
	case Community = 'community';
	case Content = 'content';

	public function getMessageKey(): string {
		return "cosmos-admindashboard-section-{$this->value}";
	}
}
