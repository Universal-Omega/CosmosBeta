<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

enum RailModuleOrigin: string {

	case BuiltIn = 'builtin';
	case Interface = 'interface';
	case Custom = 'custom';
	case Hook = 'hook';
	case Sidebar = 'sidebar';
}
