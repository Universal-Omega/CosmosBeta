<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\RenameUser\Hook\RenameUserCompleteHook;
use MediaWiki\RevisionDelete\Hook\ArticleRevisionVisibilitySetHook;
use MediaWiki\Skin\Cosmos\Rail\RailBuilder;
use MediaWiki\Specials\Hook\BlockIpCompleteHook;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * The rail caches who made the latest changes. Anything that changes who may be shown there empties it.
 */
class RailCache implements
	ArticleRevisionVisibilitySetHook,
	BlockIpCompleteHook,
	RenameUserCompleteHook
{

	public function __construct(
		private readonly WANObjectCache $cache,
	) {
	}

	/** @inheritDoc */
	public function onArticleRevisionVisibilitySet( $title, $ids, $visibilityChangeMap ): void {
		$this->purge();
	}

	/** @inheritDoc */
	public function onBlockIpComplete( $block, $user, $priorBlock ): void {
		if ( $block->getHideName() ) {
			$this->purge();
		}
	}

	/** @inheritDoc */
	public function onRenameUserComplete( int $uid, string $old, string $new ): void {
		$this->purge();
	}

	private function purge(): void {
		$this->cache->delete( RailBuilder::getRecentChangesCacheKey( $this->cache ) );
	}
}
