<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Specials;

use MediaWiki\Config\Config;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Cosmos\Components\PortletReader;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\Rail\RailBuilder;
use MediaWiki\Skin\Cosmos\Rail\RailModuleInfo;
use MediaWiki\Skin\Cosmos\Rail\RailModuleType;
use MediaWiki\Skin\Cosmos\Theme\ConfigDefaults;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\Skin\Cosmos\Theme\ThemeFont;
use MediaWiki\Skin\Cosmos\Theme\ThemePresets;
use MediaWiki\Skin\Cosmos\Theme\ThemeSettings;
use MediaWiki\Skin\Cosmos\Theme\ThemeStore;
use MediaWiki\Skin\SkinFactory;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use Throwable;
use function array_diff;
use function array_intersect;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_strimwidth;
use function preg_replace;
use function strip_tags;
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
		private readonly Config $cosmosOptions,
		private readonly ConfigDefaults $configDefaults,
		private readonly EffectiveTheme $theme,
		private readonly RailBuilder $railBuilder,
		private readonly ThemeStore $store,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly SkinFactory $skinFactory,
		private readonly TemplateParser $templateParser,
		private readonly TitleFactory $titleFactory,
	) {
		parent::__construct( 'CosmosThemeDesigner' );
	}

	private function isPublicView(): bool {
		return (bool)$this->cosmosOptions->get( ConfigNames::ThemeDesignerPublicView );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'cosmos-themedesigner';
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/**
	 * With public viewing on, anyone can open the designer. Publishing still needs the right.
	 *
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ): bool {
		return $this->isPublicView() || parent::userCanExecute( $user );
	}

	/** @inheritDoc */
	public function isRestricted(): bool {
		return !$this->isPublicView() && parent::isRestricted();
	}

	private function canEdit(): bool {
		return $this->getAuthority()->isAllowed( $this->getRestriction() );
	}

	/**
	 * @inheritDoc
	 * @param ?string $subPage @phan-unused-param
	 */
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
			$out->addHTML( Html::successBox( $this->msg( 'cosmos-themedesigner-success' )->escaped() ) );
		}

		$out->addWikiMsg( 'cosmos-themedesigner-text' );
		$out->addModules( [ 'skins.cosmos.themedesigner' ] );
		$out->addJsConfigVars( 'wgCosmosThemeDesigner', $this->getClientData() );
		$out->addHTML( $this->buildForm() );
	}

	private function handlePost(): void {
		$out = $this->getOutput();
		$request = $this->getRequest();
		if ( !$this->canEdit() ) {
			$this->displayRestrictionError();
		}

		$this->checkReadOnly();
		if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
			$out->addHTML( Html::errorBox( $this->msg( 'cosmos-themedesigner-error-token' )->escaped() ) );
			return;
		}

		$revertTo = $request->getInt( 'wpRevertTo' );
		if ( $revertTo > 0 ) {
			$saved = $this->store->restore(
				$revertTo,
				$this->getUser(),
				$this->msg( 'cosmos-themedesigner-restore-comment', $revertTo )->inContentLanguage()->text()
			);

			if ( $saved === null ) {
				$out->addHTML( Html::errorBox( $this->msg( 'cosmos-themedesigner-error-revision' )->escaped() ) );
				return;
			}
		} else {
			$decoded = json_decode( $request->getText( 'wpThemeJson' ), true );
			if ( !is_array( $decoded ) ) {
				$out->addHTML( Html::errorBox( $this->msg( 'cosmos-themedesigner-error-json' )->escaped() ) );
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

	/** @return array<string, mixed> */
	private function applyConfigurationRules( array $data ): array {
		$hidden = $data['footer']['hiddenLinks'] ?? [];
		$data['footer']['hiddenLinks'] = is_array( $hidden ) ?
			array_values( array_diff( $hidden, $this->theme->getFooterProtectedLinks() ) ) :
			[];

		if ( !$this->theme->canHideFooterIcons() ) {
			$data['footer']['showIcons'] = true;
		}

		return $data;
	}

	private function buildForm(): string {
		return $this->templateParser->processTemplate( 'ThemeDesigner', [
			'form-id' => 'skin-cosmos-themedesigner__form',
			'action' => $this->getPageTitle()->getLocalURL(),
			'html-token' => Html::hidden(
				'wpEditToken',
				$this->getContext()->getCsrfTokenSet()->getToken()->toString()
			),
			'msg-nojs' => $this->msg( 'cosmos-themedesigner-nojs' )->text(),
			'msg-advanced' => $this->msg( 'cosmos-themedesigner-advanced' )->text(),
			'msg-advanced-help' => $this->msg( 'cosmos-themedesigner-advanced-help' )->text(),
			'json' => json_encode(
				$this->store->getCurrent()->toArray(),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			),
			'is-editable' => $this->canEdit(),
			'msg-comment' => $this->msg( 'cosmos-themedesigner-comment' )->text(),
			'msg-publish' => $this->msg( 'cosmos-themedesigner-publish' )->text(),
		] );
	}

	private function getClientData(): array {
		$current = $this->store->getCurrent();
		$fallbacks = [];
		foreach ( ThemeSettings::MODES as $mode ) {
			foreach ( ThemeSettings::COLOR_SLOTS as $slot ) {
				$fallbacks[$mode][$slot] = $this->theme->getFallbackColor( $slot, $mode );
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
			'canEdit' => $this->canEdit(),
			'themeOnly' => $this->configDefaults->isThemeDesignerOnly(),
			'portableInfobox' => $this->extensionRegistry->isLoaded( 'PortableInfobox' ),
			'settings' => $current->toArray(),
			'defaults' => ThemeSettings::getDefaults(),
			'revisionId' => $current->getRevisionId(),
			'slots' => ThemeSettings::COLOR_SLOTS,
			'fallbacks' => $fallbacks,
			'presets' => $presets,
			'history' => $history,
			'effective' => $this->getEffectiveDefaults(),
			'canHideFooterIcons' => $this->theme->canHideFooterIcons(),
			'upload' => $this->getUploadData(),
			'fontPresets' => ThemeFont::PRESETS,
			'namespaces' => $this->getNamespaceOptions(),
			'railModules' => array_map(
				static fn ( RailModuleInfo $module ): array => $module->toArray(),
				$this->railBuilder->getAvailableModules()
			),
		] + $chrome;
	}

	/**
	 * What each setting is while the theme leaves it alone, for the designer to show and to reset to.
	 *
	 * @return array<string, mixed>
	 */
	private function getEffectiveDefaults(): array {
		$defaults = $this->theme->getDefaults();
		return [
			'images' => [
				'wordmark' => $defaults->wordmark,
				'header' => $defaults->headerImage,
				'background' => $defaults->backgroundImage,
				'backgroundSize' => $defaults->backgroundSize,
				'backgroundRepeat' => $defaults->backgroundRepeat,
				'backgroundFixed' => $defaults->backgroundFixed,
			],
			'layout' => [
				'contentWidth' => $defaults->contentWidth,
				'contentOpacity' => $defaults->contentOpacity,
				'fontFamily' => $defaults->fontFamily,
			],
			'extensions' => [
				'portableInfoboxEuropa' => $defaults->europa,
			],
			'rail' => [
				'disabledNamespaces' => $defaults->railDisabledNamespaces,
				'disabledPages' => $defaults->railDisabledPages,
				'recentChanges' => [
					'enabled' => $defaults->recentChanges,
					'type' => $defaults->recentChangesType->value,
				],
				'interface' => array_map(
					static fn ( RailModuleType $type ): string => $type->value,
					$defaults->interfaceModules
				),
			],
		];
	}

	/** @return array<string, mixed> */
	private function getUploadData(): array {
		$allowed = array_map( 'strtolower', (array)$this->getConfig()->get( MainConfigNames::FileExtensions ) );
		return [
			'enabled' => (bool)$this->getConfig()->get( MainConfigNames::EnableUploads ) &&
				$this->getAuthority()->isAllowed( 'upload' ),
			'extensions' => array_values( array_intersect( $allowed, self::IMAGE_EXTENSIONS ) ),
			'fontExtensions' => array_values( array_intersect( $allowed, ThemeFont::EXTENSIONS ) ),
		];
	}

	/** @return list<array<string, mixed>> */
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
	 *
	 * @return array<string, mixed>
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
		$toolbox = PortletReader::findPortlet( $data['data-portlets-sidebar'] ?? [], 'p-tb' );
		foreach ( $toolbox['array-items'] ?? [] as $item ) {
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

		$protected = $this->theme->getFooterProtectedLinks();
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
