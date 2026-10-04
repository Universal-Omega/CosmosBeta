<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Skins\CosmosBeta\Theme\ColorModeResolver;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeSettings;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeStore;
use function array_diff;
use function array_values;

class CosmosConfig {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::AllowFooterIconHiding,
		ConfigNames::BackgroundImage,
		ConfigNames::BackgroundImageFixed,
		ConfigNames::BackgroundImageRepeat,
		ConfigNames::BackgroundImageSize,
		ConfigNames::BannerBackgroundColor,
		ConfigNames::ButtonBackgroundColor,
		ConfigNames::ContentBackgroundColor,
		ConfigNames::ContentOpacityLevel,
		ConfigNames::ContentWidth,
		ConfigNames::FooterBackgroundColor,
		ConfigNames::FooterProtectedLinks,
		ConfigNames::LinkColor,
		ConfigNames::MainBackgroundColor,
		ConfigNames::ToolbarBackgroundColor,
		ConfigNames::WikiHeaderBackgroundColor,
		ConfigNames::WikiHeaderBackgroundImage,
		ConfigNames::Wordmark,
		MainConfigNames::Logos,
	];

	private const array SLOT_OPTIONS = [
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
		private readonly ThemeStore $themeStore,
		private readonly ColorModeResolver $colorModeResolver,
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
		if ( $mode !== $this->getDefaultMode() ) {
			return $mode === ThemeSettings::MODE_DARK ?
				ThemeSettings::DARK_DEFAULTS[$slot] :
				ThemeSettings::LIGHT_DEFAULTS[$slot];
		}

		return (string)$this->options->get( self::SLOT_OPTIONS[$slot] );
	}

	public function getWordmark(): string {
		$fallback = $this->options->get( ConfigNames::Wordmark ) ?:
			$this->options->get( MainConfigNames::Logos )['wordmark']['src'] ??
			$this->options->get( MainConfigNames::Logos )['1x'] ?? '';

		return $this->getTheme()->getSection( 'images' )['wordmark'] ?: (string)$fallback;
	}

	public function getWikiHeaderBackgroundImage(): string {
		return $this->getTheme()->getSection( 'images' )['header'] ?:
			(string)$this->options->get( ConfigNames::WikiHeaderBackgroundImage );
	}

	public function getBackgroundImage(): string {
		return $this->getTheme()->getSection( 'images' )['background'] ?:
			(string)$this->options->get( ConfigNames::BackgroundImage );
	}

	public function getBackgroundImageSize(): string {
		return $this->getTheme()->getSection( 'images' )['backgroundSize'] ?:
			(string)$this->options->get( ConfigNames::BackgroundImageSize );
	}

	public function getBackgroundImageRepeat(): bool {
		return (bool)( $this->getTheme()->getSection( 'images' )['backgroundRepeat'] ??
			$this->options->get( ConfigNames::BackgroundImageRepeat ) );
	}

	public function getBackgroundImageFixed(): bool {
		return (bool)( $this->getTheme()->getSection( 'images' )['backgroundFixed'] ??
			$this->options->get( ConfigNames::BackgroundImageFixed ) );
	}

	public function getContentWidth(): string {
		$setting = $this->getTheme()->getSection( 'layout' )['contentWidth'] ?:
			$this->options->get( ConfigNames::ContentWidth );

		return match ( $setting ) {
			'full' => 'auto',
			'large' => '176',
			default => '0',
		};
	}

	public function getContentOpacityLevel(): int {
		return (int)( $this->getTheme()->getSection( 'layout' )['contentOpacity'] ??
			$this->options->get( ConfigNames::ContentOpacityLevel ) );
	}

	public function getHeaderButtonOpacity(): int {
		return (int)$this->getTheme()->getSection( 'layout' )['headerButtonOpacity'];
	}

	public function getBackdropBlur(): int {
		return (int)$this->getTheme()->getSection( 'layout' )['backdropBlur'];
	}

	public function getFooterOpacity(): int {
		return (int)$this->getTheme()->getSection( 'footer' )['opacity'];
	}

	public function getToolbarSettings(): array {
		return $this->getTheme()->getSection( 'toolbar' );
	}

	public function getFooterSettings(): array {
		$settings = $this->getTheme()->getSection( 'footer' );

		$settings['hiddenLinks'] = array_values(
			array_diff( $settings['hiddenLinks'], $this->getFooterProtectedLinks() )
		);
		$settings['showIcons'] = $settings['showIcons'] || !$this->canHideFooterIcons();

		return $settings;
	}

	/**
	 * @return string[]
	 */
	public function getFooterProtectedLinks(): array {
		return (array)$this->options->get( ConfigNames::FooterProtectedLinks );
	}

	public function canHideFooterIcons(): bool {
		return (bool)$this->options->get( ConfigNames::AllowFooterIconHiding );
	}

	public function getRailSettings(): array {
		return $this->getTheme()->getSection( 'rail' );
	}
}
