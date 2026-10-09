<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\Rail\RailModuleType;
use function array_map;
use function intval;
use function is_string;
use function strval;

class ConfigDefaults {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::BackgroundImage,
		ConfigNames::BackgroundImageFixed,
		ConfigNames::BackgroundImageRepeat,
		ConfigNames::BackgroundImageSize,
		ConfigNames::BannerBackgroundColor,
		ConfigNames::ButtonBackgroundColor,
		ConfigNames::ContentBackgroundColor,
		ConfigNames::ContentOpacityLevel,
		ConfigNames::ContentWidth,
		ConfigNames::EnabledRailModules,
		ConfigNames::EnablePortableInfoboxEuropaTheme,
		ConfigNames::FooterBackgroundColor,
		ConfigNames::LinkColor,
		ConfigNames::MainBackgroundColor,
		ConfigNames::RailDisabledNamespaces,
		ConfigNames::RailDisabledPages,
		ConfigNames::ThemeDesignerOnly,
		ConfigNames::ToolbarBackgroundColor,
		ConfigNames::WikiHeaderBackgroundColor,
		ConfigNames::WikiHeaderBackgroundImage,
		ConfigNames::Wordmark,
	];

	private const array COLOR_OPTIONS = [
		'banner' => ConfigNames::BannerBackgroundColor,
		'header' => ConfigNames::WikiHeaderBackgroundColor,
		'body' => ConfigNames::MainBackgroundColor,
		'content' => ConfigNames::ContentBackgroundColor,
		'button' => ConfigNames::ButtonBackgroundColor,
		'link' => ConfigNames::LinkColor,
		'footer' => ConfigNames::FooterBackgroundColor,
		'toolbar' => ConfigNames::ToolbarBackgroundColor,
	];

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Whether the theme designer is the only place the theme is set, so the configuration is ignored.
	 */
	public function isThemeDesignerOnly(): bool {
		return (bool)$this->options->get( ConfigNames::ThemeDesignerOnly );
	}

	/**
	 * What the configuration says, whether or not it is in use.
	 */
	public function getDefaults(): ThemeDefaults {
		$colors = [];
		foreach ( self::COLOR_OPTIONS as $slot => $name ) {
			$colors[$slot] = (string)$this->options->get( $name );
		}

		$rail = (array)$this->options->get( ConfigNames::EnabledRailModules );
		$recentChanges = $rail['recentchanges'] ?? false;
		$interface = (array)( $rail['interface'] ?? [] );
		$types = [];
		foreach ( (array)( $interface[0] ?? $interface ) as $message => $type ) {
			if ( $type ) {
				$types[(string)$message] = self::toType( $type );
			}
		}

		return new ThemeDefaults(
			colors: $colors,
			wordmark: (string)$this->options->get( ConfigNames::Wordmark ),
			headerImage: (string)$this->options->get( ConfigNames::WikiHeaderBackgroundImage ),
			backgroundImage: (string)$this->options->get( ConfigNames::BackgroundImage ),
			backgroundSize: (string)$this->options->get( ConfigNames::BackgroundImageSize ),
			backgroundRepeat: (bool)$this->options->get( ConfigNames::BackgroundImageRepeat ),
			backgroundFixed: (bool)$this->options->get( ConfigNames::BackgroundImageFixed ),
			contentWidth: (string)$this->options->get( ConfigNames::ContentWidth ),
			contentOpacity: (int)$this->options->get( ConfigNames::ContentOpacityLevel ),
			fontFamily: ThemeDefaults::FONT_FAMILY,
			europa: (bool)$this->options->get( ConfigNames::EnablePortableInfoboxEuropaTheme ),
			railDisabledNamespaces: array_map(
				intval( ... ),
				(array)$this->options->get( ConfigNames::RailDisabledNamespaces )
			),
			railDisabledPages: array_map(
				strval( ... ),
				(array)$this->options->get( ConfigNames::RailDisabledPages )
			),
			recentChanges: (bool)$recentChanges,
			recentChangesType: self::toType( $recentChanges ),
			interfaceModules: $types,
		);
	}

	private static function toType( mixed $value ): RailModuleType {
		return ( is_string( $value ) ? RailModuleType::tryFrom( $value ) : null ) ?? RailModuleType::Normal;
	}
}
