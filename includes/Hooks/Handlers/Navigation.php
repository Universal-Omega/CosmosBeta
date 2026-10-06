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
use MediaWiki\Language\RawMessage;
use MediaWiki\Skins\CosmosBeta\CosmosNavigation;
use MediaWiki\Skins\CosmosBeta\SkinCosmos;
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
			!$context->getSkin() instanceof SkinCosmos ||
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
		if ( $output->getSkin() instanceof SkinCosmos && $this->isNavigationPage( $title ) ) {
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
		$note = $context->msg( 'previewnote' )->plain() .
			' <span class="mw-continue-editing">' .
			'[[#editform|' .
			$context->getLanguage()->getArrow() . ' ' .
			$context->msg( 'continue-editing' )->text() . ']]</span>';

		$lines = $this->navigation->extract(
			( new RawMessage( $pageText ) )->setContext( $context )->inContentLanguage()->text()
		);

		return $this->templateParser->processTemplate( 'NavigationPreview', [
			'preview' => $context->msg( 'preview' )->text(),
			'note' => Html::warningBox( $context->getOutput()->parseAsInterface( $note ) ),
			'conflict' => $isConflict ?
				Html::warningBox( $context->msg( 'previewconflict' )->escaped(), 'mw-previewconflict' ) :
				'',
			'data-cosmos-navigation' => $this->navigation->buildTree( $context, $lines ),
		] );
	}
}
