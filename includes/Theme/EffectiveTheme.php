<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Theme;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\Rail\RailRules;
use function array_diff;
use function array_values;

/**
 * The theme as it applies on the wiki, with the defaults filled in where the theme leaves a setting alone.
 */
class EffectiveTheme {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::AllowFooterIconHiding,
		ConfigNames::FooterProtectedLinks,
		MainConfigNames::Logos,
	];

	public function __construct(
		private readonly ColorModeResolver $colorModeResolver,
		private readonly ThemeDefaults $defaults,
		private readonly ThemeStore $themeStore,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getTheme(): ThemeSettings {
		return $this->themeStore->getCurrent();
	}

	public function getDefaultMode(): string {
		return $this->getTheme()->getDefaultMode();
	}

	public function isAutoColorMode(): bool {
		return $this->getTheme()->isAutoMode();
	}

	public function hasColorModePreference(): bool {
		return $this->colorModeResolver->hasPreference( RequestContext::getMain()->getUser() );
	}

	public function getAltMode(): string {
		return ColorModeResolver::getOpposite( $this->getDefaultMode() );
	}

	public function isColorModeToggleEnabled(): bool {
		return $this->getTheme()->isToggleEnabled();
	}

	public function getRenderMode(): string {
		return $this->colorModeResolver->getRenderMode( RequestContext::getMain()->getUser() );
	}

	public function getColor( string $slot, string $mode ): string {
		return $this->getTheme()->getColor( $mode, $slot ) ?? $this->getFallbackColor( $slot, $mode );
	}

	public function getFallbackColor( string $slot, string $mode ): string {
		return $this->defaults->getColor( $slot, $mode, $this->getDefaultMode() );
	}

	/**
	 * What the theme settings are when the theme does not set them.
	 */
	public function getDefaults(): ThemeDefaults {
		return $this->defaults;
	}

	public function isPortableInfoboxEuropaEnabled(): bool {
		return (bool)( $this->getTheme()->getSection( 'extensions' )['portableInfoboxEuropa'] ??
			$this->defaults->europa );
	}

	public function getWordmark(): string {
		$fallback = $this->defaults->wordmark ?:
			$this->options->get( MainConfigNames::Logos )['wordmark']['src'] ??
			$this->options->get( MainConfigNames::Logos )['1x'] ?? '';

		return $this->getTheme()->getSection( 'images' )['wordmark'] ?: (string)$fallback;
	}

	public function getWikiHeaderBackgroundImage(): string {
		return $this->getTheme()->getSection( 'images' )['header'] ?:
			$this->defaults->headerImage;
	}

	public function getBackgroundImage(): string {
		return $this->getTheme()->getSection( 'images' )['background'] ?:
			$this->defaults->backgroundImage;
	}

	public function getBackgroundImageSize(): string {
		return $this->getTheme()->getSection( 'images' )['backgroundSize'] ?:
			$this->defaults->backgroundSize;
	}

	public function getBackgroundImageRepeat(): bool {
		return (bool)( $this->getTheme()->getSection( 'images' )['backgroundRepeat'] ??
			$this->defaults->backgroundRepeat );
	}

	public function getBackgroundImageFixed(): bool {
		return (bool)( $this->getTheme()->getSection( 'images' )['backgroundFixed'] ??
			$this->defaults->backgroundFixed );
	}

	public function getContentWidth(): string {
		$setting = $this->getTheme()->getSection( 'layout' )['contentWidth'] ?:
			$this->defaults->contentWidth;

		return match ( $setting ) {
			'full' => 'auto',
			'large' => '176',
			default => '0',
		};
	}

	public function getContentOpacityLevel(): int {
		return (int)( $this->getTheme()->getSection( 'layout' )['contentOpacity'] ??
			$this->defaults->contentOpacity );
	}

	public function getFont(): ThemeFont {
		return ThemeFont::newFromArray( $this->getTheme()->getSection( 'layout' )['font'] );
	}

	public function getFontFamily( bool $fileFound ): string {
		return $this->getFont()->getFamily( $this->defaults->fontFamily, $fileFound );
	}

	public function getHeaderButtonOpacity(): int {
		return (int)$this->getTheme()->getSection( 'layout' )['headerButtonOpacity'];
	}

	public function getBackdropBlur(): int {
		return (int)$this->getTheme()->getSection( 'layout' )['backdropBlur'];
	}

	public function hasHeaderBorder(): bool {
		return (bool)$this->getTheme()->getSection( 'layout' )['headerBorder'];
	}

	public function getButtonStyle(): string {
		return (string)$this->getTheme()->getSection( 'layout' )['buttonStyle'];
	}

	public function getFooterOpacity(): int {
		return (int)$this->getTheme()->getSection( 'footer' )['opacity'];
	}

	public function getToolbarSettings(): array {
		return $this->getTheme()->getSection( 'toolbar' );
	}

	/**
	 * @return array<string, mixed>
	 * @suppress PhanPluginMoreSpecificActualReturnType
	 */
	public function getFooterSettings(): array {
		$settings = $this->getTheme()->getSection( 'footer' );
		$settings['hiddenLinks'] = array_values(
			array_diff( $settings['hiddenLinks'], $this->getFooterProtectedLinks() )
		);

		$settings['showIcons'] = $settings['showIcons'] || !$this->canHideFooterIcons();
		return $settings;
	}

	/** @return string[] */
	public function getFooterProtectedLinks(): array {
		return (array)$this->options->get( ConfigNames::FooterProtectedLinks );
	}

	public function canHideFooterIcons(): bool {
		return (bool)$this->options->get( ConfigNames::AllowFooterIconHiding );
	}

	public function getRailSettings(): array {
		return $this->getTheme()->getSection( 'rail' );
	}

	public function getRailRules( string $moduleId ): RailRules {
		return RailRules::newFromArray( $this->getRailSettings()['modules'][$moduleId] ?? [] );
	}

	/** @return array<int, array{id: string, message: string, header: string}> */
	public function getCustomRailModules(): array {
		return $this->getRailSettings()['customModules'];
	}
}
