<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Components;

use function array_merge;
use function str_starts_with;
use function substr;

final class PortletReader {

	/**
	 * Flattens the items of the given portlets into one list keyed by action name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function getItems( array $portlets, array $names ): array {
		$items = [];
		foreach ( $names as $name ) {
			foreach ( $portlets[$name]['array-items'] ?? [] as $item ) {
				$id = (string)( $item['id'] ?? '' );
				$key = str_starts_with( $id, 'ca-' ) ? substr( $id, 3 ) : $id;
				$link = $item['array-links'][0] ?? [];
				$attributes = [];

				foreach ( $link['array-attributes'] ?? [] as $attribute ) {
					$attributes[$attribute['key']] = $attribute['value'];
				}

				$items[$key] ??= [
					'id' => $id,
					'class' => $item['class'] ?? '',
					'text' => (string)( $link['text'] ?? '' ),
					'href' => $attributes['href'] ?? null,
					'title' => $attributes['title'] ?? '',
					'lang' => $attributes['lang'] ?? null,
					'hreflang' => $attributes['hreflang'] ?? null,
					'html-item' => (string)( $item['html-item'] ?? '' ),
				];
			}
		}

		return $items;
	}

	public static function findPortlet( array $sidebar, string $id ): ?array {
		$portlets = array_merge( [ $sidebar['data-portlets-first'] ?? null ], $sidebar['array-portlets-rest'] ?? [] );
		foreach ( $portlets as $portlet ) {
			if ( ( $portlet['id'] ?? null ) === $id ) {
				return $portlet;
			}
		}

		return null;
	}
}
