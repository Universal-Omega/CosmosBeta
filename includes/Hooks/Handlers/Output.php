<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Output\Hook\OutputPageBodyAttributesHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\Skin\Hook\SkinPageReadyConfigHook;
use MediaWiki\Skins\CosmosBeta\LessUtil;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;
use MediaWiki\Skins\CosmosBeta\Theme\ThemeSettings;
use function implode;

class Output implements
	OutputPageBodyAttributesHook,
	OutputPageParserOutputHook,
	SkinPageReadyConfigHook
{

	/** @inheritDoc */
	public function onOutputPageBodyAttributes( $out, $skin, &$bodyAttrs ): void {
		if ( !$skin instanceof SkinCosmosBeta ) {
			return;
		}

		$classes = [
			$skin->getUser()->isRegistered() ? 'user-logged' : 'user-anon',
			LessUtil::isThemeDark(
				'content-background-color',
				LessUtil::getCosmosSettings( $skin->cosmosConfig->getRenderMode() )
			) ? 'theme-dark' : 'theme-light',
		];

		if ( $skin->cosmosConfig->getLayoutStyle() === ThemeSettings::STYLE_FANDOMDESKTOP ) {
			$classes[] = 'skin-cosmos-style-fandomdesktop';
		}

		if ( $skin->cosmosConfig->hasSlimButtons() ) {
			$classes[] = 'skin-cosmos-slim-buttons';
		}

		if ( $out->getTitle()->isMainPage() ) {
			$classes[] = 'mainpage';
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
