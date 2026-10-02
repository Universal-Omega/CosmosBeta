<?php

declare( strict_types = 1 );

namespace MediaWiki\Skins\CosmosBeta\Components;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skins\CosmosBeta\ConfigNames;
use Telepedia\UserProfileV2\Avatar\UserProfileV2Avatar;
use wAvatar;
use function class_exists;

class BannerComponent {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::UseSocialProfileAvatar,
		ConfigNames::UseUPv2Avatar,
	];

	public function __construct(
		private readonly IContextSource $context,
		private readonly ServiceOptions $options,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getTemplateData( array $portlets ): array {
		$user = $this->context->getUser();
		$registered = $user->isRegistered();

		$items = '';
		foreach ( [ 'data-user-interface-preferences', 'data-user-page', 'data-user-menu' ] as $menu ) {
			foreach ( $portlets[$menu]['array-items'] ?? [] as $item ) {
				if ( ( $item['name'] ?? '' ) !== 'adminlinks' ) {
					$items .= $item['html-item'] ?? '';
				}
			}
		}

		return [
			'is-registered' => $registered,
			'username' => $registered ? $user->getName() : $this->context->msg( 'cosmosbeta-anonymous' )->text(),
			'html-avatar' => $this->getAvatar( $user->getId() ),
			'html-notifications' => $registered ? ( $portlets['data-notifications']['html-items'] ?? null ) : null,
			'html-personal-items' => $items,
		];
	}

	private function getAvatar( int $userId ): ?string {
		if ( class_exists( wAvatar::class ) && $this->options->get( ConfigNames::UseSocialProfileAvatar ) ) {
			return ( new wAvatar( $userId, 'm' ) )->getAvatarURL();
		}

		if (
			$this->extensionRegistry->isLoaded( 'UserProfileV2' ) &&
			$this->options->get( ConfigNames::UseUPv2Avatar )
		) {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension
			return ( new UserProfileV2Avatar( $userId ) )->getAvatarUrl( [ 'raw' => false ] );
		}

		return null;
	}
}
