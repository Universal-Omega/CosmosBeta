<?php

declare( strict_types = 1 );

namespace MediaWiki\Skin\Cosmos\Components;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Skin\Cosmos\ConfigNames;
use Telepedia\UserProfileV2\Avatar\UserProfileV2Avatar;
use wAvatar;
use function class_exists;

class BannerComponent {

	public function __construct(
		private readonly IContextSource $context,
		private readonly Config $config,
		private readonly ExtensionRegistry $extensionRegistry,
	) {
	}

	/** @return array<string, mixed> */
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
			'username' => $registered ? $user->getName() : $this->context->msg( 'cosmos-anonymous' )->text(),
			'html-avatar' => $this->getAvatar( $user->getId() ),
			'html-notifications' => $registered ? ( $portlets['data-notifications']['html-items'] ?? null ) : null,
			'html-personal-items' => $items,
			'data-talk-alert' => $registered ? $this->getTalkAlert( $portlets ) : null,
		];
	}

	/** @return ?array<string, string> */
	private function getTalkAlert( array $portlets ): ?array {
		foreach ( $portlets['data-notifications']['array-items'] ?? [] as $item ) {
			if ( ( $item['name'] ?? '' ) !== 'talk-alert' ) {
				continue;
			}

			$link = $item['array-links'][0] ?? [];
			$href = '';

			foreach ( $link['array-attributes'] ?? [] as $attribute ) {
				if ( $attribute['key'] === 'href' ) {
					$href = (string)$attribute['value'];
				}
			}

			return $href === '' ? null : [ 'href' => $href, 'text' => (string)( $link['text'] ?? '' ) ];
		}

		return null;
	}

	private function getAvatar( int $userId ): ?string {
		if ( class_exists( wAvatar::class ) && $this->config->get( ConfigNames::UseSocialProfileAvatar ) ) {
			return ( new wAvatar( $userId, 'm' ) )->getAvatarURL();
		}

		if (
			$this->extensionRegistry->isLoaded( 'UserProfileV2' ) &&
			$this->config->get( ConfigNames::UseUPv2Avatar )
		) {
			// @phan-suppress-next-line PhanUndeclaredClassMethod Optional extension
			return ( new UserProfileV2Avatar( $userId ) )->getAvatarUrl( [ 'raw' => false ] );
		}

		return null;
	}
}
