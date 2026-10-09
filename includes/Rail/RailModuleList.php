<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use function array_keys;
use function array_values;

/**
 * The modules of the rail in the order they show. Extensions get this in the CosmosRailBuilder hook.
 */
class RailModuleList {

	/** @var array<string, RailModule> */
	private array $modules = [];

	/**
	 * A module with the same id as an earlier one replaces it and keeps its place.
	 */
	public function add( RailModule $module ): void {
		$this->modules[$module->id] = $module;
	}

	public function remove( string $id ): void {
		unset( $this->modules[$id] );
	}

	public function has( string $id ): bool {
		return isset( $this->modules[$id] );
	}

	public function get( string $id ): ?RailModule {
		return $this->modules[$id] ?? null;
	}

	/** @return list<string> */
	public function getIds(): array {
		return array_keys( $this->modules );
	}

	/** @return list<RailModule> */
	public function getAll(): array {
		return array_values( $this->modules );
	}
}
