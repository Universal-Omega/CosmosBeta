<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\CosmosConfig;

/**
 * Makes rail modules that show an interface message. The wiki sets some up in its configuration,
 * and the theme designer adds more.
 */
class MessageModuleFactory {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::EnabledRailModules,
	];

	public function __construct(
		private readonly CosmosConfig $cosmosConfig,
		private readonly IContextSource $context,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public static function getInterfaceModuleId( string $message ): string {
		return "interface-$message";
	}

	public static function getCustomModuleId( string $id ): string {
		return "custom-$id";
	}

	/**
	 * @return array<string, RailModuleType> The configured messages, by the type each is shown as
	 */
	public function getConfiguredTypes(): array {
		$configured = $this->options->get( ConfigNames::EnabledRailModules )['interface'] ?? [];
		$types = [];

		foreach ( (array)( $configured[0] ?? $configured ) as $message => $type ) {
			if ( $type ) {
				$types[(string)$message] = RailModuleType::fromMixed( $type );
			}
		}

		return $types;
	}

	/**
	 * @return RailModule[]
	 */
	public function newInterfaceModules(): array {
		$modules = [];
		foreach ( $this->getConfiguredTypes() as $message => $type ) {
			$module = $this->newModule( self::getInterfaceModuleId( $message ), $message, null, $type );
			if ( $module !== null ) {
				$modules[] = $module;
			}
		}

		return $modules;
	}

	/**
	 * @return RailModule[]
	 */
	public function newCustomModules(): array {
		$modules = [];
		foreach ( $this->cosmosConfig->getCustomRailModules() as $custom ) {
			$module = $this->newModule(
				self::getCustomModuleId( $custom['id'] ),
				$custom['message'],
				$custom['header'] !== '' ? $custom['header'] : null,
				RailModuleType::Normal
			);

			if ( $module !== null ) {
				$modules[] = $module;
			}
		}

		return $modules;
	}

	private function newModule( string $id, string $message, ?string $header, RailModuleType $type ): ?RailModule {
		$text = $this->context->msg( $message );
		if ( $text->isDisabled() ) {
			return null;
		}

		return RailModule::newWithBody( $id, $type, 'interface-module', $header, $text->parse() );
	}
}
