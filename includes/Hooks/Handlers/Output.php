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
use function implode;

class Output implements
	OutputPageBodyAttributesHook,
	OutputPageParserOutputHook,
	SkinPageReadyConfigHook
{

	public function __construct(
		private readonly LessUtil $lessUtil,
	) {
	}

	/** @inheritDoc */
	public function onOutputPageBodyAttributes( $out, $skin, &$bodyAttrs ): void {
		if ( !$skin instanceof SkinCosmosBeta ) {
			return;
		}

		$user = $skin->getUser();
		$classes = [
			$user->isNamed() ? 'user-logged' : 'user-anon',
			LessUtil::isThemeDark(
				'content-background-color',
				$this->lessUtil->getCosmosSettings( $skin->cosmosConfig->getRenderMode() )
			) ? 'theme-dark' : 'theme-light',
		];

		if ( $user->isTemp() ) {
			$classes[] = 'user-temp';
		}

		$buttonStyle = $skin->cosmosConfig->getButtonStyle();
		if ( $buttonStyle !== 'default' ) {
			$classes[] = "skin-cosmos-$buttonStyle-buttons";
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
