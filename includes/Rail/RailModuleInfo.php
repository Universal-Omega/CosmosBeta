<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

/**
 * Describes a module the rail can show, for the theme designer to offer a control for it.
 */
final readonly class RailModuleInfo {

	public function __construct(
		public string $id,
		private RailModuleOrigin $origin,
		private string $label,
	) {
	}

	/** @return array{id: string, origin: string, label: string} */
	public function toArray(): array {
		return [
			'id' => $this->id,
			'origin' => $this->origin->value,
			'label' => $this->label,
		];
	}
}
