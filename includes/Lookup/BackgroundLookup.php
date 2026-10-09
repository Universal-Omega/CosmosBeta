<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Lookup;

use MediaWiki\FileRepo\File\File;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Title\TitleFactory;
use function preg_match;
use const NS_FILE;

class BackgroundLookup {

	public function __construct(
		private readonly RepoGroup $repoGroup,
		private readonly TitleFactory $titleFactory,
		private readonly string $main,
		private readonly string $wikiHeader,
	) {
	}

	public function getMainBackgroundUrl(): ?string {
		return $this->resolve( $this->main );
	}

	public function getWikiHeaderBackgroundUrl(): ?string {
		return $this->resolve( $this->wikiHeader );
	}

	private function isBackgroundUrl( string $background ): bool {
		return (bool)preg_match( '%^(?:(http|https|ftp):|)//(?:www\.)?.*$%i', $background );
	}

	private function getBackgroundFile( string $background ): ?File {
		$title = $this->titleFactory->makeTitle( NS_FILE, $background );
		return $this->repoGroup->findFile( $title ) ?: null;
	}

	private function resolve( string $background ): ?string {
		if ( $background === '' ) {
			return null;
		}

		if ( !$this->isBackgroundUrl( $background ) ) {
			$file = $this->getBackgroundFile( $background );
			if ( $file && $file->exists() ) {
				return $file->getUrl();
			}
		}

		return $background;
	}
}
