<?php
/**
 * Public redirect and intent handling.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create WhatsApp intents and redirect visitors to a fixed WhatsApp host.
 */
class WWC_Public {

	/**
	 * Allowed intent sources.
	 *
	 * @var array<int, string>
	 */
	private const ALLOWED_SOURCES = array(
		'header',
		'footer',
		'product',
		'cart',
		'checkout',
		'contact',
		'mobile-menu',
		'single-product',
		'archive-product',
		'unknown',
	);

	/**
	 * Handle the configured WhatsApp redirect page.
	 *
	 * @return void
	 */
	public function handle_redirect(): void {
		if ( ! is_page( 'go-whatsapp' ) ) {
			return;
		}

		$phone = $this->get_phone_number();

		if ( null === $phone ) {
			wp_die(
				esc_html__( 'WhatsApp is temporarily unavailable.', 'wordpress-whatsapp-conversions' ),
				esc_html__( 'Configuration error', 'wordpress-whatsapp-conversions' ),
				array( 'response' => 503 )
			);
		}

		$source = $this->get_source();
		$token  = $this->create_intent(
			array(
				'source'        => $source,
				'source_url'    => $this->get_internal_source_path(),
				'gclid'         => $this->get_tracking_id( 'gclid' ),
				'gbraid'        => $this->get_tracking_id( 'gbraid' ),
				'wbraid'        => $this->get_tracking_id( 'wbraid' ),
				'ga_client_id'  => $this->get_ga_client_id(),
				'ga_session_id' => $this->get_ga_session_id(),
			)
		);

		if ( null === $token ) {
			wp_die(
				esc_html__( 'Unable to open WhatsApp. Please try again.', 'wordpress-whatsapp-conversions' ),
				esc_html__( 'Temporary error', 'wordpress-whatsapp-conversions' ),
				array( 'response' => 503 )
			);
		}

		$message      = "Hola, quisiera recibir más información.\n\nRef: {$token}";
		$whatsapp_url = 'https://wa.me/' . $phone . '?text=' . rawurlencode( $message );

		add_filter( 'allowed_redirect_hosts', array( $this, 'allow_whatsapp_host' ) );
		wp_safe_redirect( $whatsapp_url, 302, 'WordPress WhatsApp Conversions' );
		exit;
	}

	/**
	 * Allow the fixed WhatsApp redirect host.
	 *
	 * @param array<int, string> $hosts Allowed redirect hosts.
	 * @return array<int, string>
	 */
	public function allow_whatsapp_host( array $hosts ): array {
		$hosts[] = 'wa.me';

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Read and validate the configured phone number.
	 *
	 * @return string|null
	 */
	private function get_phone_number(): ?string {
		if ( ! defined( 'WWC_PHONE_NUMBER' ) || ! is_scalar( WWC_PHONE_NUMBER ) ) {
			return null;
		}

		$phone = preg_replace( '/\D+/', '', (string) WWC_PHONE_NUMBER );

		if ( ! is_string( $phone ) || ! preg_match( '/\A[1-9][0-9]{7,19}\z/', $phone ) ) {
			return null;
		}

		return $phone;
	}

	/**
	 * Read the normalized intent source.
	 *
	 * @return string
	 */
	private function get_source(): string {
		$source = $this->get_query_value( 'from' );

		if ( null === $source || ! in_array( $source, self::ALLOWED_SOURCES, true ) ) {
			return 'unknown';
		}

		return $source;
	}

	/**
	 * Read a tracking identifier from query parameters or existing cookies.
	 *
	 * @param string $key Tracking key.
	 * @return string|null
	 */
	private function get_tracking_id( string $key ): ?string {
		$query_value = $this->get_query_value( $key );

		if ( null !== $query_value && $this->is_valid_tracking_id( $query_value ) ) {
			return $query_value;
		}

		$cookie_value = $this->get_cookie_value( $key );

		if ( null !== $cookie_value && $this->is_valid_tracking_id( $cookie_value ) ) {
			return $cookie_value;
		}

		return null;
	}

	/**
	 * Validate a Google Ads tracking identifier.
	 *
	 * @param string $value Identifier value.
	 * @return bool
	 */
	private function is_valid_tracking_id( string $value ): bool {
		return 1 === preg_match( '/\A[A-Za-z0-9._~-]{1,255}\z/', $value );
	}

	/**
	 * Return a same-host referer path without query parameters or fragments.
	 *
	 * @return string|null
	 */
	private function get_internal_source_path(): ?string {
		$referer = wp_get_referer();

		if ( false === $referer ) {
			return null;
		}

		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
		$home_host    = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		if ( ! is_string( $referer_host ) || ! is_string( $home_host ) || 0 !== strcasecmp( $referer_host, $home_host ) ) {
			return null;
		}

		$path = wp_parse_url( $referer, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return '/';
		}

		$path = '/' . ltrim( $path, '/' );

		return substr( $path, 0, 2048 );
	}

	/**
	 * Read a GA client identifier from the _ga cookie.
	 *
	 * @return string|null
	 */
	private function get_ga_client_id(): ?string {
		$value = $this->get_cookie_value( '_ga' );

		if ( null === $value || strlen( $value ) > 128 ) {
			return null;
		}

		if ( preg_match( '/\AGA\d+\.\d+\.(\d+\.\d+)\z/', $value, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/\A[A-Za-z0-9._-]{1,128}\z/', $value ) ) {
			return $value;
		}

		return null;
	}

	/**
	 * Read a GA session identifier from the measurement cookie.
	 *
	 * @return string|null
	 */
	private function get_ga_session_id(): ?string {
		if ( ! defined( 'WWC_GA4_MEASUREMENT_ID' ) || ! is_scalar( WWC_GA4_MEASUREMENT_ID ) ) {
			return null;
		}

		$measurement_id = strtoupper( sanitize_text_field( (string) WWC_GA4_MEASUREMENT_ID ) );

		if ( ! preg_match( '/\AG-([A-Z0-9]+)\z/', $measurement_id, $measurement_matches ) ) {
			return null;
		}

		$value = $this->get_cookie_value( '_ga_' . $measurement_matches[1] );

		if ( null === $value || strlen( $value ) > 255 ) {
			return null;
		}

		if ( preg_match( '/\AGS1\.\d+\.(\d+)/', $value, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/\AGS2\.\d+\.s(\d+)(?:\$|\z)/', $value, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Create a database intent and return its public token.
	 *
	 * @param array<string, string|null> $data Intent attributes.
	 * @return string|null
	 */
	private function create_intent( array $data ): ?string {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wwc_intents';
		$created_at = gmdate( 'Y-m-d H:i:s' );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 7 * DAY_IN_SECONDS ) );

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$token = $this->generate_token();
			$result = $wpdb->insert(
				$table_name,
				array(
					'token'         => $token,
					'source'        => $data['source'],
					'source_url'    => $data['source_url'],
					'gclid'         => $data['gclid'],
					'gbraid'        => $data['gbraid'],
					'wbraid'        => $data['wbraid'],
					'ga_client_id'  => $data['ga_client_id'],
					'ga_session_id' => $data['ga_session_id'],
					'status'        => 'pending',
					'created_at'    => $created_at,
					'expires_at'    => $expires_at,
					'ga4_status'    => 'pending',
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( 1 === $result ) {
				return $token;
			}
		}

		return null;
	}

	/**
	 * Generate a token using uppercase letters and digits.
	 *
	 * @return string
	 */
	private function generate_token(): string {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$token    = 'WWC-';
		$max      = strlen( $alphabet ) - 1;

		for ( $index = 0; $index < 8; $index++ ) {
			$token .= $alphabet[ random_int( 0, $max ) ];
		}

		return $token;
	}

	/**
	 * Read a scalar query parameter.
	 *
	 * @param string $key Query key.
	 * @return string|null
	 */
	private function get_query_value( string $key ): ?string {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) );

		return '' === $value ? null : $value;
	}

	/**
	 * Read a scalar first-party cookie.
	 *
	 * @param string $key Cookie key.
	 * @return string|null
	 */
	private function get_cookie_value( string $key ): ?string {
		if ( ! isset( $_COOKIE[ $key ] ) || ! is_scalar( $_COOKIE[ $key ] ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( wp_unslash( (string) $_COOKIE[ $key ] ) ) );

		return '' === $value ? null : $value;
	}
}
