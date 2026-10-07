<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Skin\Cosmos\ConfigNames;
use function array_map;
use function array_merge;
use function in_array;
use function preg_replace;
use function strtolower;
use function strtoupper;
use function trim;

class SidebarModuleFactory {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::RailSidebarPortlets,
	];

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public static function getModuleId( string $name ): string {
		return 'sidebar-' . trim( (string)preg_replace( '/[^a-z0-9._-]+/', '-', strtolower( $name ) ), '-' );
	}

	/**
	 * Stands in for the modules before the page is built, so the styles load only when there is a rail.
	 *
	 * @param array<string, array> $sections The sidebar as core builds it, by section name
	 * @return RailModule[]
	 */
	public function newPlaceholders( array $sections ): array {
		$modules = [];
		foreach ( $sections as $name => $items ) {
			if ( $items && in_array( strtoupper( (string)$name ), $this->getNames(), true ) ) {
				$modules[] = $this->newModule( (string)$name, (string)$name, [] );
			}
		}

		return $modules;
	}

	/**
	 * @param array $sidebar The sidebar template data of the skin
	 * @return RailModule[]
	 */
	public function newFromPortlets( array $sidebar ): array {
		$portlets = array_merge( [ $sidebar['data-portlets-first'] ?? null ], $sidebar['array-portlets-rest'] ?? [] );
		$modules = [];

		foreach ( $portlets as $portlet ) {
			$name = $portlet ? $this->findName( $portlet ) : null;
			$items = array_map(
				static fn ( array $item ): array => [ 'html-item' => $item['html-item'] ?? '' ],
				$portlet['array-items'] ?? []
			);

			if ( $name !== null && $items !== [] ) {
				$modules[] = $this->newModule( $name, (string)( $portlet['label'] ?? '' ), $items );
			}
		}

		return $modules;
	}

	/**
	 * @return string[] The sections to show in the rail, in capitals
	 */
	private function getNames(): array {
		return array_map( strtoupper( ... ), (array)$this->options->get( ConfigNames::RailSidebarPortlets ) );
	}

	/**
	 * Sections are told apart by the id of the portlet, or by its label when the id is not one of them.
	 */
	private function findName( array $portlet ): ?string {
		$id = strtoupper( (string)preg_replace( '/^p-/i', '', (string)( $portlet['id'] ?? '' ) ) );
		$label = strtoupper( trim( (string)( $portlet['label'] ?? '' ) ) );

		return match ( true ) {
			in_array( $id, $this->getNames(), true ) => $id,
			in_array( $label, $this->getNames(), true ) => $label,
			default => null,
		};
	}

	/**
	 * @param array<int, array{html-item: string}> $items
	 */
	private function newModule( string $name, string $label, array $items ): RailModule {
		return RailModule::newWithTools(
			self::getModuleId( $name ),
			RailModuleType::Normal,
			'sidebar-module',
			$label,
			$items
		);
	}
}
