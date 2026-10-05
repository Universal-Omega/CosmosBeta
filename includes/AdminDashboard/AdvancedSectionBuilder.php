<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\AdminDashboard;

use MediaWiki\Context\IContextSource;
use MediaWiki\SpecialPage\SpecialPageFactory;
use function array_values;
use function in_array;
use function strnatcasecmp;
use function uasort;
use function uksort;

final readonly class AdvancedSectionBuilder {

	private const string LAST_GROUP = 'other';

	public function __construct(
		private SpecialPageFactory $specialPageFactory,
	) {
	}

	/**
	 * @param string[] $excluded Canonical names of special pages shown on the general tab
	 * @return array<string, list<array{url: string, text: string, isRestricted: bool}>> Pages by group name
	 */
	public function build( IContextSource $context, array $excluded ): array {
		$groups = [];

		foreach ( $this->specialPageFactory->getUsablePages( $context->getUser(), $context ) as $name => $page ) {
			if ( in_array( $name, $excluded, true ) ) {
				continue;
			}

			$groups[$page->getFinalGroupName()][] = [
				'url' => $page->getPageTitle()->getLocalURL(),
				'text' => $page->getDescription()->text(),
				'isRestricted' => $page->isRestricted(),
			];
		}

		foreach ( $groups as &$pages ) {
			uasort( $pages, static fn ( array $a, array $b ): int => strnatcasecmp( $a['text'], $b['text'] ) );
			$pages = array_values( $pages );
		}
		unset( $pages );

		uksort( $groups, static fn ( string $a, string $b ): int => match ( true ) {
			$a === $b => 0,
			$a === self::LAST_GROUP => 1,
			$b === self::LAST_GROUP => -1,
			default => strnatcasecmp( $a, $b ),
		} );

		return $groups;
	}
}
