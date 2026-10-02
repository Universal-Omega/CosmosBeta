<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Theme;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skins\CosmosBeta\CosmosResourceLoaderModule;
use function str_starts_with;

/**
 * Every theme dependent stylesheet module gets a twin compiled for the other color mode.
 */
class AltModules {

	public const string SUFFIX = '.alt';

	private ?array $definitions = null;

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
	) {
	}

	/** @return array<string,array> Twin module name to its definition */
	public function getDefinitions(): array {
		if ( $this->definitions === null ) {
			$this->definitions = [];

			foreach ( $this->extensionRegistry->getAttribute( 'ResourceModules' ) as $name => $definition ) {
				if (
					str_starts_with( $name, 'skins.cosmosbeta.' ) &&
					( $definition['factory'] ?? null ) === CosmosResourceLoaderModule::class . '::create'
				) {
					$this->definitions[$name . self::SUFFIX] = [ 'variant' => 'alt' ] + $definition;
				}
			}
		}

		return $this->definitions;
	}

	public function getTwinName( string $name ): ?string {
		$twin = $name . self::SUFFIX;

		return isset( $this->getDefinitions()[$twin] ) ? $twin : null;
	}
}
