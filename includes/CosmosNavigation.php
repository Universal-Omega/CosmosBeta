<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Language\Language;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use Wikimedia\ObjectCache\WANObjectCache;
use function array_map;
use function array_merge;
use function count;
use function explode;
use function htmlspecialchars;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_replace;
use function strrpos;
use function strtoupper;
use function trim;

class CosmosNavigation {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::RailSidebarPortlets,
	];

	public const string MESSAGE = 'cosmosbeta-navigation';

	private const string EXPLORE_ICON = 'globe';
	private const string ICON_PATTERN = '/\s*\{icon\s*=\s*([A-Za-z0-9-]+)\s*\}/';

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly LanguageCode $contentLanguageCode,
		private readonly TitleFactory $titleFactory,
		private readonly UrlUtils $urlUtils,
		private readonly WANObjectCache $cache,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getTree( MessageLocalizer $localizer, Language $userLanguage ): array {
		$build = fn (): array => $this->buildTree( $localizer, $this->getMenuLines( $localizer ) );
		if ( $userLanguage->getCode() !== $this->contentLanguageCode->toString() ) {
			return $build();
		}

		return $this->cache->getWithSetCallback(
			$this->getCacheKey(),
			WANObjectCache::TTL_HOUR * 8,
			$build,
			[ 'version' => 1 ]
		);
	}

	/**
	 * Adds links that hooks put into the sidebar, such as the ones from extensions, to the tree.
	 * Links that are already in the tree are left out, and sections that are shown elsewhere are skipped.
	 *
	 * @param array[] $portlets Sidebar portlets from the skin template data
	 */
	public function mergeSidebar( array $tree, array $portlets ): array {
		$skipIds = [ 'P-SEARCH', 'P-TB', 'P-LANG', 'SEARCH', 'TB', 'LANG' ];
		$railNames = array_map( strtoupper( ... ), (array)$this->options->get( ConfigNames::RailSidebarPortlets ) );
		$known = [];

		$collect = static function ( array $nodes ) use ( &$collect, &$known ): void {
			foreach ( $nodes as $node ) {
				$known[(string)$node['href']] = true;
				$collect( $node['array-children'] );
			}
		};
		$collect( $tree );

		foreach ( $portlets as $portlet ) {
			$id = strtoupper( (string)( $portlet['id'] ?? '' ) );
			$label = trim( (string)( $portlet['label'] ?? '' ) );
			$bare = (string)preg_replace( '/^P-/', '', $id );

			if (
				in_array( $id, $skipIds, true ) ||
				in_array( $bare, $railNames, true ) ||
				in_array( strtoupper( $label ), $railNames, true )
			) {
				continue;
			}

			$target = null;
			foreach ( $tree as $index => $node ) {
				if ( strtoupper( $node['text'] ) === strtoupper( $label ) ) {
					$target = $index;
					break;
				}
			}

			$texts = [];
			foreach ( $target !== null ? $tree[$target]['array-children'] : [] as $child ) {
				$texts[strtoupper( trim( (string)$child['text'] ) )] = true;
			}

			$children = [];
			foreach ( $portlet['array-items'] ?? [] as $item ) {
				$link = $item['array-links'][0] ?? [];
				$href = '';
				foreach ( $link['array-attributes'] ?? [] as $attribute ) {
					if ( $attribute['key'] === 'href' ) {
						$href = (string)$attribute['value'];
					}
				}

				$text = (string)( $link['text'] ?? '' );
				$textKey = strtoupper( trim( $text ) );
				if ( $href === '' || isset( $known[$href] ) || isset( $texts[$textKey] ) ) {
					continue;
				}

				$known[$href] = true;
				$texts[$textKey] = true;
				$children[] = [
					'id' => Sanitizer::escapeIdForAttribute( $text ),
					'text' => $text,
					'href' => $href,
					'rel-nofollow' => false,
					'icon' => false,
					'has-children' => false,
					'is-sticked' => true,
					'array-children' => [],
				];
			}

			if ( !$children ) {
				continue;
			}

			if ( $target !== null ) {
				$tree[$target]['array-children'] = array_merge( $tree[$target]['array-children'], $children );
				$tree[$target]['has-children'] = true;
			} else {
				$tree[] = [
					'id' => Sanitizer::escapeIdForAttribute( $label ),
					'text' => $label,
					'href' => '#',
					'rel-nofollow' => false,
					'is-explore' => false,
					'icon' => false,
					'has-children' => true,
					'array-children' => $children,
				];
			}
		}

		return $tree;
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
				'icon' => $node['icon'] ?? ( $isExplore ? self::EXPLORE_ICON : false ),
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
				'icon' => $node['icon'] ?? false,
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

			if ( !empty( $node['original'] ) && $this->isSkippedLine( (string)$node['original'] ) ) {
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
		$icon = null;
		if ( preg_match( self::ICON_PATTERN, $line, $matches ) ) {
			$icon = in_array( $matches[1], $this->getAllowedIcons(), true ) ? $matches[1] : null;
			$line = (string)preg_replace( self::ICON_PATTERN, '', $line, 1 );
		}

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
			'icon' => $icon,
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
			( str_contains( $navigation, '{$NEWVIDEOS_CONDITIONAL}' ) || str_contains( $navigation, '{$NEWVIDEOS}' ) )
		) {
			$exploreChildUrl = '**' . htmlspecialchars( (string)SpecialPage::getTitleFor( 'NewVideos' ) ) . '|';
			$exploreChildText = 'newvideos';
			if ( str_contains( $navigation, '{$WANTEDPAGES_FORCE}' ) ) {
				$forceChildUrl = "\n**" . htmlspecialchars( (string)SpecialPage::getTitleFor( 'Wantedpages' ) ) . '|';
				$forceChildText = 'wantedpages';
			}
		} elseif (
			str_contains( $navigation, '{$WANTEDPAGES_CONDITIONAL}' ) ||
			str_contains( $navigation, '{$WANTEDPAGES}' )
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

	/**
	 * @return string[] Icons that the skin icon module provides
	 */
	public function getAllowedIcons(): array {
		$modules = $this->extensionRegistry->getAttribute( 'ResourceModules' );
		return $modules['skins.cosmosbeta.icons']['icons'] ?? [];
	}

	private function isSkippedLine( string $name ): bool {
		if ( in_array( $name, [ 'SEARCH', 'TOOLBOX', 'LANGUAGES' ], true ) ) {
			return true;
		}

		return in_array( strtoupper( $name ), array_map( strtoupper( ... ), (array)$this->options->get( ConfigNames::RailSidebarPortlets ) ), true );
	}

	private function getCacheKey(): string {
		return $this->cache->makeKey( 'Cosmos', 'navigation', 'tree' );
	}
}
