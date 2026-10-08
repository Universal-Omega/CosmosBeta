<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos;

use CookieWarning\Decisions as CookieWarningDecisions;
use CookieWarning\Hooks as CookieWarningHooks;
use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Cosmos\Components\BannerComponent;
use MediaWiki\Skin\Cosmos\Components\ChromeComponent;
use MediaWiki\Skin\Cosmos\Components\CreatePageDialogComponent;
use MediaWiki\Skin\Cosmos\Components\PageHeaderComponent;
use MediaWiki\Skin\Cosmos\Components\WikiHeaderComponent;
use MediaWiki\Skin\Cosmos\Lookup\WordmarkLookup;
use MediaWiki\Skin\Cosmos\Rail\RailBuilder;
use MediaWiki\Skin\Cosmos\Theme\AltModules;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\Skin\SkinMustache;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Options\UserOptionsManager;
use UserProfilePage;
use function array_merge;
use function class_exists;
use function hash;

class SkinCosmos extends SkinMustache {

	private const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::SocialProfileAllowBio,
		ConfigNames::SocialProfileModernTabs,
		ConfigNames::SocialProfileRoundAvatar,
		ConfigNames::SocialProfileShowEditCount,
		ConfigNames::SocialProfileShowGroupTags,
	];

	public function __construct(
		private readonly AltModules $altModules,
		private readonly CosmosNavigation $navigation,
		private readonly Config $cosmosOptions,
		private readonly EffectiveTheme $theme,
		private readonly LanguageCode $contentLanguageCode,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly LanguageNameUtils $languageNameUtils,
		private readonly PermissionManager $permissionManager,
		private readonly RailBuilder $railBuilder,
		private readonly ServiceOptions $serviceOptions,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly WordmarkLookup $wordmarkLookup,
		private readonly ?CookieWarningDecisions $cookieWarningDecisions,
		array $options,
	) {
		parent::__construct( $options );
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public static function factory(
		AltModules $altModules,
		Config $cosmosOptions,
		CosmosNavigation $navigation,
		EffectiveTheme $theme,
		RailBuilder $railBuilder,
		WordmarkLookup $wordmarkLookup,
		LanguageCode $contentLanguageCode,
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
			$navigation,
			$cosmosOptions,
			$theme,
			$contentLanguageCode,
			$extensionRegistry,
			$languageNameUtils,
			$permissionManager,
			$railBuilder,
			new ServiceOptions(
				self::CONSTRUCTOR_OPTIONS,
				$cosmosOptions
			),
			$specialPageFactory,
			$titleFactory,
			$userOptionsManager,
			$wordmarkLookup,
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

		$banner = new BannerComponent(
			$context,
			$this->cosmosOptions,
			$this->extensionRegistry
		);

		$header = new WikiHeaderComponent(
			$context,
			$this->getConfig(),
			$this->theme,
			$this->extensionRegistry,
			$this->permissionManager,
			$this->wordmarkLookup
		);

		$pageHeader = new PageHeaderComponent(
			$context,
			$this->contentLanguageCode,
			$this->languageNameUtils,
			$this->titleFactory
		);

		$dialog = new CreatePageDialogComponent(
			$context,
			$this->cosmosOptions,
			$this->specialPageFactory,
			$this->titleFactory
		);

		$chrome = new ChromeComponent(
			$context,
			$this->theme,
			$this->extensionRegistry
		);

		$this->railBuilder->setSidebarModulesFromPortlets( $sidebar );
		$toolsInRail = $this->railBuilder->shouldPutToolsInRail();
		$this->railBuilder->setToolsModule( $toolsInRail, $toolsInRail ? $chrome->getToolItems( $sidebar ) : [] );

		$tree = $this->navigation->mergeSidebar(
			$this->navigation->getTree( $this, $this->getLanguage() ),
			array_merge( [ $sidebar['data-portlets-first'] ?? [] ], $sidebar['array-portlets-rest'] ?? [] )
		);

		$siteNotice = $data['html-site-notice'] ?? null;
		// Core wraps the site notice in its container even when nothing is in it
		$hasNotice = $siteNotice !== null && $siteNotice !== '<div id="siteNotice"></div>';
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
			'data-cosmos-toolbar' => $chrome->getToolbarData( $sidebar ),
			'html-cosmos-rail' => $this->railBuilder->buildRail(),
			'html-cosmos-cookiewarning' => $this->getCookieWarning(),
			'is-cosmos-dismissable-notice' => $hasNotice && $dismissable,
			'is-cosmos-closable-notice' => $hasNotice && !$dismissable && !$noticeClosed,
			'is-cosmos-empty-notice' => !$hasNotice,
			'cosmos-notice-hash' => $hasNotice ? hash( 'crc32b', $siteNotice ) : null,
			'msg-cosmos-tagline' => $this->msg( 'cosmos-tagline' )->escaped(),
		];
	}

	/** @inheritDoc */
	public function getDefaultModules(): array {
		$modules = parent::getDefaultModules();

		// Real modules are added when the page is built. All that matters here is whether any will exist.
		$this->railBuilder
			->setSidebarModulesFromSections( $this->buildSidebar() )
			->setToolsModule( $this->railBuilder->shouldPutToolsInRail(), [] );

		if ( $this->railBuilder->hasModules() ) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.rail';
		}

		if ( $this->theme->getFooterSettings()['showIcons'] ) {
			$modules['styles']['skin'][] = 'skins.cosmos.footer.codex';
		}

		if ( $this->extensionRegistry->isLoaded( 'PortableInfobox' ) ) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.portableinfobox';
			$modules['styles']['skin'][] = $this->theme->isPortableInfoboxEuropaEnabled() ?
				'skins.cosmosbeta.portableinfobox.europa' :
				'skins.cosmosbeta.portableinfobox.default';
		}

		foreach ( $this->getSocialProfileModules() as $module ) {
			$modules['styles']['skin'][] = $module;
		}

		if ( $this->theme->getRenderMode() !== $this->theme->getDefaultMode() ) {
			foreach ( $modules['styles']['skin'] ?? [] as $index => $name ) {
				$modules['styles']['skin'][$index] = $this->altModules->getTwinName( $name ) ?? $name;
			}
		}

		return $modules;
	}

	private function getSearchData( array $search ): array {
		$classes = 'searchButton skin-cosmos-search-box__button cosmos-search-button';
		return [
			'html-input' => $this->makeSearchInput( [
				'id' => 'searchInput',
				'class' => 'skin-cosmos-search-box__input cosmos-search-input',
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
		);

		$html = '';
		$hooks->onSkinAfterContent( $html, $this );
		return $html !== '' ? $html : null;
	}
}
