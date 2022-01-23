<?php

namespace WildWolf\WordPress\TwoFactorWebAuthn;

use MadWizard\WebAuthn\Credential\UserHandle;
use MadWizard\WebAuthn\Exception\NotAvailableException;
use MadWizard\WebAuthn\Server\UserIdentityInterface;
use WP_User;
use wpdb;

class WebAuthn_User implements UserIdentityInterface {
	private WP_User $user;

	public static function get_for( WP_User $user ): self {
		return new self( $user );
	}

	private function __construct( WP_User $user ) {
		$this->user = $user;
	}

	/**
	 * @throws NotAvailableException
	 * @global wpdb $wpdb
	 */
	public function getUserHandle(): UserHandle {
		/** @var wpdb $wpdb */
		global $wpdb;

		/** @var mixed */
		$handle = wp_cache_get( sprintf( 'handle:%u', $this->user->ID ), '2fa-webauthn' );
		if ( false === $handle || ! is_string( $handle ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$handle = $wpdb->get_var( $wpdb->prepare( "SELECT user_handle FROM {$wpdb->webauthn_users} WHERE user_id = %d", $this->user->ID ) );
			if ( ! $handle ) {
				$handle = UserHandle::random()->toString();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$wpdb->webauthn_users,
					[
						'user_id'     => $this->user->ID,
						'user_handle' => $handle,
					],
					[ '%d', '%s' ]
				);
			}

			wp_cache_set( sprintf( 'handle:%u', $this->user->ID ), $handle, '2fa-webauthn', 3600 );
		}

		return UserHandle::fromString( $handle );
	}

	public function getUsername(): string {
		return $this->user->user_login;
	}

	public function getDisplayName(): string {
		return $this->user->display_name;
	}

	/**
	 * @global wpdb $wpdb
	 */
	public static function get_user_by_handle( UserHandle $handle ): ?WP_User {
		/** @var wpdb $wpdb */
		global $wpdb;

		/** @var mixed */
		$user_id = wp_cache_get( sprintf( 'user:%s', $handle->toString() ), '2fa-webauthn' );
		if ( false === $user_id || ! is_int( $user_id ) ) {
			/** @psalm-var numeric-string|null $user_id */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->webauthn_users} WHERE user_handle = %s", $handle->toString() ) );
			wp_cache_set( sprintf( 'user:%s', $handle->toString() ), (int) $user_id, '2fa-webauthn', 3600 );
		}

		return $user_id ? new WP_User( (int) $user_id ) : null;
	}
}
