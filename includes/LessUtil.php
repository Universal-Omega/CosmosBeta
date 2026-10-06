<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos;

use function array_slice;
use function count;
use function ctype_xdigit;
use function hexdec;
use function in_array;
use function is_numeric;
use function max;
use function min;
use function preg_match;
use function preg_replace;
use function preg_split;
use function round;
use function str_ends_with;
use function str_repeat;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;
use const PREG_SPLIT_NO_EMPTY;

class LessUtil {

	// Content switches to light text at the point where both colors read the same.
	public const float CONTENT_THRESHOLD = 0.179;

	// Chrome like the header and banner prefers light text a bit longer.
	public const float CHROME_THRESHOLD = 0.3;

	public function __construct(
		private readonly CosmosConfig $cosmosConfig,
	) {
	}

	/**
	 * Whether light text reads better on a theme color, judged by what is actually seen.
	 * Colors with an alpha, or with an opacity setting like the content and footer ones,
	 * are blended with what sits behind them before they are compared.
	 */
	public function isDark( string $slot, string $mode, float $threshold ): bool {
		$visible = $this->getVisibleColor( $slot, $mode );
		return $visible === null || self::isColorDark( $visible['r'], $visible['g'], $visible['b'], $threshold );
	}

	/**
	 * @return array{r: int, g: int, b: int}|null The color as seen, null when nothing is seen
	 */
	private function getVisibleColor( string $slot, string $mode ): ?array {
		$color = self::parseColor( $this->cosmosConfig->getColor( $slot, $mode ) );
		$alpha = $color === null ? 0.0 : (float)$color['a'] * $this->getOpacity( $slot );
		if ( $color !== null && $alpha >= 1.0 ) {
			return [ 'r' => $color['r'], 'g' => $color['g'], 'b' => $color['b'] ];
		}

		// Buttons sit on the content, everything else sits on the page background.
		$backdrop = match ( $slot ) {
			'body' => null,
			'button' => $this->getVisibleColor( 'content', $mode ),
			default => $this->getVisibleColor( 'body', $mode ),
		};

		if ( $backdrop === null ) {
			return $color !== null && $alpha > 0.0 ?
				[ 'r' => $color['r'], 'g' => $color['g'], 'b' => $color['b'] ] :
				null;
		}

		return $color === null ? $backdrop : self::blend( $color, $alpha, $backdrop );
	}

	/** Opacity setting of a theme color on top of the alpha of the color itself */
	private function getOpacity( string $slot ): float {
		return match ( $slot ) {
			'content' => $this->cosmosConfig->getContentOpacityLevel() / 100,
			'footer' => $this->cosmosConfig->getFooterOpacity() / 100,
			default => 1.0,
		};
	}

	/**
	 * @param array{r: int, g: int, b: int} $color
	 * @param array{r: int, g: int, b: int} $backdrop
	 * @return array{r: int, g: int, b: int} The color laid over the backdrop
	 */
	public static function blend( array $color, float $alpha, array $backdrop ): array {
		$alpha = max( 0.0, min( 1.0, $alpha ) );
		return [
			'r' => (int)round( $color['r'] * $alpha + $backdrop['r'] * ( 1 - $alpha ) ),
			'g' => (int)round( $color['g'] * $alpha + $backdrop['g'] * ( 1 - $alpha ) ),
			'b' => (int)round( $color['b'] * $alpha + $backdrop['b'] * ( 1 - $alpha ) ),
		];
	}

	/** Whether white text reads better than black text on an opaque color */
	public static function isColorDark( int $red, int $green, int $blue, float $threshold ): bool {
		return self::getLuminance( $red, $green, $blue ) < $threshold;
	}

	/** Relative luminance as defined by WCAG, 0 for black and 1 for white */
	private static function getLuminance( int $red, int $green, int $blue ): float {
		$channels = [];
		foreach ( [ $red, $green, $blue ] as $value ) {
			$value /= 255;
			$channels[] = $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
		}

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	public static function colorNameToHex( string $colorName ): string {
		// standard 147 HTML color names
		$colors = [
			'aliceblue' => '#f0f8ff',
			'antiquewhite' => '#faebd7',
			'aqua' => '#00ffff',
			'aquamarine' => '#7fffd4',
			'azure' => '#f0ffff',
			'beige' => '#f5f5dc',
			'bisque' => '#ffe4c4',
			'black' => '#000000',
			'blanchedalmond' => '#ffebcd',
			'blue' => '#0000ff',
			'blueviolet' => '#8a2be2',
			'brown' => '#a52a2a',
			'burlywood' => '#deb887',
			'cadetblue' => '#5f9ea0',
			'chartreuse' => '#7fff00',
			'chocolate' => '#d2691e',
			'coral' => '#ff7f50',
			'cornflowerblue' => '#6495ed',
			'cornsilk' => '#fff8dc',
			'crimson' => '#dc143c',
			'cyan' => '#00ffff',
			'darkblue' => '#00008b',
			'darkcyan' => '#008b8b',
			'darkgoldenrod' => '#b8860b',
			'darkgray' => '#a9a9a9',
			'darkgreen' => '#006400',
			'darkgrey' => '#a9a9a9',
			'darkkhaki' => '#bdb76b',
			'darkmagenta' => '#8b008b',
			'darkolivegreen' => '#556b2f',
			'darkorange' => '#ff8c00',
			'darkorchid' => '#9932cc',
			'darkred' => '#8b0000',
			'darksalmon' => '#e9967a',
			'darkseagreen' => '#8fbc8f',
			'darkslateblue' => '#483d8b',
			'darkslategray' => '#2f4f4f',
			'darkslategrey' => '#2f4f4f',
			'darkturquoise' => '#00ced1',
			'darkviolet' => '#9400d3',
			'deeppink' => '#ff1493',
			'deepskyblue' => '#00bfff',
			'dimgray' => '#696969',
			'dimgrey' => '#696969',
			'dodgerblue' => '#1e90ff',
			'firebrick' => '#b22222',
			'floralwhite' => '#fffaf0',
			'forestgreen' => '#228b22',
			'fuchsia' => '#ff00ff',
			'gainsboro' => '#dcdcdc',
			'ghostwhite' => '#f8f8ff',
			'gold' => '#ffd700',
			'goldenrod' => '#daa520',
			'gray' => '#808080',
			'green' => '#008000',
			'greenyellow' => '#adff2f',
			'grey' => '#808080',
			'honeydew' => '#f0fff0',
			'hotpink' => '#ff69b4',
			'indianred' => '#cd5c5c',
			'indigo' => '#4b0082',
			'ivory' => '#fffff0',
			'khaki' => '#f0e68c',
			'lavender' => '#e6e6fa',
			'lavenderblush' => '#fff0f5',
			'lawngreen' => '#7cfc00',
			'lemonchiffon' => '#fffacd',
			'lightblue' => '#add8e6',
			'lightcoral' => '#f08080',
			'lightcyan' => '#e0ffff',
			'lightgoldenrodyellow' => '#fafad2',
			'lightgray' => '#d3d3d3',
			'lightgreen' => '#90ee90',
			'lightgrey' => '#d3d3d3',
			'lightpink' => '#ffb6c1',
			'lightsalmon' => '#ffa07a',
			'lightseagreen' => '#20b2aa',
			'lightskyblue' => '#87cefa',
			'lightslategray' => '#778899',
			'lightslategrey' => '#778899',
			'lightsteelblue' => '#b0c4de',
			'lightyellow' => '#ffffe0',
			'lime' => '#00ff00',
			'limegreen' => '#32cd32',
			'linen' => '#faf0e6',
			'magenta' => '#ff00ff',
			'maroon' => '#800000',
			'mediumaquamarine' => '#66cdaa',
			'mediumblue' => '#0000cd',
			'mediumorchid' => '#ba55d3',
			'mediumpurple' => '#9370d0',
			'mediumseagreen' => '#3cb371',
			'mediumslateblue' => '#7b68ee',
			'mediumspringgreen' => '#00fa9a',
			'mediumturquoise' => '#48d1cc',
			'mediumvioletred' => '#c71585',
			'midnightblue' => '#191970',
			'mintcream' => '#f5fffa',
			'mistyrose' => '#ffe4e1',
			'moccasin' => '#ffe4b5',
			'navajowhite' => '#ffdead',
			'navy' => '#000080',
			'oldlace' => '#fdf5e6',
			'olive' => '#808000',
			'olivedrab' => '#6b8e23',
			'orange' => '#ffa500',
			'orangered' => '#ff4500',
			'orchid' => '#da70d6',
			'palegoldenrod' => '#eee8aa',
			'palegreen' => '#98fb98',
			'paleturquoise' => '#afeeee',
			'palevioletred' => '#db7093',
			'papayawhip' => '#ffefd5',
			'peachpuff' => '#ffdab9',
			'peru' => '#cd853f',
			'pink' => '#ffc0cb',
			'plum' => '#dda0dd',
			'powderblue' => '#b0e0e6',
			'purple' => '#800080',
			'red' => '#ff0000',
			'rosybrown' => '#bc8f8f',
			'royalblue' => '#4169e1',
			'saddlebrown' => '#8b4513',
			'salmon' => '#fa8072',
			'sandybrown' => '#f4a460',
			'seagreen' => '#2e8b57',
			'seashell' => '#fff5ee',
			'sienna' => '#a0522d',
			'silver' => '#c0c0c0',
			'skyblue' => '#87ceeb',
			'slateblue' => '#6a5acd',
			'slategray' => '#708090',
			'slategrey' => '#708090',
			'snow' => '#fffafa',
			'springgreen' => '#00ff7f',
			'steelblue' => '#4682b4',
			'tan' => '#d2b48c',
			'teal' => '#008080',
			'thistle' => '#d8bfd8',
			'tomato' => '#ff6347',
			'turquoise' => '#40e0d0',
			'violet' => '#ee82ee',
			'wheat' => '#f5deb3',
			'white' => '#ffffff',
			'whitesmoke' => '#f5f5f5',
			'yellow' => '#ffff00',
			'yellowgreen' => '#9acd32',
		];

		$key = strtolower( trim( $colorName ) );
		return $colors[$key] ?? $colorName;
	}

	/**
	 * Parses a CSS color into channels. Supports hex, rgb(), rgba(),
	 * color names and transparent. Returns null for anything else.
	 *
	 * @return array|null r, g, b as 0 to 255 and a as 0 to 1
	 */
	public static function parseColor( string $color ): ?array {
		$color = strtolower( trim( $color ) );
		if ( $color === 'transparent' ) {
			return [ 'r' => 0, 'g' => 0, 'b' => 0, 'a' => 0.0 ];
		}

		if ( preg_match( '/^[a-z]+$/', $color ) ) {
			$color = self::colorNameToHex( $color );
		}

		if ( $color !== '' && $color[0] === '#' ) {
			$hex = substr( $color, 1 );
			if ( !ctype_xdigit( $hex ) || !in_array( strlen( $hex ), [ 3, 4, 6, 8 ], true ) ) {
				return null;
			}

			if ( strlen( $hex ) <= 4 ) {
				$hex = preg_replace( '/./', '$0$0', $hex );
			}

			$alpha = strlen( $hex ) === 8 ? hexdec( substr( $hex, 6, 2 ) ) / 255 : 1.0;

			return [
				'r' => hexdec( substr( $hex, 0, 2 ) ),
				'g' => hexdec( substr( $hex, 2, 2 ) ),
				'b' => hexdec( substr( $hex, 4, 2 ) ),
				'a' => $alpha,
			];
		}

		if ( !preg_match( '/^rgba?\(([^)]*)\)$/', $color, $matches ) ) {
			return null;
		}

		$parts = preg_split( '/[\s,\/]+/', trim( $matches[1] ), -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $parts ) < 3 || count( $parts ) > 4 ) {
			return null;
		}

		$channels = [];
		foreach ( array_slice( $parts, 0, 3 ) as $part ) {
			$isPercent = str_ends_with( $part, '%' );
			$number = $isPercent ? substr( $part, 0, -1 ) : $part;

			if ( !is_numeric( $number ) ) {
				return null;
			}

			$value = $isPercent ? (float)$number * 2.55 : (float)$number;
			$channels[] = (int)round( max( 0, min( 255, $value ) ) );
		}

		$alpha = 1.0;
		if ( isset( $parts[3] ) ) {
			$isPercent = str_ends_with( $parts[3], '%' );
			$number = $isPercent ? substr( $parts[3], 0, -1 ) : $parts[3];

			if ( !is_numeric( $number ) ) {
				return null;
			}

			$alpha = max( 0.0, min( 1.0, $isPercent ? (float)$number / 100 : (float)$number ) );
		}

		return [ 'r' => $channels[0], 'g' => $channels[1], 'b' => $channels[2], 'a' => $alpha ];
	}

	/** @return array with r, g, and b keys */
	public static function hexToRgb( string $hex ): array {
		$hex = str_replace( '#', '', $hex );
		$length = strlen( $hex );

		if ( $length === 6 ) {
			$rgb = [
				'r' => hexdec( substr( $hex, 0, 2 ) ),
				'g' => hexdec( substr( $hex, 2, 2 ) ),
				'b' => hexdec( substr( $hex, 4, 2 ) ),
			];
		} elseif ( $length === 3 ) {
			$rgb = [
				'r' => hexdec( str_repeat( substr( $hex, 0, 1 ), 2 ) ),
				'g' => hexdec( str_repeat( substr( $hex, 1, 1 ), 2 ) ),
				'b' => hexdec( str_repeat( substr( $hex, 2, 1 ), 2 ) ),
			];
		} else {
			$rgb = [ 'r' => 0, 'g' => 0, 'b' => 0 ];
		}

		return $rgb;
	}
}
