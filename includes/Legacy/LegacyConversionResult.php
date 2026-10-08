<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Legacy;

use function array_sum;

final readonly class LegacyConversionResult {

	/**
	 * @param string $output
	 * @param array<string,int> $replacements Old class name to the number of times it was replaced.
	 * @param array<string,string> $mappedTo Old class name to the new class name.
	 * @param list<string> $iconClasses Old icon classes that were found and left untouched.
	 */
	public function __construct(
		public string $output,
		public array $replacements,
		public array $mappedTo,
		public array $iconClasses,
	) {
	}

	public function getTotal(): int {
		return array_sum( $this->replacements );
	}
}
