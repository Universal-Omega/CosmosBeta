<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Lookup;

use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Title\TitleFactory;
use const NS_FILE;

class FontLookup {

	public function __construct(
		private readonly RepoGroup $repoGroup,
		private readonly TitleFactory $titleFactory,
	) {
	}

	public function getUrl( string $name ): ?string {
		if ( $name === '' ) {
			return null;
		}

		$file = $this->repoGroup->findFile( $this->titleFactory->makeTitle( NS_FILE, $name ) );
		return $file && $file->exists() ? $file->getUrl() : null;
	}
}
