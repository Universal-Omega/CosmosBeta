<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\CosmosConfig;

/**
 * Decides where the rail and each of its modules show.
 */
class RailVisibility {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::RailDisabledNamespaces,
		ConfigNames::RailDisabledPages,
	];

	public function __construct(
		private readonly CosmosConfig $cosmosConfig,
		private readonly IContextSource $context,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Whether the rail is off as a whole, whatever the page.
	 */
	public function isRailHidden(): bool {
		$settings = $this->cosmosConfig->getRailSettings();

		return !$settings['enabled'] ||
			( $settings['hideForAnons'] && !$this->context->getUser()->isNamed() ) ||
			(bool)$this->context->getOutput()->getProperty( 'norail' );
	}

	/**
	 * Whether the module may show on the current page, going by its rules and the rail wide ones.
	 */
	public function isModuleShown( string $id ): bool {
		$settings = $this->cosmosConfig->getRailSettings();

		return $this->cosmosConfig->getRailRules( $id )->isShownOn(
			$this->context->getTitle(),
			$settings['disabledNamespaces'] ?? $this->options->get( ConfigNames::RailDisabledNamespaces ),
			$settings['disabledPages'] ?? $this->options->get( ConfigNames::RailDisabledPages )
		);
	}

	public function canShowModule( string $id ): bool {
		return !$this->isRailHidden() && $this->isModuleShown( $id );
	}
}
