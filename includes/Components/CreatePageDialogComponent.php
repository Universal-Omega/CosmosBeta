<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Components;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\MainConfigNames;
use MediaWiki\SiteStats\SiteStats;
use MediaWiki\Skins\CosmosBeta\ConfigNames;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\SpecialWantedPages;
use MediaWiki\Title\TitleFactory;
use function count;
use function in_array;
use function preg_match;

class CreatePageDialogComponent {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::EnableWantedPages,
		ConfigNames::FetchWantedPagesFromCache,
		ConfigNames::WantedPagesFetchedNamespaces,
		ConfigNames::WantedPagesMaxTitlesCount,
		MainConfigNames::Script,
	];

	public function __construct(
		private readonly IContextSource $context,
		private readonly ServiceOptions $options,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly TitleFactory $titleFactory,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getTemplateData(): array {
		$wantedPagesEnabled = (bool)$this->options->get( ConfigNames::EnableWantedPages );

		$wantedPagesMessage = $wantedPagesEnabled ?
			$this->context->msg( 'cosmosbeta-createpage-wanted-pages' )->text() :
			$this->context->msg(
				'cosmosbeta-createpage-no-wanted-pages',
				SpecialPage::getTitleFor( 'Wantedpages' )->getPrefixedText()
			)->text();

		return [
			'form-action' => $this->options->get( MainConfigNames::Script ),
			'msg-close' => $this->context->msg( 'cosmosbeta-createpage-close' )->text(),
			'msg-header' => $this->context->msg( 'cosmosbeta-createpage-header' )->text(),
			'msg-label' => $this->context->msg( 'cosmosbeta-createpage-input-label' )->text(),
			'html-text' => $this->context->msg(
				'cosmosbeta-createpage-text',
				$this->context->getLanguage()->formatNum( SiteStats::articles() ),
				$this->context->msg( 'sitetitle' )->text(),
				$wantedPagesMessage
			)->parse(),
			'msg-next' => $this->context->msg( 'cosmosbeta-createpage-next' )->text(),
			'array-proposals' => $wantedPagesEnabled ? $this->getMostWantedPages() : [],
		];
	}

	private function getMostWantedPages(): array {
		$page = $this->specialPageFactory->getPage( 'Wantedpages' );

		if ( !$page instanceof SpecialWantedPages ) {
			return [];
		}

		$rows = $this->options->get( ConfigNames::FetchWantedPagesFromCache ) ?
			$page->fetchFromCache( false ) :
			$page->doQuery();

		$namespaces = $this->options->get( ConfigNames::WantedPagesFetchedNamespaces );
		$max = (int)$this->options->get( ConfigNames::WantedPagesMaxTitlesCount );
		$pages = [];

		foreach ( $rows as $row ) {
			if ( count( $pages ) >= $max ) {
				break;
			}

			if ( !$row->title || !in_array( (int)$row->namespace, $namespaces, true ) ) {
				continue;
			}

			$title = $this->titleFactory->newFromText( $row->title, $row->namespace );

			if ( $title && !$title->isKnown() && !preg_match( '/[:\/]+/', $title->getText() ) ) {
				$pages[] = [
					'title' => $title->getFullText(),
					'url' => $title->getLocalURL( [ 'action' => 'edit', 'source' => 'redlink' ] ),
				];
			}
		}

		return $pages;
	}
}
