<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Title\Title;
use function in_array;

/**
 * What the theme says about a single rail module. Anything left null follows the wiki configuration.
 */
final readonly class RailRules {

	/**
	 * @param bool|null $enabled
	 * @param RailModuleType|null $type
	 * @param int[]|null $disabledNamespaces
	 * @param string[]|null $disabledPages Full page names, or "mainpage" for the main page
	 */
	public function __construct(
		public ?bool $enabled,
		public ?RailModuleType $type,
		public ?array $disabledNamespaces,
		public ?array $disabledPages,
	) {
	}

	/**
	 * @param array{enabled?: bool, type?: string, disabledNamespaces?: int[], disabledPages?: string[]} $data
	 *   Rules as the theme settings store them
	 */
	public static function newFromArray( array $data ): self {
		return new self(
			$data['enabled'] ?? null,
			isset( $data['type'] ) ? RailModuleType::from( $data['type'] ) : null,
			$data['disabledNamespaces'] ?? null,
			$data['disabledPages'] ?? null
		);
	}

	/**
	 * A list of its own replaces the rail wide one, so an empty list shows the module everywhere.
	 *
	 * @param Title $title
	 * @param int[] $defaultNamespaces Rail wide namespaces without a rail
	 * @param string[] $defaultPages Rail wide pages without a rail
	 */
	public function isShownOn( Title $title, array $defaultNamespaces, array $defaultPages ): bool {
		if ( $this->enabled === false ) {
			return false;
		}

		$pages = $this->disabledPages ?? $defaultPages;
		$isMainPageHidden = $title->isMainPage() && in_array( 'mainpage', $pages, true );
		return !$title->inNamespaces( $this->disabledNamespaces ?? $defaultNamespaces ) &&
			!$isMainPageHidden &&
			!in_array( $title->getFullText(), $pages, true );
	}
}
