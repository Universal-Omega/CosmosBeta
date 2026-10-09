<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Tests\Unit;

use Generator;
use MediaWiki\Skin\Cosmos\Hooks\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/** @covers \MediaWiki\Skin\Cosmos\Hooks\HookRunner */
class HookRunnerTest extends HookRunnerTestBase {

	/** @inheritDoc */
	public static function provideHookRunners(): Generator {
		yield HookRunner::class => [ HookRunner::class ];
	}
}
