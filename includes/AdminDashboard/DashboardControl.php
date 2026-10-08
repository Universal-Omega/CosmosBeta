<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\AdminDashboard;

final readonly class DashboardControl {

	public function __construct(
		public string $id,
		public DashboardSection $section,
		public string $url,
		public ?string $specialPage,
		public bool $opensCreateDialog,
		public bool $isExternal,
	) {
	}

	public function getLabelKey(): string {
		return "cosmos-admindashboard-control-{$this->id}-label";
	}

	public function getDescriptionKey(): string {
		return "cosmos-admindashboard-control-{$this->id}-description";
	}
}
