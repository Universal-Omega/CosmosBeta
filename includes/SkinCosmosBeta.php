<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use CookieWarning\Decisions as CookieWarningDecisions;
use CookieWarning\Hooks as CookieWarningHooks;
use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\SkinMustache;
use MediaWiki\Skins\CosmosBeta\Components\BannerComponent;
use MediaWiki\Skins\CosmosBeta\Components\ChromeComponent;
use MediaWiki\Skins\CosmosBeta\Components\CreatePageDialogComponent;
use MediaWiki\Skins\CosmosBeta\Components\PageHeaderComponent;
use MediaWiki\Skins\CosmosBeta\Components\WikiHeaderComponent;
use MediaWiki\Skins\CosmosBeta\Theme\AltModules;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsManager;
use UserProfilePage;
use function array_map;
use function array_merge;
use function class_exists;
use function hash;
use function in_array;
use function preg_replace;
use function strtoupper;
use function trim;

class SkinCosmosBeta extends SkinMustache {

	private const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::EnablePortableInfoboxEuropaTheme,
		ConfigNames::RailSidebarPortlets,
		ConfigNames::SocialProfileAllowBio,
		ConfigNames::SocialProfileModernTabs,
		ConfigNames::SocialProfileRoundAvatar,
		ConfigNames::SocialProfileShowEditCount,
		ConfigNames::SocialProfileShowGroupTags,
	];

	public function __construct(
		private readonly AltModules $altModules,
		public readonly CosmosConfig $cosmosConfig,
		private readonly CosmosNavigation $navigation,
		private readonly Config $cosmosOptions,
		private readonly CosmosRailBuilder $railBuilder,
		private readonly CosmosWordmarkLookup $wordmarkLookup,
		private readonly Language $contentLanguage,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly LanguageNameUtils $languageNameUtils,
		private readonly PermissionManager $permissionManager,
		private readonly ServiceOptions $serviceOptions,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly ?CookieWarningDecisions $cookieWarningDecisions,
		array $options,
	) {
		parent::__construct( $options );
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public static function factory(
		AltModules $altModules,
		Config $cosmosOptions,
		CosmosConfig $cosmosConfig,
		CosmosNavigation $navigation,
		CosmosRailBuilder $railBuilder,
		CosmosWordmarkLookup $wordmarkLookup,
		Language $contentLanguage,
		ExtensionRegistry $extensionRegistry,
		LanguageNameUtils $languageNameUtils,
		PermissionManager $permissionManager,
		SpecialPageFactory $specialPageFactory,
		TitleFactory $titleFactory,
		UserOptionsManager $userOptionsManager,
		?CookieWarningDecisions $cookieWarningDecisions,
		array $options
	): self {
		return new self(
			$altModules,
			$cosmosConfig,
			$navigation,
			$cosmosOptions,
			$railBuilder,
			$wordmarkLookup,
			$contentLanguage,
			$extensionRegistry,
			$languageNameUtils,
			$permissionManager,
			new ServiceOptions(
				self::CONSTRUCTOR_OPTIONS,
				$cosmosOptions
			),
			$specialPageFactory,
			$titleFactory,
			$userOptionsManager,
			$cookieWarningDecisions,
			$options
		);
	}

	/** @inheritDoc */
	public function getTemplateData(): array {
		$data = parent::getTemplateData();

		$portlets = $data['data-portlets'] ?? [];
		$sidebar = $data['data-portlets-sidebar'] ?? [];
		$context = $this->getContext();
		$mainPage = $data['link-mainpage'];

		$banner = new BannerComponent( $context, $this->cosmosOptions, $this->extensionRegistry );
		$header = new WikiHeaderComponent(
			$context,
			$this->getConfig(),
			$this->permissionManager,
			$this->extensionRegistry,
			$this->wordmarkLookup,
			$this->cosmosConfig
		);
		$pageHeader = new PageHeaderComponent(
			$context,
			$this->titleFactory,
			$this->languageNameUtils,
			$this->contentLanguage
		);
		$dialog = new CreatePageDialogComponent(
			$context,
			$this->cosmosOptions,
			$this->specialPageFactory,
			$this->titleFactory
		);
		$chrome = new ChromeComponent( $context, $this->cosmosConfig, $this->extensionRegistry );

		$this->railBuilder->setSidebarModules( $this->getRailSidebarModules( $sidebar ) );

		$toolsInRail = $this->shouldPutToolsInRail();
		$this->railBuilder->setToolsModule( $toolsInRail, $toolsInRail ? $chrome->getToolItems( $sidebar ) : [] );

		$tree = $this->navigation->mergeSidebar(
			$this->navigation->getTree( $this, $this->getLanguage() ),
			array_merge( [ $sidebar['data-portlets-first'] ?? [] ], $sidebar['array-portlets-rest'] ?? [] )
		);
		$siteNotice = $data['html-site-notice'] ?? null;
		$search = $this->getSearchData( $data['data-search-box'] ?? [] );
		$dismissable = $this->extensionRegistry->isLoaded( 'DismissableSiteNotice' );
		$noticeClosed = $this->getRequest()->getCookie( 'CosmosSiteNoticeState' ) === 'closed';

		return [ 'data-search-box' => $search ] + $data + [
			'data-cosmos-navigation' => $tree,
			'data-cosmos-banner' => $banner->getTemplateData( $portlets ),
			'data-cosmos-header' => $header->getTemplateData( $mainPage ) + [ 'data-cosmos-navigation' => $tree ],
			'data-cosmos-page-header' => $pageHeader->getTemplateData( $portlets ) + [
				'html-title' => $data['html-title'] ?? '',
				'array-indicators' => $data['array-indicators'] ?? [],
			],
			'data-cosmos-dialog' => $dialog->getTemplateData(),
			'data-cosmos-footer' => $chrome->getFooterData( $data['data-footer'] ?? [] ),
			'data-cosmos-toolbar' => $chrome->getToolbarData( $sidebar, $toolsInRail ),
			'html-cosmos-rail' => $this->railBuilder->buildRail(),
			'html-cosmos-cookiewarning' => $this->getCookieWarning(),
			'is-cosmos-dismissable-notice' => $siteNotice !== null && $dismissable,
			'is-cosmos-closable-notice' => $siteNotice !== null && !$dismissable && !$noticeClosed,
			'cosmos-notice-hash' => $siteNotice !== null ? hash( 'crc32b', $siteNotice ) : null,
			'msg-cosmosbeta-tagline' => $this->msg( 'cosmosbeta-tagline' )->escaped(),
		];
	}

	/** @inheritDoc */
	public function getDefaultModules(): array {
		$modules = parent::getDefaultModules();

		$this->railBuilder->setToolsModule( $this->shouldPutToolsInRail(), [] );

		if (
			!$this->railBuilder->isHidden() &&
			( $this->railBuilder->hasModules() || (array)$this->serviceOptions->get( ConfigNames::RailSidebarPortlets ) !== [] )
		) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.rail';
		}

		if ( $this->extensionRegistry->isLoaded( 'PortableInfobox' ) ) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.portableinfobox';
			$modules['styles']['skin'][] = $this->serviceOptions->get( ConfigNames::EnablePortableInfoboxEuropaTheme ) ?
				'skins.cosmosbeta.portableinfobox.europa' :
				'skins.cosmosbeta.portableinfobox.default';
		}

		if (
			LessUtil::isThemeDark(
				'content-background-color',
				LessUtil::getCosmosSettings( $this->cosmosConfig->getRenderMode() )
			) &&
			$this->extensionRegistry->isLoaded( 'CodeMirror' ) &&
			$this->extensionRegistry->isLoaded( 'VisualEditor' )
		) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.codemirror';
		}

		if ( $this->extensionRegistry->isLoaded( 'CodeEditor' ) ) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.codeeditor';
		}

		foreach ( $this->getSocialProfileModules() as $module ) {
			$modules['styles']['skin'][] = $module;
		}

		if ( $this->cosmosConfig->getRenderMode() !== $this->cosmosConfig->getDefaultMode() ) {
			foreach ( $modules['styles']['skin'] ?? [] as $index => $name ) {
				$modules['styles']['skin'][$index] = $this->altModules->getTwinName( $name ) ?? $name;
			}
		}

		return $modules;
	}

	/**
	 * Sidebar sections such as the dynamic user sidebar are moved out of the top navigation into the rail.
	 */
	private function getRailSidebarModules( array $sidebar ): array {
		$names = array_map( strtoupper( ... ), (array)$this->serviceOptions->get( ConfigNames::RailSidebarPortlets ) );
		$portlets = array_merge( [ $sidebar['data-portlets-first'] ?? null ], $sidebar['array-portlets-rest'] ?? [] );
		$modules = [];

		foreach ( $portlets as $portlet ) {
			if ( !$portlet ) {
				continue;
			}

			$id = strtoupper( (string)preg_replace( '/^p-/i', '', (string)( $portlet['id'] ?? '' ) ) );
			$label = strtoupper( trim( (string)( $portlet['label'] ?? '' ) ) );

			if ( !in_array( $id, $names, true ) && !in_array( $label, $names, true ) ) {
				continue;
			}

			$items = [];

			foreach ( $portlet['array-items'] ?? [] as $item ) {
				$items[] = [ 'html-item' => $item['html-item'] ?? '' ];
			}

			if ( $items ) {
				$modules[] = [ 'label' => (string)( $portlet['label'] ?? '' ), 'items' => $items ];
			}
		}

		return $modules;
	}

	private function shouldPutToolsInRail(): bool {
		$settings = $this->cosmosConfig->getToolbarSettings();

		return $settings['enabled'] && $settings['style'] === 'rail' && !$this->railBuilder->isHidden();
	}

	private function getSearchData( array $search ): array {
		$classes = 'searchButton skin-cosmos-search-button cosmos-search-button';

		return [
			'html-input' => $this->makeSearchInput( [
				'id' => 'searchInput',
				'class' => 'skin-cosmos-search-input cosmos-search-input',
			] ),
			'html-button-search' => $this->makeSearchButton( 'go', [
				'id' => 'searchButton',
				'class' => $classes,
			] ),
			'html-button-search-fallback' => $this->makeSearchButton( 'fulltext', [
				'id' => 'mw-searchButton',
				'class' => "mw-fallbackSearchButton $classes",
			] ),
		] + $search;
	}

	private function getSocialProfileModules(): array {
		if ( !class_exists( UserProfilePage::class ) ) {
			return [];
		}

		$modules = [];
		$map = [
			ConfigNames::SocialProfileModernTabs => 'skins.cosmosbeta.profiletabs',
			ConfigNames::SocialProfileRoundAvatar => 'skins.cosmosbeta.profileavatar',
			ConfigNames::SocialProfileShowEditCount => 'skins.cosmosbeta.profileeditcount',
			ConfigNames::SocialProfileAllowBio => 'skins.cosmosbeta.profilebio',
			ConfigNames::SocialProfileShowGroupTags => 'skins.cosmosbeta.profiletags',
		];

		foreach ( $map as $option => $module ) {
			if ( $this->serviceOptions->get( $option ) ) {
				$modules[] = $module;
			}
		}

		if ( $modules ) {
			$modules[] = 'skins.cosmosbeta.socialprofile';
		}

		return $modules;
	}

	private function getCookieWarning(): ?string {
		if ( $this->cookieWarningDecisions === null ) {
			return null;
		}

		$hooks = new CookieWarningHooks(
			$this->getConfig(),
			$this->cookieWarningDecisions,
			$this->userOptionsManager
		)

		$html = '';
		$hooks->onSkinAfterContent( $html, $this );
		return $html !== '' ? $html : null;
	}
}
