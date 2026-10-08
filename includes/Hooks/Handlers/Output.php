<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\Output\Hook\OutputPageBodyAttributesHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\Skin\Cosmos\LessUtil;
use MediaWiki\Skin\Cosmos\SkinCosmos;
use MediaWiki\Skin\Cosmos\Theme\EffectiveTheme;
use MediaWiki\Skin\Hook\SkinPageReadyConfigHook;
use function implode;

class Output implements
	OutputPageBodyAttributesHook,
	OutputPageParserOutputHook,
	SkinPageReadyConfigHook
{

	public function __construct(
		private readonly EffectiveTheme $theme,
		private readonly LessUtil $lessUtil,
	) {
	}

	/** @inheritDoc */
	public function onOutputPageBodyAttributes( $out, $skin, &$bodyAttrs ): void {
		if ( !$skin instanceof SkinCosmos ) {
			return;
		}

		$user = $skin->getUser();
		$isNamed = $user->isNamed();
		$isDark = $this->lessUtil->isDark(
			'content',
			$this->theme->getRenderMode(),
			LessUtil::CONTENT_THRESHOLD
		);

		$classes = [
			$isNamed ? 'user-logged skin-cosmos-user-logged' : 'user-anon skin-cosmos-user-anon',
			$isDark ? 'theme-dark skin-cosmos-theme-dark' : 'theme-light skin-cosmos-theme-light',
		];

		if ( $this->theme->hasHeaderBorder() ) {
			$classes[] = 'skin-cosmos-has-header-border';
		}

		$buttonStyle = $this->theme->getButtonStyle();
		if ( $buttonStyle !== 'default' ) {
			$classes[] = "skin-cosmos-buttons--$buttonStyle";
		}

		if ( $out->getTitle()->isMainPage() ) {
			$classes[] = 'mainpage skin-cosmos-is-main-page';
		}

		$additional = $out->getProperty( 'additionalBodyClass' );
		if ( $additional ) {
			$classes[] = Sanitizer::escapeClass( $additional );
		}

		$bodyAttrs['class'] .= ' ' . implode( ' ', $classes );
	}

	/** @inheritDoc */
	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		if ( $parserOutput->getPageProperty( 'norail' ) !== null ) {
			$out->setProperty( 'norail', true );
		}

		$additional = $parserOutput->getPageProperty( 'additionalBodyClass' );
		if ( $additional ) {
			$out->setProperty( 'additionalBodyClass', $additional );
		}
	}

	/** @inheritDoc */
	public function onSkinPageReadyConfig( Context $context, array &$config ): void {
		if ( $context->getSkin() === 'cosmosbeta' ) {
			$config['search'] = false;
		}
	}
}
