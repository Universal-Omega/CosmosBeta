<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\AdminDashboard;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skins\CosmosBeta\CosmosNavigation;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use function array_filter;
use function array_values;
use function preg_match;
use function trim;
use const NS_MEDIAWIKI;

final readonly class DashboardControlRegistry {

	public const array CONSTRUCTOR_OPTIONS = [
		MainConfigNames::EnableUploads,
	];

	public function __construct(
		private ExtensionRegistry $extensionRegistry,
		private PermissionManager $permissionManager,
		private ServiceOptions $options,
		private SpecialPageFactory $specialPageFactory,
		private TitleFactory $titleFactory,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * @return list<DashboardControl> Controls the user is allowed to use, in display order
	 */
	public function getControls( User $user, MessageLocalizer $localizer ): array {
		$wiki = DashboardSection::Wiki;
		$community = DashboardSection::Community;
		$content = DashboardSection::Content;
		$canCreate = $this->permissionManager->userHasRight( $user, 'createpage' ) &&
			$this->permissionManager->userHasRight( $user, 'edit' );

		return array_values( array_filter( [
			$this->special( 'themedesigner', $wiki, 'CosmosBetaThemeDesigner', $user ),
			$this->special( 'recentchanges', $wiki, 'Recentchanges', $user ),
			$this->interfacePage( 'navigation', $wiki, CosmosNavigation::MESSAGE, $user ),
			$this->interfacePage( 'tagline', $wiki, 'cosmosbeta-tagline', $user ),
			$this->interfacePage( 'css', $wiki, 'Cosmosbeta.css', $user ),
			$this->interfacePage( 'js', $wiki, 'Cosmosbeta.js', $user ),
			$this->extensionRegistry->isLoaded( 'ManageWiki' ) ?
				$this->special( 'managewiki', $wiki, 'ManageWiki', $user ) :
				null,
			$this->special( 'analytics', $wiki, 'Analytics', $user ),

			$this->special( 'listusers', $community, 'Listusers', $user ),
			$this->special( 'userrights', $community, 'Userrights', $user ),
			$this->special( 'block', $community, 'Block', $user ),
			$this->interfacePage( 'sitenotice', $community, 'Sitenotice', $user ),
			$this->external( 'help', $community, $localizer ),

			$this->special( 'categories', $content, 'Categories', $user ),
			$canCreate ? new DashboardControl( 'addpage', $content, '#create-article', opensCreateDialog: true ) : null,
			$this->options->get( MainConfigNames::EnableUploads ) ?
				$this->special( 'upload', $content, 'Upload', $user ) :
				null,
			$this->special( 'multipleupload', $content, 'MultipleUpload', $user ),
			$this->extensionRegistry->isLoaded( 'Video' ) ?
				$this->special( 'video', $content, 'AddVideo', $user ) :
				null,
			$this->special( 'blog', $content, 'CreateBlogPage', $user ),
			$this->special( 'allpages', $content, 'Allpages', $user ),
			$this->special( 'newpages', $content, 'Newpages', $user ),
			$this->special( 'listfiles', $content, 'Listfiles', $user ),
		] ) );
	}

	private function special( string $id, DashboardSection $section, string $name, User $user ): ?DashboardControl {
		$page = $this->specialPageFactory->getPage( $name );

		if ( !$page || !$page->userCanExecute( $user ) ) {
			return null;
		}

		return new DashboardControl( $id, $section, $page->getPageTitle()->getFullURL(), specialPage: $name );
	}

	private function interfacePage( string $id, DashboardSection $section, string $text, User $user ): ?DashboardControl {
		$title = $this->titleFactory->makeTitleSafe( NS_MEDIAWIKI, $text );

		if ( !$title || !$this->permissionManager->userCan( 'edit', $user, $title ) ) {
			return null;
		}

		return new DashboardControl( $id, $section, $title->getFullURL( [ 'action' => 'edit' ] ) );
	}

	private function external( string $id, DashboardSection $section, MessageLocalizer $localizer ): ?DashboardControl {
		$url = trim( $localizer->msg( "cosmosbeta-admindashboard-control-$id-url" )->inContentLanguage()->plain() );

		if ( !preg_match( '#^https?://#i', $url ) ) {
			return null;
		}

		return new DashboardControl( $id, $section, $url, isExternal: true );
	}
}
