<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Components;

use MediaWiki\Context\IContextSource;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skins\CosmosBeta\CosmosConfig;
use MediaWiki\SpecialPage\SpecialPage;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_ends_with;

class ChromeComponent {

	public function __construct(
		private readonly IContextSource $context,
		private readonly CosmosConfig $config,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
	}

	public function getFooterData( array $footer ): array {
		$settings = $this->config->getFooterSettings();

		return [
			'array-info' => $this->filterLinks( $footer['data-info']['array-items'] ?? [], $settings['hiddenLinks'] ),
			'array-places' => $this->filterLinks( $footer['data-places']['array-items'] ?? [], $settings['hiddenLinks'] ),
			'array-icons' => $settings['showIcons'] ? $this->getIcons( $footer['data-icons']['array-items'] ?? [] ) : [],
		];
	}

	public function getToolbarData( array $sidebar ): ?array {
		$settings = $this->config->getToolbarSettings();
		$portlet = PortletReader::findPortlet( $sidebar, 'p-tb' );

		if ( !$settings['enabled'] ) {
			return null;
		}

		$items = [];
		foreach ( $portlet['array-items'] ?? [] as $item ) {
			$id = $item['attrs']['id'] ?? '';
			$hidden = false;

			foreach ( $settings['hiddenItems'] as $name ) {
				$hidden = $hidden || $id === "t-$name";
			}

			if ( !$hidden ) {
				$items[] = [ 'html-item' => $item['html-item'] ];
			}
		}

		if (
			$this->extensionRegistry->isLoaded( 'CreateRedirect' ) &&
			!in_array( 'createredirect', $settings['hiddenItems'], true )
		) {
			$action = $this->context->getRequest()->getText( 'action', 'view' );
			$title = $this->context->getTitle();

			if ( $action === 'view' || $action === 'purge' || !$title->isSpecialPage() ) {
				$items[] = [
					'redirect-url' => SpecialPage::getTitleFor( 'CreateRedirect', $title->getPrefixedText() )->getLocalURL(),
					'redirect-text' => $this->context->msg( 'createredirect' )->text(),
				];
			}
		}

		return [
			'style' => $settings['style'],
			'array-items' => $items,
		];
	}

	private function getIcons( array $items ): array {
		$icons = [];

		foreach ( $items as $item ) {
			$id = (string)( $item['attrs']['id'] ?? '' );

			if ( preg_match( '/^<li[^>]*>(.*)<\/li>\s*$/s', $item['html-item'] ?? '', $matches ) ) {
				$icons[] = [
					'name' => preg_replace( '/^footer-|ico$/', '', $id ),
					'html' => $matches[1],
				];
			}
		}

		return $icons;
	}

	private function filterLinks( array $items, array $hidden ): array {
		$links = [];

		foreach ( $items as $item ) {
			$id = (string)( $item['attrs']['id'] ?? '' );
			$skip = false;

			foreach ( $hidden as $name ) {
				$skip = $skip || str_ends_with( $id, "-$name" );
			}

			if ( !$skip && preg_match( '/^<li[^>]*>(.*)<\/li>\s*$/s', $item['html-item'] ?? '', $matches ) ) {
				$links[] = [
					'id' => $id,
					'html' => preg_replace( '/\s+$/', '', $matches[1] ),
				];
			}
		}

		return $links;
	}
}
