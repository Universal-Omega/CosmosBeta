<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Components;

use MediaWiki\Context\IContextSource;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use function array_filter;
use function array_map;
use function array_values;

final readonly class FandomRailComponent {

	public function __construct(
		private IContextSource $context,
		private PermissionManager $permissionManager,
	) {
	}

	/**
	 * @param array[] $tree Navigation tree of the wiki
	 */
	public function getTemplateData( string $mainPageUrl, array $tree ): ?array {
		$user = $this->context->getUser();

		if ( !$this->permissionManager->userHasRight( $user, 'read' ) ) {
			return null;
		}

		$registered = $user->isRegistered();
		$title = $this->context->getTitle();
		$can = fn ( string $right ): bool => $this->permissionManager->userHasRight( $user, $right );

		$top = [
			$this->item( 'home', 'home', $mainPageUrl ),
			$this->item( 'explore', 'globe', SpecialPage::getTitleFor( 'Recentchanges' )->getLocalURL(), $this->getExploreLinks( $tree ) ),
			$registered ? $this->item( 'saved', 'bookmark', SpecialPage::getTitleFor( 'Watchlist' )->getLocalURL() ) : null,
			$registered ?
				$this->item( 'contributions', 'edit', SpecialPage::getTitleFor( 'Contributions', $user->getName() )->getLocalURL() ) :
				null,
			$title->canExist() && $title->exists() ?
				$this->item( 'history', 'history', $title->getLocalURL( [ 'action' => 'history' ] ) ) :
				null,
			$this->item( 'utilities', 'specialPages', SpecialPage::getTitleFor( 'Specialpages' )->getLocalURL() ),
		];

		$more = array_filter( [
			$can( 'cosmosbeta-admindashboard' ) ? $this->link( 'cosmosbeta-admindashboard', SpecialPage::getTitleFor( 'AdminDashboard' ) ) : null,
			$can( 'cosmosbeta-themedesigner' ) ? $this->link( 'cosmosbetathemedesigner', SpecialPage::getTitleFor( 'CosmosBetaThemeDesigner' ) ) : null,
			$can( 'upload' ) ? $this->link( 'upload', SpecialPage::getTitleFor( 'Upload' ) ) : null,
		] );

		$bottom = [
			$registered ? $this->item( 'settings', 'settings', SpecialPage::getTitleFor( 'Preferences' )->getLocalURL() ) : null,
			$more ? $this->item( 'more', 'ellipsis', '#', array_values( $more ) ) : null,
		];

		return [
			'msg-label' => $this->context->msg( 'cosmosbeta-fandomrail-label' )->text(),
			'array-top' => array_values( array_filter( $top ) ),
			'array-bottom' => array_values( array_filter( $bottom ) ),
		];
	}

	/**
	 * @param array[] $tree
	 * @return list<array{text: string, href: string}>
	 */
	private function getExploreLinks( array $tree ): array {
		foreach ( $tree as $node ) {
			if ( !( $node['is-explore'] ?? false ) ) {
				continue;
			}

			return array_map(
				static fn ( array $child ): array => [ 'text' => (string)$child['text'], 'href' => (string)$child['href'] ],
				$node['array-children'] ?? []
			);
		}

		return [];
	}

	/**
	 * @param list<array{text: string, href: string}> $children
	 */
	private function item( string $id, string $icon, string $url, array $children = [] ): array {
		return [
			'id' => "skin-cosmos-fandomrail-$id",
			'icon' => $icon,
			'label' => $this->context->msg( "cosmosbeta-fandomrail-$id" )->text(),
			'url' => $url,
			'has-children' => $children !== [],
			'array-children' => $children,
		];
	}

	/**
	 * @return array{text: string, href: string}
	 */
	private function link( string $message, Title $title ): array {
		return [ 'text' => $this->context->msg( $message )->text(), 'href' => $title->getLocalURL() ];
	}
}
