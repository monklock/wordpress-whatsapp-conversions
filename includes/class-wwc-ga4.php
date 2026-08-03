<?php
/**
 * GA4 Measurement Protocol delivery.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedule and send confirmed WhatsApp lead events.
 */
class WWC_GA4 {

	/**
	 * Action Scheduler group.
	 */
	public const ACTION_GROUP = 'wwc';

	/**
	 * GA4 collection endpoint.
	 */
	private const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

	/**
	 * Schedule a converted intent for background delivery.
	 *
	 * @param int    $intent_id    Intent ID.
	 * @param string $converted_at UTC conversion time.
	 * @return void
	 */
	public function schedule_event( int $intent_id, string $converted_at = '' ): void {
		unset( $converted_at );

		$intent = $this->get_pending_intent( $intent_id );

		if ( null === $intent ) {
			return;
		}

		if ( empty( $intent['ga_client_id'] ) ) {
			$this->mark_status( $intent_id, 'skipped' );

			return;
		}

		$args = array( $intent_id );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			$action_id = as_schedule_single_action(
				time() + 1,
				'wwc_send_ga4_event',
				$args,
				self::ACTION_GROUP,
				true
			);

			if ( $action_id > 0 ) {
				return;
			}
		}

		if ( ! wp_next_scheduled( 'wwc_send_ga4_event', $args ) ) {
			wp_schedule_single_event( time() + 1, 'wwc_send_ga4_event', $args, true );
		}
	}

	/**
	 * Send one pending lead event to GA4.
	 *
	 * @param int $intent_id Intent ID.
	 * @return void
	 */
	public function send_event( int $intent_id ): void {
		global $wpdb;

		$lock_name = 'wwc_ga4_' . $intent_id;
		$has_lock  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) );

		if ( 1 !== $has_lock ) {
			return;
		}

		try {
			$intent = $this->get_pending_intent( $intent_id );

			if ( null === $intent ) {
				return;
			}

			if ( empty( $intent['ga_client_id'] ) ) {
				$this->mark_status( $intent_id, 'skipped' );

				return;
			}

			$configuration = $this->get_configuration();

			if ( null === $configuration ) {
				$this->mark_status( $intent_id, 'failed' );

				return;
			}

			$payload = $this->build_payload( $intent );
			$url     = add_query_arg(
				array(
					'measurement_id' => $configuration['measurement_id'],
					'api_secret'     => $configuration['api_secret'],
				),
				self::ENDPOINT
			);

			$response = wp_remote_post(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 0,
					'headers'     => array( 'Content-Type' => 'application/json' ),
					'body'        => wp_json_encode( $payload ),
					'data_format' => 'body',
				)
			);

			if ( is_wp_error( $response ) ) {
				$this->mark_status( $intent_id, 'failed' );

				return;
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );

			if ( $status_code >= 200 && $status_code < 300 ) {
				$this->mark_status( $intent_id, 'sent', gmdate( 'Y-m-d H:i:s' ) );
			} else {
				$this->mark_status( $intent_id, 'failed' );
			}
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/**
	 * Schedule converted intents left pending after an interrupted request.
	 *
	 * @return void
	 */
	public function schedule_pending_events(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wwc_intents';
		$intent_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE status = %s AND ga4_status = %s ORDER BY id ASC LIMIT 100",
				'converted',
				'pending'
			)
		);

		foreach ( $intent_ids as $intent_id ) {
			$this->schedule_event( (int) $intent_id );
		}
	}

	/**
	 * Build a privacy-safe Measurement Protocol payload.
	 *
	 * @param array<string, mixed> $intent Intent row.
	 * @return array<string, mixed>
	 */
	private function build_payload( array $intent ): array {
		$source_path = is_string( $intent['source_url'] ) && '' !== $intent['source_url'] ? $intent['source_url'] : '/';
		$params      = array(
			'engagement_time_msec' => 100,
			'whatsapp_source'      => (string) $intent['source'],
			'source_path'          => substr( $source_path, 0, 100 ),
			'lead_type'            => 'message',
		);

		if ( isset( $intent['ga_session_id'] ) && is_string( $intent['ga_session_id'] ) && preg_match( '/\A[1-9][0-9]{0,18}\z/', $intent['ga_session_id'] ) ) {
			$params['session_id'] = (int) $intent['ga_session_id'];
		}

		$payload = array(
			'client_id' => (string) $intent['ga_client_id'],
			'events'    => array(
				array(
					'name'   => 'whatsapp_lead',
					'params' => $params,
				),
			),
		);

		$converted_timestamp = isset( $intent['converted_at'] ) ? strtotime( (string) $intent['converted_at'] . ' UTC' ) : false;

		if ( false !== $converted_timestamp && $converted_timestamp >= time() - ( 72 * HOUR_IN_SECONDS ) ) {
			$payload['timestamp_micros'] = $converted_timestamp * 1000000;
		}

		return $payload;
	}

	/**
	 * Read and validate server-side GA4 configuration.
	 *
	 * @return array<string, string>|null
	 */
	private function get_configuration(): ?array {
		if (
			! defined( 'WWC_GA4_MEASUREMENT_ID' ) ||
			! is_scalar( WWC_GA4_MEASUREMENT_ID ) ||
			! defined( 'WWC_GA4_API_SECRET' ) ||
			! is_scalar( WWC_GA4_API_SECRET )
		) {
			return null;
		}

		$measurement_id = strtoupper( trim( sanitize_text_field( (string) WWC_GA4_MEASUREMENT_ID ) ) );
		$api_secret     = trim( (string) WWC_GA4_API_SECRET );

		if ( ! preg_match( '/\AG-[A-Z0-9]+\z/', $measurement_id ) || '' === $api_secret || strlen( $api_secret ) > 255 ) {
			return null;
		}

		return array(
			'measurement_id' => $measurement_id,
			'api_secret'     => $api_secret,
		);
	}

	/**
	 * Load one converted intent awaiting GA4 delivery.
	 *
	 * @param int $intent_id Intent ID.
	 * @return array<string, mixed>|null
	 */
	private function get_pending_intent( int $intent_id ): ?array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wwc_intents';
		$intent     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d AND status = %s AND ga4_status = %s",
				$intent_id,
				'converted',
				'pending'
			),
			ARRAY_A
		);

		return is_array( $intent ) ? $intent : null;
	}

	/**
	 * Update GA4 delivery status while it is still pending.
	 *
	 * @param int         $intent_id Intent ID.
	 * @param string      $status    New status.
	 * @param string|null $sent_at   UTC sent time.
	 * @return void
	 */
	private function mark_status( int $intent_id, string $status, ?string $sent_at = null ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wwc_intents';
		$wpdb->update(
			$table_name,
			array(
				'ga4_status'  => $status,
				'ga4_sent_at' => $sent_at,
			),
			array(
				'id'         => $intent_id,
				'ga4_status' => 'pending',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	}
}
