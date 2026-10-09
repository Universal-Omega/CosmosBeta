<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Skin\Cosmos\ConfigNames;
use MediaWiki\Skin\Cosmos\SkinCosmos;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use UserProfilePage;
use Wikimedia\Assert\Assert;
use Wikimedia\Timestamp\TimestampFormat;
use function count;
use function date;
use function in_array;
use function ucfirst;
use function wfTimestamp;
use const NS_USER;

class SocialProfile {

	private const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::SocialProfileAllowBio,
		ConfigNames::SocialProfileFollowBioRedirects,
		ConfigNames::SocialProfileNumberofGroupTags,
		ConfigNames::SocialProfileShowEditCount,
		ConfigNames::SocialProfileShowGroupTags,
		ConfigNames::SocialProfileTagGroups,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly TemplateParser $templateParser,
		private readonly TitleFactory $titleFactory,
		private readonly UserGroupManager $userGroupManager,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly WikiPageFactory $wikiPageFactory,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public static function factory(
		Config $cosmosOptions,
		TemplateParser $templateParser,
		TitleFactory $titleFactory,
		UserGroupManager $userGroupManager,
		UserRegistrationLookup $userRegistrationLookup,
		WikiPageFactory $wikiPageFactory
	): self {
		return new self(
			new ServiceOptions(
				self::CONSTRUCTOR_OPTIONS,
				$cosmosOptions
			),
			$templateParser,
			$titleFactory,
			$userGroupManager,
			$userRegistrationLookup,
			$wikiPageFactory,
		);
	}

	/** @inheritDoc */
	public function onUserProfileGetProfileTitle( UserProfilePage $userProfilePage, string &$profileTitle ): void {
		$showTags = (bool)$this->options->get( ConfigNames::SocialProfileShowGroupTags );
		$showEdits = (bool)$this->options->get( ConfigNames::SocialProfileShowEditCount );
		$allowBio = (bool)$this->options->get( ConfigNames::SocialProfileAllowBio );
		if ( !$showTags && !$showEdits && !$allowBio ) {
			return;
		}

		// @phan-suppress-next-line PhanUndeclaredMethod
		$context = $userProfilePage->getContext();
		Assert::postcondition( $context instanceof IContextSource, 'The profile page has a context' );
		if ( !$context->getSkin() instanceof SkinCosmos ) {
			return;
		}

		$owner = $userProfilePage->profileOwner;
		Assert::postcondition( $owner instanceof User, 'The profile page has an owner' );

		$profileTitle = $this->templateParser->processTemplate( 'ProfileHeader', [
			'name' => $owner->getName(),
			'array-tags' => $showTags ? $this->getUserGroupTags( $context, $owner ) : [],
			'data-editcount' => $showEdits ? $this->getEditCount( $context, $owner ) : null,
			'bio' => $allowBio ? $this->getUserBio( $owner ) : null,
		] );
	}

	/** @return array<string, string> */
	private function getEditCount( IContextSource $context, User $owner ): array {
		$registration = $this->userRegistrationLookup->getFirstRegistration( $owner );
		return [
			'url' => SpecialPage::getTitleFor( 'Contributions', $owner->getName() )->getFullURL(),
			'count' => (string)$owner->getEditCount(),
			'label' => $context->msg( 'cosmos-editcount-label' )->text(),
			'registration' => $registration === null ?
				'' :
				date( 'F j, Y', (int)wfTimestamp( TimestampFormat::UNIX, $registration ) ),
		];
	}

	/** @return list<array<string, string>> */
	private function getUserGroupTags( IContextSource $context, User $owner ): array {
		if ( $owner->getBlock() ) {
			return [ [
				'class' => 'skin-cosmos-profile__tag--blocked tag-blocked',
				'text' => $context->msg( 'cosmos-user-blocked' )->text(),
			] ];
		}

		$max = (int)$this->options->get( ConfigNames::SocialProfileNumberofGroupTags );
		$groups = $this->userGroupManager->getUserGroups( $owner );
		$tags = [];

		foreach ( $this->options->get( ConfigNames::SocialProfileTagGroups ) as $group ) {
			if ( !in_array( $group, $groups, true ) || count( $tags ) >= $max ) {
				continue;
			}

			$message = $context->msg( "group-$group-member" );
			$tags[] = [
				'class' => 'skin-cosmos-profile__tag--' . Sanitizer::escapeClass( $group ) .
					' tag-' . Sanitizer::escapeClass( $group ),
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
		if ( $this->options->get( ConfigNames::SocialProfileFollowBioRedirects ) && $title->isRedirect() ) {
			$target = $content?->getRedirectTarget();
			if ( $target !== null && $target->isKnown() && $target->inNamespace( NS_USER ) ) {
				$content = $this->wikiPageFactory->newFromTitle( $target )->getContent();
			}
		}

		return $content instanceof TextContent ? $content->getText() : null;
	}
}
