<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\AlternateEditPreviewHook;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Language\Hook\MessageCacheReplaceHook;
use MediaWiki\Skins\CosmosBeta\CosmosNavigation;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use function trim;
use const NS_MEDIAWIKI;

class Navigation implements
	AlternateEditPreviewHook,
	BeforeInitializeHook,
	MessageCacheReplaceHook
{

	public function __construct(
		private readonly CosmosNavigation $navigation,
		private readonly TemplateParser $templateParser,
		private readonly TitleFactory $titleFactory,
	) {
	}

	/** @inheritDoc */
	public function onAlternateEditPreview( $editPage, &$content, &$previewHTML, &$parserOutput ): bool {
		$context = $editPage->getContext();

		if (
			!$context->getSkin() instanceof SkinCosmosBeta ||
			!$content instanceof WikitextContent ||
			!$this->isNavigationPage( $editPage->getTitle() )
		) {
			return true;
		}

		$pageText = trim( $content->getText() );

		if ( $pageText === '' || $pageText === '-' ) {
			return true;
		}

		$context->getOutput()->enableOOUI();
		$previewHTML = $this->buildPreview( $context, $editPage->isConflict, $pageText );

		return false;
	}

	/** @inheritDoc */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ): void {
		if ( $output->getSkin() instanceof SkinCosmosBeta && $this->isNavigationPage( $title ) ) {
			$request->setVal( 'wteswitched', '1' );
		}
	}

	/** @inheritDoc */
	public function onMessageCacheReplace( $title, $text ): void {
		$this->navigation->purge();
	}

	private function isNavigationPage( Title $title ): bool {
		return $title->equals(
			$this->titleFactory->newFromText( CosmosNavigation::MESSAGE, NS_MEDIAWIKI )
		);
	}

	private function buildPreview( IContextSource $context, bool $isConflict, string $pageText ): string {
		$out = $context->getOutput();

		$conflict = $isConflict ?
			Html::warningBox( $context->msg( 'previewconflict' )->escaped(), 'mw-previewconflict' ) :
			'';

		$note = $context->msg( 'previewnote' )->plain() .
			' <span class="mw-continue-editing">' .
			'[[#editform|' .
			$context->getLanguage()->getArrow() . ' ' .
			$context->msg( 'continue-editing' )->text() . ']]</span>';

		$tree = $this->navigation->buildTree( $context, $this->navigation->extract( $pageText ) );

		$menu = $this->templateParser->processTemplate( 'Navigation', [ 'data-cosmos-navigation' => $tree ] );

		return Html::rawElement( 'div', [ 'class' => 'previewnote' ],
			Html::rawElement( 'h2', [ 'id' => 'mw-previewheader' ], $context->msg( 'preview' )->escaped() ) .
			Html::warningBox( $out->parseAsInterface( $note ) ) . $conflict
		) . Html::rawElement( 'header', [ 'class' => 'skin-cosmos-header cosmos-header' ],
			Html::rawElement(
				'nav',
				[ 'class' => 'skin-cosmos-header__local-navigation cosmos-header__local-navigation skin-cosmos-navigation-preview navigation-preview' ],
				$menu
			)
		);
	}
}
