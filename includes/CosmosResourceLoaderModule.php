<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\SkinModule;
use MediaWiki\Skin\Cosmos\Lookup\BackgroundLookup;
use MediaWiki\Skin\Cosmos\Lookup\FontLookup;
use MediaWiki\Skin\Cosmos\Lookup\WordmarkLookup;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\Skin\Cosmos\Theme\ThemeFont;
use Wikimedia\Minify\CSSMin;
use function array_merge;
use function array_values;
use function in_array;
use function min;
use function round;
use function sprintf;
use function strtolower;

/** @suppress PhanAccessClassInternal */
class CosmosResourceLoaderModule extends SkinModule {

	private readonly bool $isAlt;

	public function __construct(
		array $options,
		private readonly BackgroundLookup $backgroundLookup,
		private readonly EffectiveTheme $theme,
		private readonly FontLookup $fontLookup,
		private readonly LessUtil $lessUtil,
		private readonly WordmarkLookup $wordmarkLookup,
	) {
		parent::__construct( $options );
		$this->isAlt = ( $options['variant'] ?? '' ) === 'alt';
	}

	public static function factory( array $options ): self {
		$services = MediaWikiServices::getInstance();
		return new self(
			$options,
			$services->get( 'Cosmos.BackgroundLookup' ),
			$services->get( 'Cosmos.EffectiveTheme' ),
			$services->get( 'Cosmos.FontLookup' ),
			$services->get( 'Cosmos.LessUtil' ),
			$services->get( 'Cosmos.WordmarkLookup' )
		);
	}

	/** @inheritDoc */
	public function getPreloadLinks( Context $context ): array {
		$preloadLinks = parent::getPreloadLinks( $context );

		$urls = [
			$this->wordmarkLookup->getWordmarkUrl(),
			$this->backgroundLookup->getMainBackgroundUrl(),
			$this->backgroundLookup->getWikiHeaderBackgroundUrl(),
		];

		foreach ( $urls as $url ) {
			if ( $url !== null && $url !== '' ) {
				$preloadLinks[$url] = [ 'as' => 'image' ];
			}
		}

		return $preloadLinks;
	}

	/** @inheritDoc */
	protected function getLessVars( Context $context ): array {
		$lessVars = parent::getLessVars( $context );
		$mode = $this->isAlt ? $this->theme->getAltMode() : $this->theme->getDefaultMode();

		$mainBackground = $this->backgroundLookup->getMainBackgroundUrl();
		$wikiHeaderBackground = $this->backgroundLookup->getWikiHeaderBackgroundUrl();

		$contentBackgroundColor = $this->theme->getColor( 'content', $mode );
		$contentRgb = $this->resolveColor( $contentBackgroundColor );

		$bannerColor = $this->theme->getColor( 'banner', $mode );
		$lessVars['banner-background-color'] = $bannerColor;

		[ $br, $bg, $bb, $ba ] = $this->resolveColor( $bannerColor );
		$lessVars['banner-background-color-fallback'] = $this->getFallbackColor(
			$br,
			$bg,
			$bb,
			$ba,
			$this->theme->getBackdropBlur()
		);

		if ( $mainBackground !== null && $mainBackground !== '' ) {
			$lessVars['main-background-image'] = CSSMin::buildUrlValue( $mainBackground );
		} else {
			$lessVars['main-background-image'] = 0;
		}

		if ( $wikiHeaderBackground !== null && $wikiHeaderBackground !== '' ) {
			$lessVars['wiki-header-background-image'] = CSSMin::buildUrlValue( $wikiHeaderBackground );
		} else {
			$lessVars['wiki-header-background-image'] = 0;
		}

		$lessVars['main-background-color'] = $this->theme->getColor( 'body', $mode );
		$lessVars['content-background-color'] = $contentBackgroundColor;
		$lessVars['main-background-image-size'] = $this->theme->getBackgroundImageSize();

		$contentWidth = $this->theme->getContentWidth();
		$lessVars['content-width-1084'] = $contentWidth === 'auto' ? 'auto' : 1024 + (int)$contentWidth . 'px';
		$lessVars['content-width-1596'] = $contentWidth === 'auto' ? 'auto' : 1178 + (int)$contentWidth . 'px';

		$fontUrl = $this->fontLookup->getUrl( $this->theme->getFont()->getFileName() );
		$lessVars['font-family'] = $this->theme->getFontFamily( $fontUrl !== null );
		$lessVars['font-file'] = $fontUrl !== null ? CSSMin::buildUrlValue( $fontUrl ) : 0;
		$lessVars['font-file-family'] = "'" . ThemeFont::FILE_FAMILY . "'";
		$lessVars['link-color'] = $this->theme->getColor( 'link', $mode );
		$linkIsDark = $this->lessUtil->isDark( 'link', $mode, LessUtil::CONTENT_THRESHOLD );
		$lessVars['link-contrast-color'] = $linkIsDark ? '#fff' : '#202122';
		$lessVars['link-contrast-invert'] = $linkIsDark ? 1 : 0;
		$lessVars['button-background-color'] = $this->theme->getColor( 'button', $mode );

		if ( $this->theme->getBackgroundImageRepeat() ) {
			$lessVars['main-background-image-repeat'] = 'repeat';
		} else {
			$lessVars['main-background-image-repeat'] = 'no-repeat';
		}

		if ( $this->theme->getBackgroundImageFixed() ) {
			$lessVars['main-background-image-position'] = 'fixed';
		} else {
			$lessVars['main-background-image-position'] = 'absolute';
		}

		// Convert content background to rgba for opacity.
		[ $r, $g, $b, $contentAlpha ] = $contentRgb;

		$contentOpacityLevelConfig = $this->theme->getContentOpacityLevel();
		$blur = $this->theme->getBackdropBlur();
		$lessVars['backdrop-blur'] = $blur . 'px';
		$lessVars['backdrop-blur-enabled'] = $blur > 0 ? 1 : 0;
		$buttonOpacity = $this->theme->getHeaderButtonOpacity() / 100;
		$lessVars['header-button-background'] = "rgba(0, 30, 59, $buttonOpacity)";
		$lessVars['header-button-background-hover'] = 'rgba(0, 30, 59, ' . min( 1, $buttonOpacity + 0.2 ) . ')';

		$contentFinalAlpha = round( $contentAlpha * $contentOpacityLevelConfig / 100, 3 );
		$lessVars['content-opacity-level'] = "rgba($r, $g, $b, $contentFinalAlpha)";
		$lessVars['content-opacity-level-fallback'] = $this->getFallbackColor( $r, $g, $b, $contentFinalAlpha, $blur );

		$footerBackgroundColor = $this->theme->getColor( 'footer', $mode );
		[ $r, $g, $b, $footerAlpha ] = $this->resolveColor( $footerBackgroundColor );
		$footerOpacity = round( $footerAlpha * $this->theme->getFooterOpacity() / 100, 3 );
		$lessVars['footer-background-color'] = "rgba($r, $g, $b, $footerOpacity)";
		$lessVars['footer-background-color-fallback'] = $this->getFallbackColor( $r, $g, $b, $footerOpacity, $blur );

		$isFooterBackgroundColorDark = $this->lessUtil->isDark( 'footer', $mode, LessUtil::CHROME_THRESHOLD );
		$lessVars['footer-font-color1'] = $isFooterBackgroundColorDark ? 'rgba(255,255,255,0.75)' : 'rgba(0,0,0,0.65)';
		$lessVars['footer-font-color2'] = $isFooterBackgroundColorDark ? '#fff' : '#000';

		$headerBackgroundColor = $this->theme->getColor( 'header', $mode );
		[ $r, $g, $b, $headerAlpha ] = $this->resolveColor( $headerBackgroundColor );
		$colorName = $headerAlpha > 0 ? "rgba($r,$g,$b,$headerAlpha)" : 'transparent';
		$halfAlpha = round( $headerAlpha * 0.5, 3 );

		$rightGradient = "linear-gradient(to right,rgba($r,$g,$b,$halfAlpha),rgba($r,$g,$b,$halfAlpha))";
		$leftGradient = "linear-gradient(to left,rgba($r,$g,$b,0) 200px,$colorName 430px)";
		$lessVars['header-background-color'] = "$rightGradient,$leftGradient";

		$rightGradient = "linear-gradient(to right,rgba($r,$g,$b,$halfAlpha),rgba($r,$g,$b,$halfAlpha))";
		$leftGradient = "linear-gradient(to left,rgba($r,$g,$b,0) 200px,$colorName 471px)";
		$lessVars['header-background-color2'] = "$rightGradient,$leftGradient";

		$lessVars['header-background-solid-color'] = $headerBackgroundColor;
		$lessVars['header-font-color'] = $this->lessUtil->isDark( 'header', $mode, LessUtil::CHROME_THRESHOLD )
			? '#fff'
			: '#000';

		return array_merge(
			$lessVars,
			$this->getToolbarVars( $mode ),
			$this->getContentVars( $mode ),
			$this->getBannerVars( $mode ),
			$this->getButtonVars( $mode )
		);
	}

	/**
	 * Browsers without backdrop-filter get a more opaque background instead of the blur.
	 */
	private function getFallbackColor( int $r, int $g, int $b, float $alpha, int $blur ): string {
		$boosted = $blur > 0 ? $alpha + ( 1 - $alpha ) * 0.6 : $alpha;
		return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, (string)round( $boosted, 3 ) );
	}

	private function getToolbarFallback( string $color ): string {
		[ $r, $g, $b, $a ] = $this->resolveColor( $color );
		return $this->getFallbackColor( $r, $g, $b, (float)$a, $this->theme->getBackdropBlur() );
	}

	/**
	 * Values that cannot be parsed, like none, are treated as transparent.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: float} Red, green, blue and alpha
	 */
	private function resolveColor( string $color ): array {
		return array_values( LessUtil::parseColor( $color ) ?? [ 'r' => 0, 'g' => 0, 'b' => 0, 'a' => 0.0 ] );
	}

	/** @return array<string, int|string> */
	private function getToolbarVars( string $mode ): array {
		$toolbarBackgroundColor = $this->theme->getColor( 'toolbar', $mode );
		return [
			'toolbar-background-color2' => $toolbarBackgroundColor,
			'toolbar-background-color-fallback' => $this->getToolbarFallback( $toolbarBackgroundColor ),
			'toolbar-background-color-mix' =>
				in_array( strtolower( $toolbarBackgroundColor ), [ '#000', '#000000', 'black' ], true ) ?
					'#404040' :
					'#000',
			'toolbar-font-color' => $this->lessUtil->isDark( 'toolbar', $mode, LessUtil::CHROME_THRESHOLD )
				? '#fff'
				: '#000',
			'toolbar-icon-invert' => $this->lessUtil->isDark( 'toolbar', $mode, LessUtil::CHROME_THRESHOLD ) ? 1 : 0,
		];
	}

	/** @return array<string, int|string> */
	private function getContentVars( string $mode ): array {
		$isContentBackgroundColorDark = $this->lessUtil->isDark( 'content', $mode, LessUtil::CONTENT_THRESHOLD );
		return [
			'font-color' => $isContentBackgroundColorDark ? '#D5D4D4' : '#000',
			'theme-invert' => $isContentBackgroundColorDark ? 1 : 0,
			'border-color' => $isContentBackgroundColorDark ? '#333333' : '#CCCCCC',
			'alt-font-color' => $isContentBackgroundColorDark ? '#fff' : '#000',
			'code-background-color' => $isContentBackgroundColorDark ? '#c5c6c6' : '#3a3939',
			'rail-header-bottom-border' => $isContentBackgroundColorDark ? 'rgba(213, 212, 212, 0.2)' : '#eaecf0',
			'tabs-background-color' => $isContentBackgroundColorDark ? 'rgba(213, 212, 212, 0.07)' : '#eaecf0',
			'infobox-background-mix' => $isContentBackgroundColorDark ? '85%' : '90%',
			'toc-background-color' => $isContentBackgroundColorDark ? 'transparent' : '#f8f9fa',
		];
	}

	/** @return array<string, int|string> */
	private function getBannerVars( string $mode ): array {
		$isBannerBackgroundColorDark = $this->lessUtil->isDark( 'banner', $mode, LessUtil::CHROME_THRESHOLD );
		return [
			'banner-font-color' =>
				$isBannerBackgroundColorDark ? '#fff' : '#000',
			'banner-icon-invert' => $isBannerBackgroundColorDark ? 1 : 0,
			'banner-search-background' =>
				$isBannerBackgroundColorDark ? 'rgba(0,0,0,0.4)' : 'rgba(255,255,255,0.4)',
			'banner-search-focus-background' =>
				$isBannerBackgroundColorDark ? 'rgba(0,0,0,0.6)' : 'rgba(255,255,255,0.6)',
			'banner-search-button-hover-background' =>
				$isBannerBackgroundColorDark ? 'rgba(0,0,0,0.3)' : 'rgba(255,255,255,0.3)',
			'banner-search-button-active-background' =>
				$isBannerBackgroundColorDark ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.3)',
		];
	}

	/** @return array<string, string> */
	private function getButtonVars( string $mode ): array {
		$isButtonBackgroundColorDark = $this->lessUtil->isDark( 'button', $mode, LessUtil::CHROME_THRESHOLD );
		return [
			'notice-close-button-color' => $isButtonBackgroundColorDark ? 'fff' : '111',
			'button-font-color' => $isButtonBackgroundColorDark ? '#fff' : '#000',
		];
	}
}
