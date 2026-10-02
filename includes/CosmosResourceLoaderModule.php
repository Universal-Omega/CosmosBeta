<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\SkinModule;
use Wikimedia\Minify\CSSMin;
use function array_merge;
use function array_values;
use function in_array;
use function sprintf;
use function strtolower;

class CosmosResourceLoaderModule extends SkinModule {

	private readonly bool $isAlt;

	public function __construct(
		array $options,
		private readonly CosmosConfig $cosmosConfig,
		private readonly CosmosBackgroundLookup $backgroundLookup,
		private readonly CosmosWordmarkLookup $wordmarkLookup,
	) {
		parent::__construct( $options );

		$this->isAlt = ( $options['variant'] ?? '' ) === 'alt';
	}

	public static function create( array $options ): self {
		$services = MediaWikiServices::getInstance();

		return new self(
			$options,
			$services->get( 'CosmosBetaConfig' ),
			$services->get( 'CosmosBetaBackgroundLookup' ),
			$services->get( 'CosmosBetaWordmarkLookup' )
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
			if ( $url ) {
				$preloadLinks[$url] = [ 'as' => 'image' ];
			}
		}

		return $preloadLinks;
	}

	/** @inheritDoc */
	protected function getLessVars( Context $context ): array {
		$lessVars = parent::getLessVars( $context );

		$mode = $this->isAlt ? $this->cosmosConfig->getAltMode() : $this->cosmosConfig->getDefaultMode();
		$settings = LessUtil::getCosmosSettings( $mode );

		$mainBackground = $this->backgroundLookup->getMainBackgroundUrl();
		$wikiHeaderBackground = $this->backgroundLookup->getWikiHeaderBackgroundUrl();

		$contentBackgroundColor = $this->cosmosConfig->getColor( 'content', $mode );
		$contentRgb = $this->resolveColor( $contentBackgroundColor );

		$lessVars['banner-background-color'] = $this->cosmosConfig->getColor( 'banner', $mode );

		if ( $mainBackground ) {
			$lessVars['main-background-image'] = CSSMin::buildUrlValue( $mainBackground );
		} else {
			$lessVars['main-background-image'] = 0;
		}

		if ( $wikiHeaderBackground ) {
			$lessVars['wiki-header-background-image'] = CSSMin::buildUrlValue( $wikiHeaderBackground );
		} else {
			$lessVars['wiki-header-background-image'] = 0;
		}

		$lessVars['main-background-color'] = $this->cosmosConfig->getColor( 'body', $mode );
		$lessVars['content-background-color'] = $contentBackgroundColor;
		$lessVars['main-background-image-size'] = $this->cosmosConfig->getBackgroundImageSize();

		$contentWidth = $this->cosmosConfig->getContentWidth();
		$lessVars['content-width-1084'] = $contentWidth === 'auto' ? 'auto' : 1024 + $contentWidth . 'px';
		$lessVars['content-width-1596'] = $contentWidth === 'auto' ? 'auto' : 1178 + $contentWidth . 'px';

		$lessVars['link-color'] = $this->cosmosConfig->getColor( 'link', $mode );
		$lessVars['button-background-color'] = $this->cosmosConfig->getColor( 'button', $mode );

		if ( $this->cosmosConfig->getBackgroundImageRepeat() ) {
			$lessVars['main-background-image-repeat'] = 'repeat';
		} else {
			$lessVars['main-background-image-repeat'] = 'no-repeat';
		}

		if ( $this->cosmosConfig->getBackgroundImageFixed() ) {
			$lessVars['main-background-image-position'] = 'fixed';
		} else {
			$lessVars['main-background-image-position'] = 'absolute';
		}

		// Convert content background to rgba for opacity.
		[ $r, $g, $b ] = $contentRgb;

		$contentOpacityLevelConfig = $this->cosmosConfig->getContentOpacityLevel();
		$lessVars['banner-icon-opacity'] = $this->cosmosConfig->getBannerIconOpacity() / 100;
		$lessVars['header-icon-opacity'] = $this->cosmosConfig->getHeaderIconOpacity() / 100;

		$lessVars['content-opacity-level'] = "rgba($r, $g, $b, " . $contentOpacityLevelConfig / 100.00 . ')';

		$footerBackgroundColor = $this->cosmosConfig->getColor( 'footer', $mode );
		[ $r, $g, $b, $footerAlpha ] = $this->resolveColor( $footerBackgroundColor );
		$footerOpacity = $footerAlpha > 0 ? $this->cosmosConfig->getFooterOpacity() / 100 : 0;
		$lessVars['footer-background-color'] = "rgba($r, $g, $b, $footerOpacity)";

		$isFooterBackgroundColorDark = LessUtil::isThemeDark( 'footer-background-color', $settings );
		$lessVars['footer-font-color1'] = $isFooterBackgroundColorDark ? '#999' : '#666';
		$lessVars['footer-font-color2'] = $isFooterBackgroundColorDark ? '#fff' : '#000';

		$headerBackgroundColor = $this->cosmosConfig->getColor( 'header', $mode );
		[ $r, $g, $b, $headerAlpha ] = $this->resolveColor( $headerBackgroundColor );
		$colorName = $headerAlpha > 0 ? sprintf( '#%02x%02x%02x', $r, $g, $b ) : 'transparent';

		$rightGradient = "linear-gradient(to right,rgba($r,$g,$b,0.5),rgba($r,$g,$b,0.5))";
		$leftGradient = "linear-gradient(to left,rgba($r,$g,$b,0) 200px,$colorName 430px)";
		$lessVars['header-background-color'] = "$rightGradient,$leftGradient";

		$rightGradient = "linear-gradient(to right,rgba($r,$g,$b,0.5),rgba($r,$g,$b,0.5))";
		$leftGradient = "linear-gradient(to left,rgba($r,$g,$b,0) 200px,$colorName 471px)";
		$lessVars['header-background-color2'] = "$rightGradient,$leftGradient";

		$lessVars['header-background-solid-color'] = $headerBackgroundColor;
		$lessVars['header-font-color'] = LessUtil::isThemeDark( 'header-background-color', $settings ) ? '#fff' : '#000';

		return array_merge(
			$lessVars,
			$this->getToolbarVars( $mode, $settings ),
			$this->getContentVars( $settings ),
			$this->getBannerVars( $settings ),
			$this->getButtonVars( $settings )
		);
	}

	/**
	 * Values that cannot be parsed, like none, are treated as transparent.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: float} Red, green, blue and alpha
	 */
	private function resolveColor( string $color ): array {
		return array_values( LessUtil::parseColor( $color ) ?? [ 'r' => 0, 'g' => 0, 'b' => 0, 'a' => 0.0 ] );
	}

	private function getToolbarVars( string $mode, array $settings ): array {
		$toolbarBackgroundColor = $this->cosmosConfig->getColor( 'toolbar', $mode );

		return [
			'toolbar-background-color2' => $toolbarBackgroundColor,
			'toolbar-background-color-mix' =>
				in_array( strtolower( $toolbarBackgroundColor ), [ '#000', '#000000', 'black' ], true ) ?
					'#404040' :
					'#000',
			'toolbar-font-color' => LessUtil::isThemeDark( 'toolbar-background-color', $settings ) ? '#fff' : '#000',
		];
	}

	private function getContentVars( array $settings ): array {
		$isContentBackgroundColorDark = LessUtil::isThemeDark( 'content-background-color', $settings );

		return [
			'font-color' => $isContentBackgroundColorDark ? '#D5D4D4' : '#000',
			'border-color' => $isContentBackgroundColorDark ? '#333333' : '#CCCCCC',
			'alt-font-color' => $isContentBackgroundColorDark ? '#fff' : '#000',
			'code-background-color' => $isContentBackgroundColorDark ? '#c5c6c6' : '#3a3939',
			'rail-header-bottom-border' => $isContentBackgroundColorDark ? '#0a0a0a' : '#eaecf0',
			'tabs-background-color' => $isContentBackgroundColorDark ? 'transparent' : '#eaecf0',
			'infobox-background-mix' => $isContentBackgroundColorDark ? '85%' : '90%',
			'toc-background-color' => $isContentBackgroundColorDark ? 'transparent' : '#f8f9fa',
		];
	}

	private function getBannerVars( array $settings ): array {
		$isBannerBackgroundColorDark = LessUtil::isThemeDark( 'banner-background-color', $settings );

		return [
			'banner-font-color' =>
				$isBannerBackgroundColorDark ? '#fff' : '#000',
			'banner-echo-font-color' =>
				$isBannerBackgroundColorDark ? 'fff' : '111',
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

	private function getButtonVars( array $settings ): array {
		$isButtonBackgroundColorDark = LessUtil::isThemeDark( 'button-background-color', $settings );

		return [
			'notice-close-button-color' => $isButtonBackgroundColorDark ? 'fff' : '111',
			'button-font-color' => $isButtonBackgroundColorDark ? '#fff' : '#000',
		];
	}
}
