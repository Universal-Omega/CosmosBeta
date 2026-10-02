<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use MediaWiki\FileRepo\File\File;
use MediaWiki\FileRepo\RepoGroup;
use MediaWiki\Title\TitleFactory;
use function preg_match;
use const NS_FILE;

class CosmosWordmarkLookup {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly RepoGroup $repoGroup,
		private readonly CosmosConfig $cosmosConfig,
	) {
	}

	public function getWordmarkUrl(): ?string {
		if ( $this->cosmosConfig->getWordmark() === '' ) {
			return null;
		}

		if ( !$this->isWordmarkUrl() ) {
			$file = $this->getWordmarkFile();
			if ( $file && $file->exists() ) {
				return $file->getUrl();
			}
		}

		return $this->cosmosConfig->getWordmark();
	}

	public function isWordmarkUrl(): bool {
		return (bool)preg_match( '%^(?:(http|https|ftp):|)//(?:www\.)?.*$%i', $this->cosmosConfig->getWordmark() );
	}

	public function getWordmarkFile(): ?File {
		$title = $this->titleFactory->makeTitle( NS_FILE, $this->cosmosConfig->getWordmark() );
		return $this->repoGroup->findFile( $title ) ?: null;
	}
}
