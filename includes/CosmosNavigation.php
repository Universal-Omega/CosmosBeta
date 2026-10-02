<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use MediaWiki\Language\Language;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use MessageLocalizer;
use Wikimedia\ObjectCache\WANObjectCache;
use function count;
use function explode;
use function htmlspecialchars;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_replace;
use function strpos;
use function strrpos;
use function trim;

class CosmosNavigation {

	public const string MESSAGE = 'cosmosbeta-navigation';

	public function __construct(
		private readonly WANObjectCache $cache,
		private readonly Language $contentLanguage,
		private readonly UrlUtils $urlUtils,
		private readonly TitleFactory $titleFactory,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
	}

	public function getTree( MessageLocalizer $localizer, Language $userLanguage ): array {
		$build = fn (): array => $this->buildTree( $localizer, $this->getMenuLines( $localizer ) );

		if ( $userLanguage->getCode() !== $this->contentLanguage->getCode() ) {
			return $build();
		}

		return $this->cache->getWithSetCallback(
			$this->getCacheKey(),
			WANObjectCache::TTL_HOUR * 8,
			$build,
			[ 'version' => 1 ]
		);
	}

	public function purge(): void {
		$this->cache->delete( $this->getCacheKey() );
	}

	public function buildTree( MessageLocalizer $localizer, array $lines ): array {
		$nodes = $this->parse( $localizer, $lines );

		if ( !isset( $nodes[0]['children'] ) ) {
			return [];
		}

		$exploreText = $localizer->msg( 'cosmosbeta-explore' )->text();
		$tree = [];

		foreach ( $nodes[0]['children'] as $index ) {
			$node = $nodes[$index];
			$isExplore = $node['text'] === $exploreText;
			$id = Sanitizer::escapeIdForAttribute( $node['text'] );
			$children = $node['children'] ?? [];

			$tree[] = [
				'id' => $id,
				'text' => $node['text'],
				'href' => $node['href'] !== '' && $node['text'] !== 'Navigation' && !$isExplore ? $node['href'] : '#',
				'rel-nofollow' => !$node['internal'],
				'is-explore' => $isExplore,
				'has-children' => $children !== [],
				'array-children' => $this->buildChildren( $nodes, $children ),
			];
		}

		return $tree;
	}

	private function buildChildren( array $nodes, array $children ): array {
		$items = [];

		foreach ( $children as $position => $index ) {
			$node = $nodes[$index];
			$grandChildren = $node['children'] ?? [];
			$id = Sanitizer::escapeIdForAttribute( $node['text'] );

			$items[] = [
				'id' => $id,
				'text' => $node['text'],
				'href' => $node['href'] !== '' ? $node['href'] : '#',
				'rel-nofollow' => !$node['internal'],
				'has-children' => $grandChildren !== [],
				'is-sticked' => $position > count( $grandChildren ) - 1,
				'array-children' => $this->buildChildren( $nodes, $grandChildren ),
			];
		}

		return $items;
	}

	public function parse( MessageLocalizer $localizer, array $lines ): array {
		$nodes = [];
		$lastDepth = 0;
		$i = 0;

		foreach ( $lines as $line ) {
			if ( trim( str_replace( '*', '', $line ) ) === '' ) {
				continue;
			}

			$node = $this->parseLine( $localizer, $line );
			$node['depth'] = strrpos( $line, '*' ) + 1;

			if ( $node['depth'] === $lastDepth ) {
				$node['parentIndex'] = $nodes[$i]['parentIndex'] ?? 0;
			} elseif ( $node['depth'] === $lastDepth + 1 ) {
				$node['parentIndex'] = $i;
			} else {
				$node['parentIndex'] = 0;

				for ( $x = $i; $x > 0; $x-- ) {
					if ( $nodes[$x]['depth'] === $node['depth'] - 1 ) {
						$node['parentIndex'] = $x;
						break;
					}
				}
			}

			if ( !empty( $node['original'] ) && in_array( $node['original'], [ 'SEARCH', 'TOOLBOX', 'LANGUAGES' ], true ) ) {
				continue;
			}

			$nodes[$i + 1] = $node;
			$nodes[$node['parentIndex']]['children'][] = $i + 1;
			$lastDepth = $node['depth'];
			$i++;
		}

		return $nodes;
	}

	public function parseLine( MessageLocalizer $localizer, string $line ): array {
		$parts = explode( '|', trim( $line, '* ' ), 2 );
		$parts[0] = trim( $parts[0], '[]' );
		$internal = false;

		if ( count( $parts ) === 2 && $parts[1] !== '' ) {
			$link = trim( $localizer->msg( trim( $parts[0] ) )->inContentLanguage()->text() );
			$label = trim( $parts[1] );
		} else {
			$link = $label = trim( $parts[0] );
		}

		$labelMessage = $localizer->msg( $label );
		$text = $labelMessage->exists() ? $labelMessage->text() : $label;

		if ( !$localizer->msg( trim( $parts[0] ) )->exists() ) {
			$link = $parts[0];
		}

		if ( preg_match( '/^(?:' . $this->urlUtils->validProtocols() . ')/', $link ) ) {
			$href = $link;
		} elseif ( $link === '' || $link[0] === '#' ) {
			$href = '#';
		} else {
			$title = $this->titleFactory->newFromText( $link );
			$href = $title ? $title->fixSpecialName()->getLocalURL() : '#';
			$internal = $title !== null;
		}

		return [
			'original' => $parts[0],
			'text' => $text,
			'href' => $href,
			'internal' => $internal,
		];
	}

	private function getMenuLines( MessageLocalizer $localizer ): array {
		return $this->extract( $localizer->msg( self::MESSAGE )->inContentLanguage()->text() );
	}

	public function extract( string $navigation ): array {
		$exploreChildUrl = null;
		$exploreChildText = null;
		$forceChildUrl = null;
		$forceChildText = null;

		if (
			$this->extensionRegistry->isLoaded( 'Video' ) &&
			( strpos( $navigation, '{$NEWVIDEOS_CONDITIONAL}' ) !== false || strpos( $navigation, '{$NEWVIDEOS}' ) !== false )
		) {
			$exploreChildUrl = '**' . htmlspecialchars( (string)SpecialPage::getTitleFor( 'NewVideos' ) ) . '|';
			$exploreChildText = 'newvideos';

			if ( strpos( $navigation, '{$WANTEDPAGES_FORCE}' ) !== false ) {
				$forceChildUrl = "\n**" . htmlspecialchars( (string)SpecialPage::getTitleFor( 'Wantedpages' ) ) . '|';
				$forceChildText = 'wantedpages';
			}
		} elseif (
			strpos( $navigation, '{$WANTEDPAGES_CONDITIONAL}' ) !== false ||
			strpos( $navigation, '{$WANTEDPAGES}' ) !== false
		) {
			$exploreChildUrl = '**' . htmlspecialchars( (string)SpecialPage::getTitleFor( 'Wantedpages' ) ) . '|';
			$exploreChildText = 'wantedpages';
		}

		$cleaned = preg_replace(
			'/(\{\$NEWVIDEOS\})|(\{\$WANTEDPAGES\})|(\{\$NEWVIDEOS_CONDITIONAL\})' .
				'|(\{\$WANTEDPAGES_CONDITIONAL\})|(\{\$WANTEDPAGES_FORCE\})/',
			'',
			$navigation
		);

		$message = trim( $cleaned . $exploreChildUrl . $exploreChildText . $forceChildUrl . $forceChildText );

		return $message !== '' && $message !== '-' ? explode( "\n", $message ) : [];
	}

	private function getCacheKey(): string {
		return $this->cache->makeKey( 'CosmosBeta', 'navigation', 'tree' );
	}
}
