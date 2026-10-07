<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Rail;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use function array_map;
use function array_unique;
use function implode;

class RailRenderer {

	public function __construct(
		private readonly TemplateParser $templateParser,
		private readonly IContextSource $context,
	) {
	}

	/**
	 * @param RailModule[] $modules
	 * @return string The rail, or an empty string without modules
	 */
	public function render( array $modules ): string {
		if ( $modules === [] ) {
			return '';
		}

		return $this->templateParser->processTemplate( 'Rail', [
			'array-modules' => array_map( $this->getTemplateData( ... ), $modules ),
		] );
	}

	private function getTemplateData( RailModule $module ): array {
		return [
			'class' => $this->getClasses( $module->classes ),
			'is-sticky' => $module->type === RailModuleType::Sticky,
			'header' => $module->header === null ? null : $this->getHeader( $module->header ),
			'array-recentchanges' => $module->recentChanges ?: null,
			'data-tools' => $module->tools ? [ 'array-items' => $module->tools ] : null,
			'html-body' => $module->body,
		];
	}

	/**
	 * @param string[] $classes
	 */
	private function getClasses( array $classes ): string {
		$all = [];
		foreach ( $classes as $class ) {
			$all[] = $class;
			$all[] = "skin-cosmos-$class";
		}

		return implode( ' ', array_unique( $all ) );
	}

	/**
	 * Headers are message keys, or plain text when no such message exists.
	 */
	private function getHeader( string $header ): string {
		$message = $this->context->msg( $header );
		return $message->exists() && !$message->isDisabled() ? $message->text() : $header;
	}
}
