<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Components;

use MediaWiki\Context\IContextSource;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Title\TitleFactory;
use function array_slice;
use function count;
use function implode;
use function str_contains;
use function str_starts_with;
use const CONTENT_MODEL_WIKITEXT;
use const NS_CATEGORY;

class PageHeaderComponent {

	private const int VISIBLE_CATEGORIES = 3;

	public function __construct(
		private readonly IContextSource $context,
		private readonly TitleFactory $titleFactory,
		private readonly LanguageNameUtils $languageNameUtils,
		private readonly Language $contentLanguage,
	) {
	}

	public function getTemplateData( array $portlets ): array {
		return [
			'data-categories' => $this->getCategories(),
			'data-interlang' => $this->getInterlang( $portlets ),
			'data-actions' => $this->getActions( $portlets ),
		];
	}

	private function getCategories(): ?array {
		$out = $this->context->getOutput();
		$titles = [];

		foreach ( $out->getCategories( 'normal' ) as $name ) {
			$title = $this->titleFactory->newFromText( $name, NS_CATEGORY );
			if ( $title ) {
				$titles[] = [
					'text' => $title->getText(),
					'href' => $title->getLocalURL(),
				];
			}
		}

		$isMainPage = $this->context->canUseWikiPage() && $this->context->getWikiPage()->getTitle()->isMainPage();
		if ( !$titles || $isMainPage ) {
			return null;
		}

		$hasMore = count( $titles ) > 4;
		$visible = $hasMore ? array_slice( $titles, 0, self::VISIBLE_CATEGORIES ) : $titles;
		$more = $hasMore ? array_slice( $titles, self::VISIBLE_CATEGORIES ) : [];

		foreach ( $visible as $index => &$category ) {
			$category['id'] = "categories-top-$index";
			$category['has-next'] = $index < count( $visible ) - 1;
		}
		unset( $category );

		foreach ( $more as $index => &$category ) {
			$category['id'] = "categories-top-more-$index";
		}
		unset( $category );

		return [
			'msg-in' => $this->context->msg( 'cosmosbeta-article-header-categories-in' )->text(),
			'array-links' => $visible,
			'has-more' => $hasMore,
			'msg-more-separator' => $this->context->msg( 'cosmosbeta-article-header-categories-more-separator' )->text(),
			'msg-more' => $this->context->msg( 'cosmosbeta-article-header-categories-more' )
				->numParams( count( $more ) )->text(),
			'array-more-links' => $more,
		];
	}

	private function getInterlang( array $portlets ): ?array {
		$variants = PortletReader::getItems( $portlets, [ 'data-variants' ] );
		$languages = PortletReader::getItems( $portlets, [ 'data-languages' ] );

		if ( !$variants && !$languages ) {
			return null;
		}

		$data = [];

		if ( $variants ) {
			$label = $this->context->msg( 'variants' )->text();
			foreach ( $variants as $variant ) {
				if ( $this->hasClass( $variant['class'], 'selected' ) ) {
					$label = $variant['text'];
					break;
				}
			}

			$data['data-variants'] = [
				'label' => $label,
				'array-items' => $this->toLinks( $variants ),
			];
		}

		if ( $languages ) {
			$title = $this->context->getTitle();
			$code = $title->getPageLanguage()->getCode();

			if ( $title->isSpecialPage() || !$title->hasContentModel( CONTENT_MODEL_WIKITEXT ) ) {
				$code = $this->contentLanguage->getCode();
			}

			$data['data-languages'] = [
				'label' => $this->languageNameUtils->getLanguageName( $code ),
				'array-items' => $this->toLinks( $languages ),
			];
		}

		return $data;
	}

	private function toLinks( array $items ): array {
		$links = [];

		foreach ( $items as $item ) {
			$links[] = [
				'id' => $item['id'],
				'class' => $item['class'],
				'href' => $item['href'],
				'lang' => $item['lang'],
				'hreflang' => $item['hreflang'],
				'title' => $item['title'],
				'text' => $item['text'],
			];
		}

		return $links;
	}

	private function getActions( array $portlets ): array {
		$items = PortletReader::getItems( $portlets, [ 'data-associated-pages', 'data-views', 'data-actions' ] );
		$title = $this->context->getTitle();

		$edit = $talk = $view = null;
		$dropdown = [];
		$isEditing = $isViewSource = $isHistory = $isSpecialAction = false;

		foreach ( $items as $key => $item ) {
			$selected = $this->hasClass( $item['class'], 'selected' );

			switch ( $key ) {
				case 'edit':
					$edit = $item + [ 'icon' => 'edit' ];
					$isEditing = $selected;
					break;
				case 'viewsource':
					$edit = $item + [ 'icon' => 'eye' ];
					$isViewSource = $selected;
					break;
				case 'talk':
					$talk = $item + [ 'icon' => 'speechBubble' ];
					break;
				case 'view':
					break;
				default:
					if ( $key === 'addsection' ) {
						$item['text'] = $this->context->msg( 'cosmosbeta-action-addsection' )->text();
					}

					if ( str_starts_with( $key, 'nstab-' ) ) {
						$view = $item;
					} elseif ( !str_starts_with( $key, 'varlang-' ) ) {
						if ( !$selected ) {
							$dropdown[$key] = $item;
						} elseif ( $key === 'history' ) {
							$isHistory = true;
						} else {
							$isSpecialAction = true;
						}
					}
			}
		}

		$isTalkPage = $title->isTalkPage();
		$talkUrl = $title->getTalkPageIfDefined()?->getLinkURL();
		$pageUrl = $title->getLinkURL();
		$backToPage = $view ? $this->context->msg( 'cosmosbeta-action-backtopage', $view['text'] )->text() : '';
		$primary = $secondary = null;

		if ( $isEditing || $isSpecialAction ) {
			if ( $isTalkPage ) {
				$primary = $talk ? [
					'icon' => 'close',
					'text' => $this->context->msg( 'cosmosbeta-action-cancel' )->text(),
					'href' => $talkUrl ?? $talk['href'],
				] + $talk : null;
				$secondary = $view ? [ 'icon' => 'undo', 'text' => $backToPage ] + $view : null;
			} else {
				$primary = $view ? [
					'icon' => 'close',
					'text' => $this->context->msg( 'cosmosbeta-action-cancel' )->text(),
					'href' => $pageUrl,
				] + $view : null;
				$secondary = $talk ? [ 'icon' => 'speechBubble' ] + $talk : null;
			}

			if ( !$isEditing && $edit ) {
				$dropdown = [ 'edit' => $edit ] + $dropdown;
			}
		} elseif ( $isHistory || $isViewSource ) {
			if ( $isTalkPage ) {
				$primary = $talk ? [
					'icon' => 'undo',
					'text' => $this->context->msg( 'cosmosbeta-action-back' )->text(),
				] + $talk : null;
				$secondary = $view ? [ 'icon' => 'undo', 'text' => $backToPage ] + $view : null;
			} else {
				$primary = $view ? [
					'icon' => 'undo',
					'text' => $this->context->msg( 'cosmosbeta-action-back' )->text(),
				] + $view : null;
				$secondary = $talk;
			}

			if ( !$isViewSource && $edit ) {
				$dropdown = [ 'edit' => $edit ] + $dropdown;
			}
		} elseif ( $isTalkPage ) {
			$primary = $edit;
			$secondary = $view ? [ 'icon' => 'undo', 'text' => $backToPage ] + $view : null;
		} else {
			$primary = $edit;
			$secondary = $view ? $talk : null;
		}

		return [
			'data-primary' => $this->toButton( $primary, 'primary' ),
			'data-secondary' => $this->toButton( $secondary, 'secondary' ),
			'has-dropdown' => $dropdown !== [],
			'array-dropdown-items' => $this->toDropdown( $dropdown ),
			'is-view' => $view !== null,
		];
	}

	private function toButton( ?array $item, string $variant ): ?array {
		if ( !$item ) {
			return null;
		}

		$classes = [
			$item['class'],
			"skin-cosmos-button skin-cosmos-button-$variant skin-cosmos-button-action",
			"cosmos-button cosmos-button-$variant cosmos-button-action",
		];
		$id = $item['id'] ?? '';

		if ( str_starts_with( $id, 'ca-nstab-' ) ) {
			$classes[] = 'skin-cosmos-actions-view cosmos-actions-view';
		} elseif ( $id === 'ca-talk' ) {
			$classes[] = 'skin-cosmos-actions-talk cosmos-actions-talk';
		} else {
			$classes[] = 'skin-cosmos-actions-edit cosmos-actions-edit';
		}

		return [
			'id' => $item['id'] ?? null,
			'class' => implode( ' ', $classes ),
			'href' => $item['href'] ?? null,
			'title' => $item['title'] ?? '',
			'icon' => $item['icon'] ?? null,
			'text' => $item['text'],
		];
	}

	private function toDropdown( array $items ): array {
		$list = [];

		foreach ( $items as $item ) {
			$list[] = [ 'html-item' => $item['html-item'] ];
		}

		return $list;
	}

	private function hasClass( string $classes, string $class ): bool {
		return str_contains( $classes, $class );
	}
}
