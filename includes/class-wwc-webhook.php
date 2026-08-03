<?php
/**
 * Meta WhatsApp webhook handling.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verify Meta requests and convert matching WhatsApp intents.
 */
class WWC_Webhook {

	/**
	 * REST namespace.
	 */
	private const REST_NAMESPACE = 'wordpress-whatsapp-conversions/v1';

	/**
	 * REST route.
	 */
	private const REST_ROUTE = '/webhook';

	/**
	 * Register webhook routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'verify_webhook' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_webhook' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Verify the webhook subscription request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_webhook( WP_REST_Request $request ) {
		$mode      = $this->get_verification_parameter( $request, 'hub.mode' );
		$token     = $this->get_verification_parameter( $request, 'hub.verify_token' );
		$challenge = $this->get_verification_parameter( $request, 'hub.challenge' );

		if (
			'subscribe' !== $mode ||
			null === $token ||
			null === $challenge ||
			! defined( 'WWC_VERIFY_TOKEN' ) ||
			! is_scalar( WWC_VERIFY_TOKEN ) ||
			'' === (string) WWC_VERIFY_TOKEN ||
			! hash_equals( (string) WWC_VERIFY_TOKEN, $token )
		) {
			return new WP_Error(
				'wwc_webhook_forbidden',
				__( 'Webhook verification failed.', 'wordpress-whatsapp-conversions' ),
				array( 'status' => 403 )
			);
		}

		$response = new WP_REST_Response( $challenge, 200 );
		$response->header( 'Content-Type', 'text/plain; charset=' . get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Serve the verification challenge without JSON encoding.
	 *
	 * @param bool             $served  Whether the response was served.
	 * @param WP_HTTP_Response $result  REST result.
	 * @param WP_REST_Request  $request REST request.
	 * @param WP_REST_Server   $server  REST server.
	 * @return bool
	 */
	public function serve_plain_challenge( bool $served, WP_HTTP_Response $result, WP_REST_Request $request, WP_REST_Server $server ): bool {
		unset( $server );

		if ( $served || 'GET' !== $request->get_method() || '/' . self::REST_NAMESPACE . self::REST_ROUTE !== $request->get_route() ) {
			return $served;
		}

		$headers      = $result->get_headers();
		$content_type = $headers['Content-Type'] ?? '';

		if ( 200 !== $result->get_status() || ! is_string( $content_type ) || ! str_starts_with( $content_type, 'text/plain' ) ) {
			return $served;
		}

		echo (string) $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return true;
	}

	/**
	 * Process a signed webhook delivery.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$body      = $request->get_body();
		$signature = $request->get_header( 'x-hub-signature-256' );

		if ( ! $this->has_valid_signature( $body, $signature ) ) {
			return new WP_Error(
				'wwc_webhook_forbidden',
				__( 'Invalid webhook signature.', 'wordpress-whatsapp-conversions' ),
				array( 'status' => 403 )
			);
		}

		try {
			$payload = json_decode( $body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING );
		} catch ( JsonException $exception ) {
			unset( $exception );

			return new WP_REST_Response( null, 200 );
		}

		if ( is_array( $payload ) ) {
			$this->process_payload( $payload );
		}

		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Validate the Meta SHA-256 signature.
	 *
	 * @param string $body      Raw request body.
	 * @param string $signature Signature header.
	 * @return bool
	 */
	private function has_valid_signature( string $body, string $signature ): bool {
		if (
			! defined( 'WWC_APP_SECRET' ) ||
			! is_scalar( WWC_APP_SECRET ) ||
			'' === (string) WWC_APP_SECRET ||
			! preg_match( '/\Asha256=[a-f0-9]{64}\z/i', $signature )
		) {
			return false;
		}

		$expected = 'sha256=' . hash_hmac( 'sha256', $body, (string) WWC_APP_SECRET );

		return hash_equals( $expected, strtolower( $signature ) );
	}

	/**
	 * Traverse webhook entries and process incoming text messages.
	 *
	 * @param array<string, mixed> $payload Decoded payload.
	 * @return void
	 */
	private function process_payload( array $payload ): void {
		$entries = $payload['entry'] ?? array();

		if ( ! is_array( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['changes'] ) || ! is_array( $entry['changes'] ) ) {
				continue;
			}

			foreach ( $entry['changes'] as $change ) {
				$value    = is_array( $change ) && isset( $change['value'] ) && is_array( $change['value'] ) ? $change['value'] : array();
				$messages = $value['messages'] ?? array();

				if ( ! is_array( $messages ) ) {
					continue;
				}

				foreach ( $messages as $message ) {
					if ( is_array( $message ) ) {
						$this->process_message( $message );
					}
				}
			}
		}
	}

	/**
	 * Convert one valid incoming text message.
	 *
	 * @param array<string, mixed> $message Message payload.
	 * @return void
	 */
	private function process_message( array $message ): void {
		if (
			'text' !== ( $message['type'] ?? null ) ||
			! isset( $message['text']['body'] ) ||
			! is_string( $message['text']['body'] ) ||
			! isset( $message['id'], $message['from'] ) ||
			! is_scalar( $message['id'] ) ||
			! is_scalar( $message['from'] )
		) {
			return;
		}

		$message_id = sanitize_text_field( (string) $message['id'] );
		$wa_id      = sanitize_text_field( (string) $message['from'] );

		if (
			! preg_match( '/\A[\x21-\x7E]{1,255}\z/', $message_id ) ||
			! preg_match( '/\A[0-9]{5,32}\z/', $wa_id ) ||
			! preg_match( '/\bWWC-[A-Z0-9]{8}\b/', $message['text']['body'], $token_match )
		) {
			return;
		}

		$converted_at = $this->get_conversion_time( $message['timestamp'] ?? null );
		$this->convert_intent( $token_match[0], $message_id, $wa_id, $converted_at );
	}

	/**
	 * Atomically convert a pending, non-expired intent.
	 *
	 * @param string $token        Intent token.
	 * @param string $message_id   Meta message ID.
	 * @param string $wa_id        Sender WhatsApp ID.
	 * @param string $converted_at UTC conversion time.
	 * @return void
	 */
	private function convert_intent( string $token, string $message_id, string $wa_id, string $converted_at ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wwc_intents';
		$now         = gmdate( 'Y-m-d H:i:s' );
		$wa_id_hash  = hash_hmac( 'sha256', $wa_id, wp_salt( 'auth' ) );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET status = %s, wa_id_hash = %s, message_id = %s, converted_at = %s
				WHERE token = %s AND status = %s AND expires_at >= %s",
				'converted',
				$wa_id_hash,
				$message_id,
				$converted_at,
				$token,
				'pending',
				$now
			)
		);

		if ( 1 !== $updated ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET status = %s WHERE token = %s AND status = %s AND expires_at < %s",
					'expired',
					$token,
					'pending',
					$now
				)
			);

			return;
		}

		$intent_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE token = %s", $token )
		);

		if ( $intent_id > 0 ) {
			do_action( 'wwc_intent_converted', $intent_id, $converted_at );
		}
	}

	/**
	 * Normalize the Meta message timestamp.
	 *
	 * @param mixed $timestamp Raw timestamp.
	 * @return string
	 */
	private function get_conversion_time( $timestamp ): string {
		$now = time();

		if ( is_scalar( $timestamp ) && preg_match( '/\A[0-9]{10}\z/', (string) $timestamp ) ) {
			$message_time = (int) $timestamp;

			if ( $message_time >= $now - DAY_IN_SECONDS && $message_time <= $now + ( 5 * MINUTE_IN_SECONDS ) ) {
				return gmdate( 'Y-m-d H:i:s', $message_time );
			}
		}

		return gmdate( 'Y-m-d H:i:s', $now );
	}

	/**
	 * Read a dotted Meta verification parameter after PHP normalization.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $name    Dotted parameter name.
	 * @return string|null
	 */
	private function get_verification_parameter( WP_REST_Request $request, string $name ): ?string {
		$value = $request->get_param( $name );

		if ( null === $value ) {
			$value = $request->get_param( str_replace( '.', '_', $name ) );
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( (string) $value ) );

		return '' === $value ? null : $value;
	}
}
