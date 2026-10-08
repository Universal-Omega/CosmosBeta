<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Specials;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Skin\Cosmos\Legacy\LegacyConversionResult;
use MediaWiki\Skin\Cosmos\Legacy\LegacyConverter;
use MediaWiki\SpecialPage\SpecialPage;
use function strlen;

class SpecialLegacyConverter extends SpecialPage {

	private ?LegacyConversionResult $result = null;

	public function __construct(
		private readonly LegacyConverter $converter,
	) {
		parent::__construct( 'CosmosLegacyConverter' );
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'cosmosbeta-legacyconverter' );
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->getOutput()->addWikiMsg( 'cosmosbeta-legacyconverter-intro' );

		$form = new HTMLForm( [
			'type' => [
				'type' => 'radio',
				'label-message' => 'cosmosbeta-legacyconverter-type',
				'options-messages' => [
					'cosmosbeta-legacyconverter-type-css' => 'css',
					'cosmosbeta-legacyconverter-type-js' => 'js',
				],
				'default' => 'css',
			],
			'source' => [
				'type' => 'textarea',
				'label-message' => 'cosmosbeta-legacyconverter-source',
				'rows' => 16,
				'required' => true,
				'spellcheck' => false,
			],
		], $this->getContext(), 'cosmosbeta-legacyconverter' );
		$form->setSubmitTextMsg( 'cosmosbeta-legacyconverter-submit' )
			->setSubmitCallback( $this->convert( ... ) )
			->show();

		if ( $this->result !== null ) {
			$this->getOutput()->addHTML( $this->renderResult( $this->result ) );
		}
	}

	private function convert( array $data ): false|Message {
		$source = (string)$data['source'];
		$limit = (int)$this->getConfig()->get( MainConfigNames::MaxArticleSize ) * 1024;

		if ( strlen( $source ) > $limit ) {
			return $this->msg( 'cosmosbeta-legacyconverter-toolarge' );
		}

		$this->result = $data['type'] === 'js'
			? $this->converter->convertJs( $source )
			: $this->converter->convertCss( $source );

		return false;
	}

	private function renderResult( LegacyConversionResult $result ): string {
		$html = Html::element( 'h2', [], $this->msg( 'cosmosbeta-legacyconverter-output' )->text() );
		$html .= Html::element( 'textarea', [
			'class' => 'skin-cosmos-legacyconverter-output',
			'rows' => 16,
			'readonly' => true,
			'spellcheck' => 'false',
			'style' => 'width: 100%; font-family: monospace;',
		], $result->output );

		if ( $result->replacements === [] ) {
			$html .= Html::noticeBox(
				$this->msg( 'cosmosbeta-legacyconverter-nochanges' )->escaped(),
				'skin-cosmos-legacyconverter-summary'
			);
		} else {
			$rows = '';
			foreach ( $result->replacements as $old => $count ) {
				$rows .= Html::rawElement( 'tr', [],
					Html::element( 'td', [], $old ) .
					Html::element( 'td', [], $result->mappedTo[$old] ) .
					Html::element( 'td', [], (string)$count )
				);
			}

			$html .= Html::element( 'h2', [], $this->msg( 'cosmosbeta-legacyconverter-summary' )->text() );
			$html .= Html::rawElement( 'table', [ 'class' => 'wikitable' ],
				Html::rawElement( 'tr', [],
					Html::element( 'th', [], $this->msg( 'cosmosbeta-legacyconverter-old' )->text() ) .
					Html::element( 'th', [], $this->msg( 'cosmosbeta-legacyconverter-new' )->text() ) .
					Html::element( 'th', [], $this->msg( 'cosmosbeta-legacyconverter-count' )->text() )
				) . $rows
			);
		}

		if ( $result->iconClasses !== [] ) {
			$html .= Html::warningBox(
				$this->msg( 'cosmosbeta-legacyconverter-icons' )
					->params( $this->getLanguage()->commaList( $result->iconClasses ) )
					->parse()
			);
		}

		return $html;
	}
}
