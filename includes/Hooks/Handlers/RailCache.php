<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\Block\DatabaseBlock;
use MediaWiki\RenameUser\Hook\RenameUserCompleteHook;
use MediaWiki\RevisionDelete\Hook\ArticleRevisionVisibilitySetHook;
use MediaWiki\Skin\Cosmos\Rail\RailBuilder;
use MediaWiki\Specials\Hook\BlockIpCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
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

	/**
	 * @inheritDoc
	 * @param Title $title @phan-unused-param
	 * @param int[] $ids @phan-unused-param
	 * @param array<int, array{oldBits: int, newBits: int}> $visibilityChangeMap @phan-unused-param
	 */
	public function onArticleRevisionVisibilitySet( $title, $ids, $visibilityChangeMap ): void {
		$this->purge();
	}

	/**
	 * @inheritDoc
	 * @param User $user @phan-unused-param
	 * @param ?DatabaseBlock $priorBlock @phan-unused-param
	 */
	public function onBlockIpComplete( $block, $user, $priorBlock ): void {
		if ( $block->getHideName() ) {
			$this->purge();
		}
	}

	/**
	 * @inheritDoc
	 * @param int $uid @phan-unused-param
	 * @param string $old @phan-unused-param
	 * @param string $new @phan-unused-param
	 */
	public function onRenameUserComplete( int $uid, string $old, string $new ): void {
		$this->purge();
	}

	private function purge(): void {
		$this->cache->delete( RailBuilder::getRecentChangesCacheKey( $this->cache ) );
	}
}
