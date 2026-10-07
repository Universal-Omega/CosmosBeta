<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Legacy;

use function array_keys;
use function array_sum;
use function in_array;
use function ksort;
use function ltrim;
use function max;
use function min;
use function preg_match;
use function preg_replace_callback;
use function sort;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Rewrites old Cosmos class names in CSS selectors and in the
 * class and selector arguments of JavaScript to the skin-cosmos- names.
 */
final class LegacyConverter {

	private const SELECTOR_CALL = '/(?:\$|jQuery|\.(?:find|closest|is|not|filter|has|children|parents|parentsUntil|parent|siblings|next|prev|nextAll|prevAll|nextUntil|prevUntil|querySelector|querySelectorAll|matches))\s*\(\s*$/';
	private const DELEGATE_CALL = '/\.(?:on|off|one|delegate)\s*\(\s*(?:\'[^\']*\'|"[^"]*")\s*,\s*$/';
	private const CLASS_CALL = '/(?:\.(?:addClass|removeClass|toggleClass|hasClass|getElementsByClassName)|classList\s*\.\s*(?:add|remove|toggle|contains|replace))\s*\(\s*(?:(?:\'[^\']*\'|"[^"]*")\s*,\s*)*$/';
	private const CLASS_ASSIGN = '/(?:\.className\s*=|\bclass[\'"]?\s*:|setAttribute\s*\(\s*[\'"]class[\'"]\s*,)\s*$/';

	/** @var array<string,int> */
	private array $replacements = [];

	/** @var array<string,string> */
	private array $mappedTo = [];

	/** @var array<string,true> */
	private array $iconClasses = [];

	public function convertCss( string $css ): LegacyConversionResult {
		$this->reset();

		$out = '';
		$length = strlen( $css );
		$segment = 0;
		$i = 0;

		while ( $i < $length ) {
			$char = $css[$i];

			if ( $char === '/' && ( $css[$i + 1] ?? '' ) === '*' ) {
				$end = strpos( $css, '*/', $i + 2 );
				$i = $end === false ? $length : $end + 2;
			} elseif ( $char === '"' || $char === "'" ) {
				$i = $this->skipString( $css, $i );
			} elseif ( $char === '{' ) {
				$out .= $this->convertPrelude( substr( $css, $segment, $i - $segment ) ) . '{';
				$segment = ++$i;
			} elseif ( $char === ';' || $char === '}' ) {
				$out .= substr( $css, $segment, $i + 1 - $segment );
				$segment = ++$i;
			} else {
				$i++;
			}
		}

		return $this->result( $out . substr( $css, $segment ) );
	}

	public function convertJs( string $js ): LegacyConversionResult {
		$this->reset();

		$out = '';
		$length = strlen( $js );
		$i = 0;
		$last = 0;

		while ( $i < $length ) {
			$char = $js[$i];
			$next = $js[$i + 1] ?? '';

			if ( $char === '/' && $next === '/' ) {
				$end = strpos( $js, "\n", $i );
				$i = $end === false ? $length : $end;
			} elseif ( $char === '/' && $next === '*' ) {
				$end = strpos( $js, '*/', $i + 2 );
				$i = $end === false ? $length : $end + 2;
			} elseif ( $char === '/' && $this->startsRegex( $js, $i ) ) {
				$i = $this->skipRegex( $js, $i );
			} elseif ( $char === '"' || $char === "'" || $char === '`' ) {
				$end = $this->skipString( $js, $i );
				$literal = substr( $js, $i, $end - $i );
				$before = substr( $js, max( 0, $i - 160 ), min( 160, $i ) );
				$out .= substr( $js, $last, $i - $last ) . $this->convertLiteral( $literal, $before );
				$last = $i = $end;
			} else {
				$i++;
			}
		}

		return $this->result( $out . substr( $js, $last ) );
	}

	private function reset(): void {
		$this->replacements = [];
		$this->mappedTo = [];
		$this->iconClasses = [];
	}

	private function result( string $output ): LegacyConversionResult {
		ksort( $this->replacements );
		ksort( $this->mappedTo );
		$icons = array_keys( $this->iconClasses );
		sort( $icons );

		return new LegacyConversionResult( $output, $this->replacements, $this->mappedTo, $icons );
	}

	private function convertLiteral( string $literal, string $before ): string {
		$quote = $literal[0];
		if ( strlen( $literal ) < 2 || ( $quote === '`' && str_contains( $literal, '${' ) ) ) {
			return $literal;
		}

		$body = substr( $literal, 1, -1 );

		if ( preg_match( self::CLASS_CALL, $before ) || preg_match( self::CLASS_ASSIGN, $before ) ) {
			$body = $this->convertClassList( $body );
		} elseif ( preg_match( self::SELECTOR_CALL, $before ) || preg_match( self::DELEGATE_CALL, $before ) ) {
			$body = $this->convertPrelude( $body );
		} elseif ( str_contains( $body, '<' ) && str_contains( $body, 'class' ) ) {
			$body = $this->convertMarkup( $body );
		}

		return $quote . $body . $quote;
	}

	private function convertMarkup( string $html ): string {
		return preg_replace_callback(
			'/(\sclass\s*=\s*(\\\\?[\'"]))([^\'"\\\\]*)/',
			fn ( array $m ): string => $m[1] . $this->convertClassList( $m[3] ),
			$html
		) ?? $html;
	}

	private function convertClassList( string $list ): string {
		return preg_replace_callback(
			'/[^\s]+/',
			fn ( array $m ): string => $this->mapClass( $m[0] ),
			$list
		) ?? $list;
	}

	private function convertPrelude( string $prelude ): string {
		if ( str_starts_with( ltrim( $prelude ), '@' ) ) {
			return $prelude;
		}

		$out = '';
		$length = strlen( $prelude );
		$i = 0;
		$last = 0;

		while ( $i < $length ) {
			$char = $prelude[$i];

			if ( $char === '/' && ( $prelude[$i + 1] ?? '' ) === '*' ) {
				$end = strpos( $prelude, '*/', $i + 2 );
				$i = $end === false ? $length : $end + 2;
			} elseif ( $char === '"' || $char === "'" ) {
				$i = $this->skipString( $prelude, $i );
			} elseif ( $char === '.' && preg_match( '/\G\.(-?[A-Za-z_][\w-]*)/', $prelude, $m, 0, $i ) ) {
				$out .= substr( $prelude, $last, $i + 1 - $last ) . $this->mapClass( $m[1] );
				$i += strlen( $m[0] );
				$last = $i;
			} elseif ( $char === '[' && preg_match( '/\G\[\s*class\s*~=\s*([\'"]?)([^\'"\]\s]+)\1/', $prelude, $m, 0, $i ) ) {
				$mapped = $this->mapClass( $m[2] );
				$out .= substr( $prelude, $last, $i - $last ) . str_replace( $m[2], $mapped, $m[0] );
				$i += strlen( $m[0] );
				$last = $i;
			} else {
				$i++;
			}
		}

		return $out . substr( $prelude, $last );
	}

	private function mapClass( string $class ): string {
		if ( in_array( $class, LegacySelectors::ICON_CLASSES, true ) ) {
			$this->iconClasses[$class] = true;
			return $class;
		}

		$new = LegacySelectors::CLASSES[$class] ?? null;
		if ( $new === null ) {
			return $class;
		}

		$this->replacements[$class] = ( $this->replacements[$class] ?? 0 ) + 1;
		$this->mappedTo[$class] = $new;

		return $new;
	}

	private function skipString( string $source, int $start ): int {
		$quote = $source[$start];
		$length = strlen( $source );
		$i = $start + 1;

		while ( $i < $length ) {
			$char = $source[$i];
			if ( $char === '\\' ) {
				$i += 2;
			} elseif ( $char === $quote ) {
				return $i + 1;
			} elseif ( $char === "\n" && $quote !== '`' ) {
				return $i;
			} else {
				$i++;
			}
		}

		return $length;
	}

	private function startsRegex( string $source, int $pos ): bool {
		$before = rtrim( substr( $source, 0, $pos ) );
		if ( $before === '' ) {
			return true;
		}

		if ( preg_match( '/(?:^|[^\w$])(?:return|typeof|case|in|of)$/', $before ) ) {
			return true;
		}

		return str_contains( '(,=:[!&|?{};+-*%<>~^', substr( $before, -1 ) );
	}

	private function skipRegex( string $source, int $start ): int {
		$length = strlen( $source );
		$i = $start + 1;
		$inClass = false;

		while ( $i < $length ) {
			$char = $source[$i];
			if ( $char === '\\' ) {
				$i += 2;
				continue;
			}
			if ( $char === "\n" ) {
				return $i;
			}
			if ( $char === '[' ) {
				$inClass = true;
			} elseif ( $char === ']' ) {
				$inClass = false;
			} elseif ( $char === '/' && !$inClass ) {
				return $i + 1;
			}
			$i++;
		}

		return $length;
	}
}
