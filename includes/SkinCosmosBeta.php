<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use CookieWarning\Decisions as CookieWarningDecisions;
use CookieWarning\Hooks as CookieWarningHooks;
use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
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
use function class_exists;
use function hash;

class SkinCosmosBeta extends SkinMustache {

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
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UserOptionsManager $userOptionsManager,
		private readonly ?CookieWarningDecisions $cookieWarningDecisions,
		array $options,
	) {
		parent::__construct( $options );
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
			$this->wordmarkLookup
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

		$tree = $this->navigation->getTree( $this, $this->getLanguage() );
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
			'data-cosmos-toolbar' => $chrome->getToolbarData( $sidebar ),
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

		if ( !$this->railBuilder->isHidden() && $this->railBuilder->hasModules() ) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.rail';
		}

		if ( $this->extensionRegistry->isLoaded( 'PortableInfobox' ) ) {
			$modules['styles']['skin'][] = 'skins.cosmosbeta.portableinfobox';
			$modules['styles']['skin'][] = $this->cosmosOptions->get( ConfigNames::EnablePortableInfoboxEuropaTheme ) ?
				'skins.cosmosbeta.portableinfobox.europa' :
				'skins.cosmosbeta.portableinfobox.default';
		}

		if (
			LessUtil::isThemeDark( 'content-background-color' ) &&
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

	private function getSearchData( array $search ): array {
		$classes = 'skin-cosmos-search-button cosmos-search-button';

		return [
			'html-input' => Html::element( 'input', [
				'id' => 'searchInput',
				'class' => 'skin-cosmos-search-input cosmos-search-input',
			] + ( $search['array-input-attributes'] ?? [] ) ),
			'html-button-search' => Html::element( 'input', [
				'id' => 'searchButton',
				'class' => $classes,
				'value' => $this->msg( 'searcharticle' )->text(),
			] + ( $search['array-button-go-attributes'] ?? [] ) ),
			'html-button-search-fallback' => Html::element( 'input', [
				'id' => 'mw-searchButton',
				'class' => "mw-fallbackSearchButton $classes",
				'value' => $this->msg( 'searchbutton' )->text(),
			] + ( $search['array-button-fulltext-attributes'] ?? [] ) ),
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
			if ( $this->cosmosOptions->get( $option ) ) {
				$modules[] = $module;
			}
		}

		if ( $modules ) {
			$modules[] = 'skins.cosmosbeta.socialprofile';
		}

		return $modules;
	}

	private function getCookieWarning(): ?string {
		if ( !$this->extensionRegistry->isLoaded( 'CookieWarning' ) ) {
			return null;
		}

		$hooks = $this->cookieWarningDecisions ?
			new CookieWarningHooks(
				$this->getConfig(),
				$this->cookieWarningDecisions,
				$this->userOptionsManager
			) :
			// @phan-suppress-next-line PhanParamTooFew
			new CookieWarningHooks();

		$html = '';
		$hooks->onSkinAfterContent( $html, $this );

		return $html !== '' ? $html : null;
	}
}
