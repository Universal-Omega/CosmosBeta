<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use function is_string;

enum RailModuleType: string {

	case Normal = 'normal';
	case Sticky = 'sticky';

	public static function tryFromMixed( mixed $value ): ?self {
		return is_string( $value ) ? self::tryFrom( $value ) : null;
	}

	/**
	 * Configuration and extensions are loose about types, so anything unknown is normal.
	 */
	public static function fromMixed( mixed $value ): self {
		return self::tryFromMixed( $value ) ?? self::Normal;
	}
}
