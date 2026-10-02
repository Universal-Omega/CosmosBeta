<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Special;

use MediaWiki\Html\Html;
use MediaWiki\Skins\CosmosBeta\CosmosConfig;
use MediaWiki\Skins\CosmosBeta\Theme\ThemePresets;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeSettings;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeStore;
use MediaWiki\SpecialPage\SpecialPage;
use function is_array;
use function json_decode;
use function json_encode;
use function trim;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class SpecialThemeDesigner extends SpecialPage {

	private const array TOOLBAR_ITEMS = [
		'whatlinkshere',
		'recentchangeslinked',
		'upload',
		'specialpages',
		'print',
		'permalink',
		'info',
		'cite',
		'createredirect',
	];

	private const array FOOTER_LINKS = [
		'lastmod',
		'copyright',
		'privacy',
		'about',
		'disclaimers',
	];

	public function __construct(
		private readonly CosmosConfig $config,
		private readonly ThemeStore $store,
	) {
		parent::__construct( 'CosmosBetaThemeDesigner' );
	}

	public function getRestriction(): string {
		return 'cosmosbeta-themedesigner';
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->addHelpLink( 'Skin:Cosmos' );

		$out = $this->getOutput();
		$request = $this->getRequest();

		if ( $request->wasPosted() ) {
			$this->handlePost();
			return;
		}

		if ( $request->getCheck( 'saved' ) ) {
			$out->addHTML( Html::successBox( $this->msg( 'cosmosbeta-themedesigner-success' )->escaped() ) );
		}

		$out->addWikiMsg( 'cosmosbeta-themedesigner-text' );
		$out->addModules( 'skins.cosmosbeta.themedesigner' );
		$out->addJsConfigVars( 'wgCosmosBetaThemeDesigner', $this->getClientData() );
		$out->addHTML( $this->buildForm() );
	}

	private function handlePost(): void {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->checkReadOnly();

		if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
			$out->addHTML( Html::errorBox( $this->msg( 'cosmosbeta-themedesigner-error-token' )->escaped() ) );
			return;
		}

		$revertTo = $request->getInt( 'wpRevertTo' );

		if ( $revertTo > 0 ) {
			$saved = $this->store->restore(
				$revertTo,
				$this->getUser(),
				$this->msg( 'cosmosbeta-themedesigner-restore-comment', $revertTo )->inContentLanguage()->text()
			);

			if ( $saved === null ) {
				$out->addHTML( Html::errorBox( $this->msg( 'cosmosbeta-themedesigner-error-revision' )->escaped() ) );
				return;
			}
		} else {
			$decoded = json_decode( $request->getText( 'wpThemeJson' ), true );

			if ( !is_array( $decoded ) ) {
				$out->addHTML( Html::errorBox( $this->msg( 'cosmosbeta-themedesigner-error-json' )->escaped() ) );
				return;
			}

			$this->store->save(
				new ThemeSettings( $decoded ),
				$this->getUser(),
				trim( $request->getText( 'wpComment' ) )
			);
		}

		$out->redirect( $this->getPageTitle()->getFullURL( [ 'saved' => 1 ] ) );
	}

	private function buildForm(): string {
		$comment = $this->msg( 'cosmosbeta-themedesigner-comment' )->text();

		return Html::rawElement( 'form', [
			'id' => 'cosmosbeta-themedesigner-form',
			'class' => 'skin-cosmos-td',
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL(),
		],
			Html::hidden( 'wpEditToken', $this->getContext()->getCsrfTokenSet()->getToken()->toString() ) .
			Html::rawElement( 'div', [ 'id' => 'skin-cosmos-themedesigner-app' ],
				Html::element( 'noscript', [], $this->msg( 'cosmosbeta-themedesigner-nojs' )->text() )
			) .
			Html::rawElement( 'details', [ 'class' => 'skin-cosmos-td-advanced' ],
				Html::element( 'summary', [], $this->msg( 'cosmosbeta-themedesigner-advanced' )->text() ) .
				Html::element( 'p', [], $this->msg( 'cosmosbeta-themedesigner-advanced-help' )->text() ) .
				Html::textarea(
					'wpThemeJson',
					json_encode(
						$this->store->getCurrent()->toArray(),
						JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					),
					[ 'id' => 'skin-cosmos-themedesigner-json', 'rows' => 14, 'spellcheck' => 'false' ]
				)
			) .
			Html::rawElement( 'div', [ 'class' => 'skin-cosmos-td-publish' ],
				Html::element( 'input', [
					'type' => 'text',
					'name' => 'wpComment',
					'maxlength' => 200,
					'placeholder' => $comment,
					'aria-label' => $comment,
				] ) .
				Html::element( 'button', [
					'type' => 'submit',
					'class' => 'skin-cosmos-td-button skin-cosmos-td-button-primary',
				], $this->msg( 'cosmosbeta-themedesigner-publish' )->text() )
			)
		);
	}

	private function getClientData(): array {
		$current = $this->store->getCurrent();

		$fallbacks = [];
		foreach ( ThemeSettings::MODES as $mode ) {
			foreach ( ThemeSettings::COLOR_SLOTS as $slot ) {
				$fallbacks[$mode][$slot] = $this->config->getFallbackColor( $slot, $mode );
			}
		}

		$presets = [];
		foreach ( ThemePresets::getAll() as $name => $preset ) {
			$presets[] = [ 'name' => $name ] + $preset;
		}

		$history = [];
		foreach ( $this->store->getHistory() as $row ) {
			$history[] = [
				'id' => $row['id'],
				'user' => $row['user'],
				'comment' => $row['comment'],
				'time' => $this->getLanguage()->userTimeAndDate( $row['timestamp'], $this->getUser() ),
			];
		}

		return [
			'canEdit' => true,
			'settings' => $current->toArray(),
			'revisionId' => $current->getRevisionId(),
			'slots' => ThemeSettings::COLOR_SLOTS,
			'fallbacks' => $fallbacks,
			'presets' => $presets,
			'history' => $history,
			'config' => [
				'backgroundSize' => $this->config->getBackgroundImageSize(),
				'contentOpacity' => $this->config->getContentOpacityLevel(),
			],
			'toolbarItems' => self::TOOLBAR_ITEMS,
			'footerLinks' => self::FOOTER_LINKS,
		];
	}
}
