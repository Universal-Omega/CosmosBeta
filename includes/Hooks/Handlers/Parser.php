<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\Hook\GetDoubleUnderscoreIDsHook;
use MediaWiki\Parser\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser as CoreParser;

class Parser implements
	GetDoubleUnderscoreIDsHook,
	ParserFirstCallInitHook
{

	/** @inheritDoc */
	public function onGetDoubleUnderscoreIDs( &$doubleUnderscoreIDs ): void {
		$doubleUnderscoreIDs[] = 'norail';
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ): void {
		$parser->setFunctionHook( 'additionalbodyclass', $this->setAdditionalBodyClass( ... ) );
	}

	public function setAdditionalBodyClass( CoreParser $parser, string $newClass ): void {
		$parser->getOutput()->setPageProperty( 'additionalBodyClass', $newClass );
	}
}
