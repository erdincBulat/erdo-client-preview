<?php
defined( 'ABSPATH' ) || exit;

class Erdo_Client_Preview_Token {

	const LINKS_KEY      = 'erdo_client_preview_magic_links';
	const COOKIE_NAME    = 'em_bypass';
	const ACCESS_LOG_MAX = 10;

	public function generate( string $label = '', ?string $expires_at = null, string $redirect_url = '' ): array {
		$raw  = wp_generate_password( 32, false, false );
		$hash = $this->hash( $raw );

		$link = array(
			'id'           => wp_generate_uuid4(),
			'label'        => sanitize_text_field( $label ),
			'token_hash'   => $hash,
			'token_raw'    => $raw,
			'created_at'   => current_time( 'mysql' ),
			'expires_at'   => $expires_at,
			'redirect_url' => esc_url_raw( $redirect_url ),
			'view_count'   => 0,
			'is_active'    => true,
		);

		$links   = $this->get_all_links();
		$links[] = $link;
		update_option( self::LINKS_KEY, $links, false );

		return $link;
	}

	public function get_all_links(): array {
		return (array) get_option( self::LINKS_KEY, array() );
	}

	public function find_by_token( string $raw_token ): ?array {
		$prefix = substr( $raw_token, 0, 16 );
		foreach ( $this->get_all_links() as $link ) {
			if ( ! $link['is_active'] ) {
				continue;
			}
			// Quick prefix check before expensive hash
			if ( strpos( $link['token_raw'], $prefix ) !== 0 ) {
				continue;
			}
			if ( hash_equals( $this->hash( $raw_token ), $link['token_hash'] ) ) {
				return $link;
			}
		}
		return null;
	}

	public function find_by_id( string $id ): ?array {
		foreach ( $this->get_all_links() as $link ) {
			if ( $link['id'] === $id ) {
				return $link;
			}
		}
		return null;
	}

	public function revoke( string $id ): void {
		$links = array_map( function ( $link ) use ( $id ) {
			if ( $link['id'] === $id ) {
				$link['is_active'] = false;
			}
			return $link;
		}, $this->get_all_links() );
		update_option( self::LINKS_KEY, $links, false );
	}

	public function delete( string $id ): void {
		$links = array_filter( $this->get_all_links(), static fn( $l ) => $l['id'] !== $id );
		update_option( self::LINKS_KEY, array_values( $links ), false );
	}

	public function increment_views( string $id ): void {
		$links = array_map( function ( $link ) use ( $id ) {
			if ( $link['id'] === $id ) {
				$link['view_count'] = ( (int) $link['view_count'] ) + 1;
			}
			return $link;
		}, $this->get_all_links() );
		update_option( self::LINKS_KEY, $links, false );
	}

	public function log_access( string $id, string $ip ): void {
		$links = array_map( function ( $link ) use ( $id, $ip ) {
			if ( $link['id'] === $id ) {
				$log   = isset( $link['access_log'] ) ? (array) $link['access_log'] : array();
				$log[] = array(
					'time' => current_time( 'mysql' ),
					'ip'   => $ip,
				);
				$link['access_log'] = array_slice( $log, -self::ACCESS_LOG_MAX );
			}
			return $link;
		}, $this->get_all_links() );
		update_option( self::LINKS_KEY, $links, false );
	}

	public function get_last_access( array $link ): ?array {
		$log = isset( $link['access_log'] ) ? (array) $link['access_log'] : array();
		return $log ? end( $log ) : null;
	}

	public function is_expired( ?string $expires_at ): bool {
		if ( ! $expires_at ) {
			return false;
		}
		return time() > ( new DateTime( $expires_at, new DateTimeZone( 'UTC' ) ) )->getTimestamp();
	}

	public function build_url( string $raw_token ): string {
		return add_query_arg( 'em_token', $raw_token, home_url( '/' ) );
	}

	public function set_bypass_cookie( string $token_hash ): void {
		setcookie(
			self::COOKIE_NAME,
			$this->cookie_value( $token_hash ),
			array(
				'expires'  => time() + 86400, // 24h
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	public function cookie_value( string $token_hash ): string {
		return hash_hmac( 'sha256', $token_hash, SECURE_AUTH_KEY );
	}

	public function has_valid_bypass_cookie(): bool {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return false;
		}
		$cookie_val = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		foreach ( $this->get_all_links() as $link ) {
			if ( ! $link['is_active'] || $this->is_expired( $link['expires_at'] ?? null ) ) {
				continue;
			}
			$expected = $this->cookie_value( $link['token_hash'] );
			if ( hash_equals( $expected, $cookie_val ) ) {
				return true;
			}
		}
		return false;
	}

	private function hash( string $raw ): string {
		return hash_hmac( 'sha256', $raw, AUTH_KEY );
	}
}
