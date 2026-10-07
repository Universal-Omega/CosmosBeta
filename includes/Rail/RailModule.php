<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use function array_values;

/**
 * A box in the rail. It shows either HTML, a list of tools or recent changes.
 */
final readonly class RailModule {

	public const string ID_PAGE_TOOLS = 'page-tools';
	public const string ID_RECENT_CHANGES = 'recentchanges';

	/**
	 * @param string[] $classes
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

	/**
	 * Reads a module as extensions hand it over in the CosmosRailBuilder hook.
	 */
	public static function newFromHookData( string $id, array $data ): self {
		return new self(
			$id,
			RailModuleType::fromMixed( $data['type'] ?? null ),
			array_values( (array)( $data['class'] ?? 'custom-module' ) ),
			isset( $data['header'] ) ? (string)$data['header'] : null,
			(string)( $data['body'] ?? '' ),
			(array)( $data['tools'] ?? [] ),
			(array)( $data['recentchanges'] ?? [] )
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

	/**
	 * The module as the CosmosRailBuilder hook gets it.
	 */
	public function toHookData(): array {
		$data = [
			'class' => $this->classes,
			'type' => $this->type->value,
			'body' => $this->body,
		];

		if ( $this->header !== null ) {
			$data['header'] = $this->header;
		}

		if ( $this->tools !== [] ) {
			$data['tools'] = $this->tools;
		}

		if ( $this->recentChanges !== [] ) {
			$data['recentchanges'] = $this->recentChanges;
		}

		return $data;
	}
}
