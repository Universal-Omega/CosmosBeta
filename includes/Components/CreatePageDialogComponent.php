<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Components;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\MainConfigNames;
use MediaWiki\SiteStats\SiteStats;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\SpecialWantedPages;
use MediaWiki\Title\TitleFactory;
use function count;
use function in_array;
use function preg_match;

class CreatePageDialogComponent {

	public function __construct(
		private readonly IContextSource $context,
		private readonly Config $config,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly TitleFactory $titleFactory,
	) {
	}

	public function getTemplateData(): array {
		$wantedPagesEnabled = (bool)$this->config->get( ConfigNames::EnableWantedPages );
		$wantedPagesMessage = $wantedPagesEnabled ?
			$this->context->msg( 'cosmos-createpage-wanted-pages' )->text() :
			$this->context->msg(
				'cosmos-createpage-no-wanted-pages',
				SpecialPage::getTitleFor( 'Wantedpages' )->getPrefixedText()
			)->text();

		return [
			'form-action' => $this->config->get( MainConfigNames::Script ),
			'msg-close' => $this->context->msg( 'cosmos-createpage-close' )->text(),
			'msg-header' => $this->context->msg( 'cosmos-createpage-header' )->text(),
			'msg-label' => $this->context->msg( 'cosmos-createpage-input-label' )->text(),
			'html-text' => $this->context->msg(
				'cosmos-createpage-text',
				$this->context->getLanguage()->formatNum( SiteStats::articles() ),
				$this->context->msg( 'sitetitle' )->text(),
				$wantedPagesMessage
			)->parse(),
			'msg-next' => $this->context->msg( 'cosmos-createpage-next' )->text(),
			'array-proposals' => $wantedPagesEnabled ? $this->getMostWantedPages() : [],
		];
	}

	private function getMostWantedPages(): array {
		$page = $this->specialPageFactory->getPage( 'Wantedpages' );
		if ( !$page instanceof SpecialWantedPages ) {
			return [];
		}

		$rows = $this->config->get( ConfigNames::FetchWantedPagesFromCache ) ?
			$page->fetchFromCache( false ) :
			$page->doQuery();

		$namespaces = $this->config->get( ConfigNames::WantedPagesFetchedNamespaces );
		$max = (int)$this->config->get( ConfigNames::WantedPagesMaxTitlesCount );
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
