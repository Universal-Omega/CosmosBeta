<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

class ThemePresets {

	/**
	 * Built in palettes. Each one is meant for a single color mode.
	 *
	 * @return array[]
	 */
	public static function getAll(): array {
		return [
			'cosmos-light' => [
				'mode' => ThemeSettings::MODE_LIGHT,
				'colors' => ThemeSettings::LIGHT_DEFAULTS,
			],
			'sky' => [
				'mode' => ThemeSettings::MODE_LIGHT,
				'colors' => [
					'banner' => '#2a6fb0',
					'header' => '#3a86c8',
					'body' => '#dcebf7',
					'content' => '#ffffff',
					'button' => '#2a6fb0',
					'link' => '#1b5fa8',
					'footer' => '#2a6fb0',
					'toolbar' => '#103a63',
				],
			],
			'forest' => [
				'mode' => ThemeSettings::MODE_LIGHT,
				'colors' => [
					'banner' => '#2e6b3f',
					'header' => '#3c8550',
					'body' => '#e4efe3',
					'content' => '#fbfdfb',
					'button' => '#2e6b3f',
					'link' => '#1f6b3a',
					'footer' => '#2e6b3f',
					'toolbar' => '#12301b',
				],
			],
			'sunset' => [
				'mode' => ThemeSettings::MODE_LIGHT,
				'colors' => [
					'banner' => '#c4512d',
					'header' => '#e0703f',
					'body' => '#f6e3d6',
					'content' => '#fffaf6',
					'button' => '#c4512d',
					'link' => '#a8401f',
					'footer' => '#c4512d',
					'toolbar' => '#4a1d0e',
				],
			],
			'lavender' => [
				'mode' => ThemeSettings::MODE_LIGHT,
				'colors' => [
					'banner' => '#6a4fb3',
					'header' => '#8269c8',
					'body' => '#e9e4f5',
					'content' => '#fdfcff',
					'button' => '#6a4fb3',
					'link' => '#563c9c',
					'footer' => '#6a4fb3',
					'toolbar' => '#241a45',
				],
			],
			'cosmos-dark' => [
				'mode' => ThemeSettings::MODE_DARK,
				'colors' => ThemeSettings::DARK_DEFAULTS,
			],
			'midnight' => [
				'mode' => ThemeSettings::MODE_DARK,
				'colors' => [
					'banner' => '#0b1a33',
					'header' => '#12284d',
					'body' => '#050b17',
					'content' => '#0d1730',
					'button' => '#2f6fd1',
					'link' => '#7fb2ff',
					'footer' => '#0b1a33',
					'toolbar' => '#000000',
				],
			],
			'ember' => [
				'mode' => ThemeSettings::MODE_DARK,
				'colors' => [
					'banner' => '#2a1410',
					'header' => '#3d1d16',
					'body' => '#120a08',
					'content' => '#1c100d',
					'button' => '#c4512d',
					'link' => '#ff9a6b',
					'footer' => '#2a1410',
					'toolbar' => '#000000',
				],
			],
			'moss' => [
				'mode' => ThemeSettings::MODE_DARK,
				'colors' => [
					'banner' => '#13261a',
					'header' => '#1c3a27',
					'body' => '#08120c',
					'content' => '#0f1d14',
					'button' => '#3c8550',
					'link' => '#7fd69a',
					'footer' => '#13261a',
					'toolbar' => '#000000',
				],
			],
		];
	}
}
