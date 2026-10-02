<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Skins\CosmosBeta\ConfigNames;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use UserProfilePage;
use function count;
use function date;
use function in_array;
use function strtotime;
use function ucfirst;
use const NS_USER;

class SocialProfile {

	public function __construct(
		private readonly Config $config,
		private readonly TemplateParser $templateParser,
		private readonly TitleFactory $titleFactory,
		private readonly UserGroupManager $userGroupManager,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
	}

	/** @inheritDoc */
	public function onUserProfileGetProfileTitle( UserProfilePage $userProfilePage, string &$profileTitle ): void {
		$showTags = (bool)$this->config->get( ConfigNames::SocialProfileShowGroupTags );
		$showEdits = (bool)$this->config->get( ConfigNames::SocialProfileShowEditCount );
		$allowBio = (bool)$this->config->get( ConfigNames::SocialProfileAllowBio );

		if ( !$showTags && !$showEdits && !$allowBio ) {
			return;
		}

		$context = $userProfilePage->getContext();

		if ( !$context->getSkin() instanceof SkinCosmosBeta ) {
			return;
		}

		$owner = $userProfilePage->profileOwner;

		$profileTitle = $this->templateParser->processTemplate( 'ProfileHeader', [
			'name' => $owner->getName(),
			'array-tags' => $showTags ? $this->getUserGroupTags( $context, $owner ) : [],
			'data-editcount' => $showEdits ? $this->getEditCount( $context, $owner ) : null,
			'bio' => $allowBio ? $this->getUserBio( $owner ) : null,
		] );
	}

	private function getEditCount( IContextSource $context, User $owner ): array {
		return [
			'url' => SpecialPage::getTitleFor( 'Contributions', $owner->getName() )->getFullURL(),
			'count' => (string)$owner->getEditCount(),
			'label' => $context->msg( 'cosmosbeta-editcount-label' )->text(),
			'registration' => date( 'F j, Y', strtotime( (string)$owner->getRegistration() ) ),
		];
	}

	private function getUserGroupTags( IContextSource $context, User $owner ): array {
		if ( $owner->getBlock() ) {
			return [ [
				'class' => 'tag-blocked',
				'text' => $context->msg( 'cosmosbeta-user-blocked' )->text(),
			] ];
		}

		$max = (int)$this->config->get( ConfigNames::SocialProfileNumberofGroupTags );
		$groups = $this->userGroupManager->getUserGroups( $owner );
		$tags = [];

		foreach ( $this->config->get( ConfigNames::SocialProfileTagGroups ) as $group ) {
			if ( !in_array( $group, $groups, true ) || count( $tags ) >= $max ) {
				continue;
			}

			$message = $context->msg( "group-$group-member" );

			$tags[] = [
				'class' => 'tag-' . Sanitizer::escapeClass( $group ),
				'text' => ucfirst( $message->isDisabled() ? $group : $message->text() ),
			];
		}

		return $tags;
	}

	private function getUserBio( User $owner ): ?string {
		$title = $this->titleFactory->newFromText( $owner->getName(), NS_USER )?->getSubpage( 'bio' );

		if ( !$title || !$title->isKnown() ) {
			return null;
		}

		$content = $this->wikiPageFactory->newFromTitle( $title )->getContent();

		if (
			$this->config->get( ConfigNames::SocialProfileFollowBioRedirects ) &&
			$title->isRedirect() &&
			$content?->getRedirectTarget()?->isKnown() &&
			$content->getRedirectTarget()->inNamespace( NS_USER )
		) {
			$content = $this->wikiPageFactory->newFromTitle( $content->getRedirectTarget() )->getContent();
		}

		return $content instanceof TextContent ? $content->getText() : null;
	}
}
