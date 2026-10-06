<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\AdminDashboard;

final readonly class DashboardControl {

	public function __construct(
		public string $id,
		public DashboardSection $section,
		public string $url,
		public ?string $specialPage = null,
		public bool $opensCreateDialog = false,
		public bool $isExternal = false,
	) {
	}

	public function getLabelKey(): string {
		return "cosmosbeta-admindashboard-control-{$this->id}-label";
	}

	public function getDescriptionKey(): string {
		return "cosmosbeta-admindashboard-control-{$this->id}-description";
	}
}
