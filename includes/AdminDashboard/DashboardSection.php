<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\AdminDashboard;

enum DashboardSection: string {

	case Wiki = 'wiki';
	case Community = 'community';
	case Content = 'content';

	public function getMessageKey(): string {
		return "cosmosbeta-admindashboard-section-{$this->value}";
	}
}
