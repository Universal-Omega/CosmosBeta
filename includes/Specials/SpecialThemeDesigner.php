<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Specials;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\SkinFactory;
use MediaWiki\Skin\Cosmos\Components\PortletReader;
use MediaWiki\Skin\Cosmos\CosmosConfig;
use MediaWiki\Skin\Cosmos\Rail\RailBuilder;
use MediaWiki\Skin\Cosmos\Rail\RailModuleInfo;
use MediaWiki\Skin\Cosmos\Theme\ThemePresets;
use MediaWiki\Skin\Cosmos\Theme\ThemeSettings;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use Throwable;
use function array_diff;
use function array_intersect;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function preg_replace;
use function strip_tags;
use function strtolower;
use function trim;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const NS_MAIN;

class SpecialThemeDesigner extends SpecialPage {

	private const array IMAGE_EXTENSIONS = [ 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' ];

	private const array FALLBACK_TOOLBAR_ITEMS = [
		'whatlinkshere',
		'recentchangeslinked',
		'upload',
		'specialpages',
		'print',
		'permalink',
		'info',
		'cite',
	];

	public function __construct(
		private readonly CosmosConfig $config,
		private readonly RailBuilder $railBuilder,
		private readonly ThemeStore $store,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly SkinFactory $skinFactory,
		private readonly TemplateParser $templateParser,
		private readonly TitleFactory $titleFactory,
	) {
		parent::__construct( 'CosmosBetaThemeDesigner' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'cosmosbeta-themedesigner';
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		$this->checkPermissions();
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
		$out->addModules( [ 'skins.cosmosbeta.themedesigner' ] );
		$out->addJsConfigVars( 'wgCosmosThemeDesigner', $this->getClientData() );
		$out->addHTML( $this->buildForm() );
	}

	private function handlePost(): void {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->checkPermissions();
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

			$decoded = $this->applyConfigurationRules( $decoded );
			$this->store->save(
				new ThemeSettings( $decoded, 0 ),
				$this->getUser(),
				trim( $request->getText( 'wpComment' ) )
			);
		}

		$out->redirect( $this->getPageTitle()->getFullURL( [ 'saved' => 1 ] ) );
	}

	private function applyConfigurationRules( array $data ): array {
		$hidden = $data['footer']['hiddenLinks'] ?? [];
		$data['footer']['hiddenLinks'] = is_array( $hidden ) ?
			array_values( array_diff( $hidden, $this->config->getFooterProtectedLinks() ) ) :
			[];

		if ( !$this->config->canHideFooterIcons() ) {
			$data['footer']['showIcons'] = true;
		}

		return $data;
	}

	private function buildForm(): string {
		return $this->templateParser->processTemplate( 'ThemeDesigner', [
			'form-id' => 'skin-cosmos-themedesigner__form',
			'action' => $this->getPageTitle()->getLocalURL(),
			'html-token' => Html::hidden( 'wpEditToken', $this->getContext()->getCsrfTokenSet()->getToken()->toString() ),
			'msg-nojs' => $this->msg( 'cosmosbeta-themedesigner-nojs' )->text(),
			'msg-advanced' => $this->msg( 'cosmosbeta-themedesigner-advanced' )->text(),
			'msg-advanced-help' => $this->msg( 'cosmosbeta-themedesigner-advanced-help' )->text(),
			'json' => json_encode(
				$this->store->getCurrent()->toArray(),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			),
			'msg-comment' => $this->msg( 'cosmosbeta-themedesigner-comment' )->text(),
			'msg-publish' => $this->msg( 'cosmosbeta-themedesigner-publish' )->text(),
		] );
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
		foreach ( $this->store->getHistory( 30 ) as $row ) {
			$history[] = [
				'id' => $row['id'],
				'user' => $row['user'],
				'comment' => $row['comment'],
				'time' => $this->getLanguage()->userTimeAndDate( $row['timestamp'], $this->getUser() ),
			];
		}

		// Reading the chrome builds the sidebar modules that the rail then lists
		$chrome = $this->getChromeOptions();

		return [
			'canEdit' => $this->userCanExecute( $this->getUser() ),
			'settings' => $current->toArray(),
			'defaults' => ThemeSettings::getDefaults(),
			'revisionId' => $current->getRevisionId(),
			'slots' => ThemeSettings::COLOR_SLOTS,
			'fallbacks' => $fallbacks,
			'presets' => $presets,
			'history' => $history,
			'config' => [
				'backgroundSize' => $this->config->getBackgroundImageSize(),
				'contentOpacity' => $this->config->getContentOpacityLevel(),
			],
			'canHideFooterIcons' => $this->config->canHideFooterIcons(),
			'upload' => $this->getUploadData(),
			'namespaces' => $this->getNamespaceOptions(),
			'railModules' => array_map(
				static fn ( RailModuleInfo $module ): array => $module->toArray(),
				$this->railBuilder->getAvailableModules()
			),
		] + $chrome;
	}

	private function getUploadData(): array {
		$extensions = array_values( array_intersect(
			array_map( 'strtolower', (array)$this->getConfig()->get( MainConfigNames::FileExtensions ) ),
			self::IMAGE_EXTENSIONS
		) );

		return [
			'enabled' => (bool)$this->getConfig()->get( MainConfigNames::EnableUploads ) &&
				$this->getAuthority()->isAllowed( 'upload' ),
			'extensions' => $extensions,
		];
	}

	private function getNamespaceOptions(): array {
		$options = [];
		foreach ( $this->getLanguage()->getFormattedNamespaces() as $id => $name ) {
			$options[] = [
				'value' => (int)$id,
				'label' => $id === NS_MAIN ? $this->msg( 'blanknamespace' )->text() : (string)$name,
			];
		}

		return $options;
	}

	/**
	 * Reads the page tools and footer links that this wiki really has, from the skin itself.
	 */
	private function getChromeOptions(): array {
		$data = [];

		try {
			$mainPage = $this->titleFactory->newMainPage();
			$context = new DerivativeContext( $this->getContext() );
			$context->setTitle( $mainPage );

			$skin = $this->skinFactory->makeSkin( 'cosmosbeta' );
			$skin->setContext( $context );
			$skin->setRelevantTitle( $mainPage );
			$data = $skin->getTemplateData();
		} catch ( Throwable ) {
			// Fall back to the usual items below.
		}

		$tools = [];
		foreach ( PortletReader::findPortlet( $data['data-portlets-sidebar'] ?? [], 'p-tb' )['array-items'] ?? [] as $item ) {
			$name = (string)( $item['name'] ?? '' );

			if ( $name !== '' ) {
				$tools[$name] = [
					'name' => $name,
					'label' => trim( (string)( $item['array-links'][0]['text'] ?? $name ) ),
				];
			}
		}

		if ( !$tools ) {
			foreach ( self::FALLBACK_TOOLBAR_ITEMS as $name ) {
				$tools[$name] = [ 'name' => $name, 'label' => $name ];
			}
		}

		if ( $this->extensionRegistry->isLoaded( 'CreateRedirect' ) ) {
			$tools['createredirect'] ??= [
				'name' => 'createredirect',
				'label' => $this->msg( 'createredirect' )->text(),
			];
		}

		$protected = $this->config->getFooterProtectedLinks();
		$links = [];

		foreach ( [ 'data-info' => 'info', 'data-places' => 'places' ] as $key => $group ) {
			foreach ( $data['data-footer'][$key]['array-items'] ?? [] as $item ) {
				$name = (string)( $item['name'] ?? '' );
				$label = trim( (string)preg_replace( '/\s+/', ' ', strip_tags( (string)( $item['html'] ?? '' ) ) ) );
				if ( $name !== '' ) {
					$links[$name] = [
						'name' => $name,
						'label' => $label !== '' ? mb_strimwidth( $label, 0, 60, '...' ) : $name,
						'group' => $group,
						'protected' => in_array( $name, $protected, true ),
					];
				}
			}
		}

		foreach ( $protected as $name ) {
			$links[$name] ??= [
				'name' => $name,
				'label' => $name,
				'group' => 'places',
				'protected' => true,
			];
		}

		return [
			'toolbarItems' => array_values( $tools ),
			'footerLinks' => array_values( $links ),
		];
	}
}
