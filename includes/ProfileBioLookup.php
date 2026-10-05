<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\TextContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use const NS_USER;

class ProfileBioLookup {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::SocialProfileAllowBio,
		ConfigNames::SocialProfileFollowBioRedirects,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly TitleFactory $titleFactory,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function isEnabled(): bool {
		return (bool)$this->options->get( ConfigNames::SocialProfileAllowBio );
	}

	/** Text of the bio subpage of a user, null when there is none */
	public function getBio( UserIdentity $owner ): ?string {
		if ( !$this->isEnabled() ) {
			return null;
		}

		$title = $this->titleFactory->newFromText( $owner->getName(), NS_USER )?->getSubpage( 'bio' );

		if ( !$title || !$title->isKnown() ) {
			return null;
		}

		$content = $this->wikiPageFactory->newFromTitle( $title )->getContent();

		if (
			$this->options->get( ConfigNames::SocialProfileFollowBioRedirects ) &&
			$title->isRedirect() &&
			$content?->getRedirectTarget()?->isKnown() &&
			$content->getRedirectTarget()->inNamespace( NS_USER )
		) {
			$content = $this->wikiPageFactory->newFromTitle( $content->getRedirectTarget() )->getContent();
		}

		return $content instanceof TextContent ? $content->getText() : null;
	}
}
