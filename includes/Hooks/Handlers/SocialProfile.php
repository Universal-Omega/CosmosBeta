<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Skins\CosmosBeta\ConfigNames;
use MediaWiki\Skins\CosmosBeta\ProfileBioLookup;
use MediaWiki\Skins\CosmosBeta\SkinCosmosBeta;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use UserProfilePage;
use function count;
use function date;
use function in_array;
use function strtotime;
use function ucfirst;

class SocialProfile {

	private const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::SocialProfileAllowBio,
		ConfigNames::SocialProfileNumberofGroupTags,
		ConfigNames::SocialProfileShowEditCount,
		ConfigNames::SocialProfileShowGroupTags,
		ConfigNames::SocialProfileTagGroups,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly ProfileBioLookup $bioLookup,
		private readonly TemplateParser $templateParser,
		private readonly UserGroupManager $userGroupManager,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public static function factory(
		Config $cosmosOptions,
		ProfileBioLookup $bioLookup,
		TemplateParser $templateParser,
		UserGroupManager $userGroupManager
	): self {
		return new self(
			new ServiceOptions(
				self::CONSTRUCTOR_OPTIONS,
				$cosmosOptions
			),
			$bioLookup,
			$templateParser,
			$userGroupManager,
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

		$context = $userProfilePage->getContext();

		if ( !$context->getSkin() instanceof SkinCosmosBeta ) {
			return;
		}

		$owner = $userProfilePage->profileOwner;

		$profileTitle = $this->templateParser->processTemplate( 'ProfileHeader', [
			'name' => $owner->getName(),
			'array-tags' => $showTags ? $this->getUserGroupTags( $context, $owner ) : [],
			'data-editcount' => $showEdits ? $this->getEditCount( $context, $owner ) : null,
			'bio' => $allowBio ? $this->bioLookup->getBio( $owner ) : null,
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

		$max = (int)$this->options->get( ConfigNames::SocialProfileNumberofGroupTags );
		$groups = $this->userGroupManager->getUserGroups( $owner );
		$tags = [];

		foreach ( $this->options->get( ConfigNames::SocialProfileTagGroups ) as $group ) {
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
}
