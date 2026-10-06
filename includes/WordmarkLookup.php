<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use MediaWiki\FileRepo\File\File;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Title\TitleFactory;
use function preg_match;
use const NS_FILE;

class WordmarkLookup {

	public function __construct(
		private readonly RepoGroup $repoGroup,
		private readonly TitleFactory $titleFactory,
		private readonly string $wordmark,
	) {
	}

	public function getWordmarkUrl(): ?string {
		if ( $this->wordmark === '' ) {
			return null;
		}

		if ( !$this->isWordmarkUrl() ) {
			$file = $this->getWordmarkFile();
			if ( $file && $file->exists() ) {
				return $file->getUrl();
			}
		}

		return $this->wordmark;
	}

	public function isWordmarkUrl(): bool {
		return (bool)preg_match( '%^(?:(http|https|ftp):|)//(?:www\.)?.*$%i', $this->wordmark );
	}

	public function getWordmarkFile(): ?File {
		$title = $this->titleFactory->makeTitle( NS_FILE, $this->wordmark );
		return $this->repoGroup->findFile( $title ) ?: null;
	}
}
