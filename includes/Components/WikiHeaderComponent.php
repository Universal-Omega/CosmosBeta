<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Components;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SiteStats\SiteStats;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\Skin\Cosmos\WordmarkLookup;
use MediaWiki\SpecialPage\SpecialPage;
use function ucwords;

class WikiHeaderComponent {

	public function __construct(
		private readonly IContextSource $context,
		private readonly Config $config,
		private readonly EffectiveTheme $theme,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly PermissionManager $permissionManager,
		private readonly WordmarkLookup $wordmarkLookup,
	) {
	}

	public function getTemplateData( string $mainPageUrl ): array {
		$user = $this->context->getUser();
		$canRead = $this->permissionManager->userHasRight( $user, 'read' );
		$articles = SiteStats::articles();

		$data = [
			'link-mainpage' => $mainPageUrl,
			'wordmark-url' => $this->wordmarkLookup->getWordmarkUrl(),
			'sitename' => $this->context->msg( 'sitetitle' )->text(),
			'can-read' => $canRead,
			'counter-value' => $this->context->getLanguage()->formatNum( $articles ),
			'counter-label' => $this->context->msg( 'cosmosbeta-counter-label' )->numParams( $articles )->escaped(),
		];

		return $canRead ? $data + $this->getButtons( !$user->isNamed() ) : $data;
	}

	private function getButtons( bool $isAnon ): array {
		$user = $this->context->getUser();
		$can = fn ( string $right ): bool => $this->permissionManager->userHasRight( $user, $right );

		$canCreate = $can( 'createpage' );
		$canEdit = $can( 'edit' );
		$canUpload = $can( 'upload' ) && $this->config->get( MainConfigNames::EnableUploads );
		$canAddVideo = $can( 'addvideo' ) && $this->extensionRegistry->isLoaded( 'Video' );
		$canViewAdminLinks = $can( 'adminlinks' );
		$canViewDashboard = $can( 'cosmosbeta-admindashboard' );

		$recentChanges = $this->context->msg( 'recentchanges' );
		$addNewPage = $this->context->msg( 'cosmosbeta-add-new-page-text' );
		$uploadUrl = $this->config->get( MainConfigNames::UploadNavigationUrl ) ?:
			SpecialPage::getTitleFor( 'Upload' )->getFullURL();
		$recentChangesUrl = SpecialPage::getTitleFor( 'Recentchanges' )->getFullURL();

		$createText = null;
		if ( $canViewAdminLinks ) {
			$createText = $isAnon ? $addNewPage->text() : null;
		} else {
			$createText = $isAnon ?
				$this->context->msg( 'cosmosbeta-anon-add-new-page-text' )->text() :
				$addNewPage->text();
		}

		$onlyRead = !$canEdit && !$canCreate;
		$hasColorMode = $this->theme->isColorModeToggleEnabled();
		$mode = $this->theme->getRenderMode();
		$hasMore = ( !$isAnon && ( $canUpload || $canAddVideo ) ) ||
			( ( $canUpload || $canAddVideo ) && $onlyRead );

		return [
			'has-create' => $canCreate && $canEdit,
			'create-text' => $createText,
			'create-flush' => !$isAnon && $canViewAdminLinks,
			'create-title' => $this->context->msg( 'cosmosbeta-add-new-page-title' )->text(),
			'has-recentchanges' => !$isAnon || $onlyRead,
			'recentchanges-text' => $onlyRead ? $recentChanges->text() : null,
			'recentchanges-url' => $recentChangesUrl,
			'recentchanges-title' => ucwords( $recentChanges->text() ),
			'has-admin' => $canViewDashboard || ( $canViewAdminLinks && $this->extensionRegistry->isLoaded( 'Admin Links' ) ),
			'admin-url' => SpecialPage::getTitleFor( $canViewDashboard ? 'AdminDashboard' : 'AdminLinks' )->getFullURL(),
			'admin-title' => $canViewDashboard ?
				$this->context->msg( 'cosmosbeta-admindashboard' )->text() :
				ucwords( $this->context->msg( 'adminlinks' )->text() ),
			'has-more' => $hasMore,
			'has-colormode' => $hasColorMode,
			'colormode-icon' => $mode === 'dark' ? 'bright' : 'moon',
			'colormode-text' => $this->context->msg( "cosmosbeta-colormode-switch-$mode" )->text(),
			'colormode-url' => $user->isNamed() ?
				SpecialPage::getTitleFor( 'Preferences' )->getLocalURL() . '#mw-prefsection-rendering' :
				'#',
			'has-more-image' => $canUpload,
			'more-image-url' => $uploadUrl,
			'msg-more-image' => $this->context->msg( 'cosmosbeta-add-new-image' )->text(),
			'has-more-video' => $canAddVideo,
			'more-video-url' => SpecialPage::getTitleFor( 'AddVideo' )->getFullURL(),
			'msg-more-video' => $this->context->msg( 'cosmosbeta-add-new-video' )->text(),
			'msg-recentchanges' => $recentChanges->text(),
		];
	}
}
