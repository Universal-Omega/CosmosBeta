<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

/**
 * A box in the rail. It shows either HTML, a list of tools or recent changes.
 */
final readonly class RailModule {

	public const string ID_PAGE_TOOLS = 'page-tools';
	public const string ID_RECENT_CHANGES = 'recentchanges';

	/**
	 * @param string $id
	 * @param RailModuleType $type
	 * @param string[] $classes
	 * @param string|null $header
	 * @param string $body
	 * @param array<int, array{html-item: string}> $tools
	 * @param array<int, array{html-page: string, html-user: string, time: string}> $recentChanges
	 */
	private function __construct(
		public string $id,
		public RailModuleType $type,
		public array $classes,
		public ?string $header,
		public string $body,
		public array $tools,
		public array $recentChanges,
	) {
	}

	public static function newWithBody(
		string $id,
		RailModuleType $type,
		string $class,
		?string $header,
		string $body
	): self {
		return new self( $id, $type, [ $class ], $header, $body, [], [] );
	}

	/**
	 * @param string $id
	 * @param RailModuleType $type
	 * @param string $class
	 * @param string $header
	 * @param array<int, array{html-item: string}> $tools
	 */
	public static function newWithTools(
		string $id,
		RailModuleType $type,
		string $class,
		string $header,
		array $tools
	): self {
		return new self( $id, $type, [ $class ], $header, '', $tools, [] );
	}

	/**
	 * @param RailModuleType $type
	 * @param array<int, array{html-page: string, html-user: string, time: string}> $entries
	 */
	public static function newWithRecentChanges( RailModuleType $type, array $entries ): self {
		return new self(
			self::ID_RECENT_CHANGES,
			$type,
			[ 'recentchanges-module' ],
			'recentchanges',
			'',
			[],
			$entries
		);
	}

	public function withType( RailModuleType $type ): self {
		return new self(
			$this->id,
			$type,
			$this->classes,
			$this->header,
			$this->body,
			$this->tools,
			$this->recentChanges
		);
	}
}
