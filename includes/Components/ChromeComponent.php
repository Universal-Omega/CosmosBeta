<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Components;

use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use function in_array;

class ChromeComponent {

	public function __construct(
		private readonly EffectiveTheme $theme,
	) {
	}

	/** @return array<string, mixed> */
	public function getFooterData( array $footer ): array {
		$settings = $this->theme->getFooterSettings();
		return [
			'array-info' => $this->getLinks( $footer['data-info']['array-items'] ?? [], $settings['hiddenLinks'] ),
			'array-places' => $this->getLinks( $footer['data-places']['array-items'] ?? [], $settings['hiddenLinks'] ),
			'array-icons' => $settings['showIcons']
				? $this->getIcons( $footer['data-icons']['array-items'] ?? [] )
				: [],
		];
	}

	/** @return list<array<string, mixed>> */
	public function getToolItems( array $sidebar ): array {
		$settings = $this->theme->getToolbarSettings();
		$items = [];
		$portlet = PortletReader::findPortlet( $sidebar, 'p-tb' );

		foreach ( $portlet['array-items'] ?? [] as $item ) {
			if ( !in_array( (string)( $item['name'] ?? '' ), $settings['hiddenItems'], true ) ) {
				$items[] = [ 'html-item' => $item['html-item'] ];
			}
		}

		return $items;
	}

	/** @return ?array<string, mixed> */
	public function getToolbarData( array $sidebar ): ?array {
		$settings = $this->theme->getToolbarSettings();
		if ( !$settings['enabled'] || $settings['style'] === 'rail' ) {
			return null;
		}

		return [
			'style' => $settings['style'],
			'array-items' => $this->getToolItems( $sidebar ),
		];
	}

	/** @return list<array<string, mixed>> */
	private function getLinks( array $items, array $hidden ): array {
		$links = [];
		foreach ( $items as $item ) {
			if ( !in_array( (string)( $item['name'] ?? '' ), $hidden, true ) ) {
				$links[] = [
					'id' => $item['id'] ?? null,
					'html' => $item['html'] ?? '',
				];
			}
		}

		return $links;
	}

	/** @return list<array<string, mixed>> */
	private function getIcons( array $items ): array {
		$icons = [];
		foreach ( $items as $item ) {
			$icons[] = [
				'name' => $item['name'] ?? '',
				'html' => $item['html'] ?? '',
			];
		}

		return $icons;
	}
}
