<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\Skin\Cosmos\ConfigNames;

class ResourceLoaderCallbacks {

	/**
	 * @return array<string, mixed>
	 * @suppress PhanUnusedPublicNoOverrideMethodParameter
	 */
	public static function getCosmosResourceLoaderConfig( Context $context, Config $config ): array {
		return [
			'wgCosmosSearchHost' => $config->get( ConfigNames::SearchHost ),
			'wgCosmosSearchUseActionAPI' => (bool)$config->get( ConfigNames::SearchUseActionAPI ),
		];
	}

	/** @suppress PhanUnusedPublicNoOverrideMethodParameter */
	public static function getCosmosSearchResourceLoaderConfig( Context $context, Config $config ): array {
		return [
			'wgCosmosSearchDescriptionSource' => $config->get( ConfigNames::SearchDescriptionSource ),
			'wgCosmosMaxSearchResults' => $config->get( ConfigNames::MaxSearchResults ),
			'wgSearchSuggestCacheExpiry' => $config->get( MainConfigNames::SearchSuggestCacheExpiry ),
		] + $config->get( ConfigNames::SearchOptions );
	}
}
