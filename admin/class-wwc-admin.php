<?php
/**
 * Administration report.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render privacy-safe WhatsApp lead metrics.
 */
class WWC_Admin {

	/**
	 * Register the report below WooCommerce or Tools.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$woocommerce_active = class_exists( 'WooCommerce' );
		$parent_slug        = $woocommerce_active ? 'woocommerce' : 'tools.php';
		$capability         = $woocommerce_active ? 'manage_woocommerce' : 'manage_options';

		add_submenu_page(
			$parent_slug,
			__( 'WhatsApp Leads', 'wordpress-whatsapp-conversions' ),
			__( 'WhatsApp Leads', 'wordpress-whatsapp-conversions' ),
			$capability,
			'wwc-leads',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the report page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';

		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to view this report.', 'wordpress-whatsapp-conversions' ) );
		}

		$filters = $this->get_filters();
		$metrics = $this->get_metrics( $filters );
		$leads   = $this->get_recent_leads( $filters );
		$page_url = class_exists( 'WooCommerce' ) ? 'admin.php?page=wwc-leads' : 'tools.php?page=wwc-leads';

		require WWC_PLUGIN_DIR . 'admin/partials/wwc-admin-display.php';
	}

	/**
	 * Read and validate the cohort date filters.
	 *
	 * @return array{date_from:string,date_to:string,utc_from:string,utc_to:string,error:string}
	 */
	private function get_filters(): array {
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$utc_from  = '';
		$utc_to    = '';
		$error     = '';

		$from = $this->parse_date( $date_from );
		$to   = $this->parse_date( $date_to );

		if ( '' !== $date_from && null === $from ) {
			$error     = __( 'Invalid start date. The filter was not applied.', 'wordpress-whatsapp-conversions' );
			$date_from = '';
		}

		if ( '' !== $date_to && null === $to ) {
			$error   = __( 'Invalid end date. The filter was not applied.', 'wordpress-whatsapp-conversions' );
			$date_to = '';
		}

		if ( null !== $from ) {
			$utc_from = $from->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		}

		if ( null !== $to ) {
			$utc_to = $to->setTime( 23, 59, 59 )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		}

		if ( '' !== $utc_from && '' !== $utc_to && $utc_from > $utc_to ) {
			$error     = __( 'The start date must not be later than the end date. The filter was not applied.', 'wordpress-whatsapp-conversions' );
			$date_from = '';
			$date_to   = '';
			$utc_from  = '';
			$utc_to    = '';
		}

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'utc_from'  => $utc_from,
			'utc_to'    => $utc_to,
			'error'     => $error,
		);
	}

	/**
	 * Parse an exact site-timezone calendar date.
	 *
	 * @param string $value Date in Y-m-d format.
	 * @return DateTimeImmutable|null
	 */
	private function parse_date( string $value ): ?DateTimeImmutable {
		if ( '' === $value ) {
			return null;
		}

		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
		$errors = DateTimeImmutable::getLastErrors();

		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $date->format( 'Y-m-d' ) !== $value ) {
			return null;
		}

		return $date;
	}

	/**
	 * Load aggregate cohort metrics.
	 *
	 * @param array{utc_from:string,utc_to:string} $filters Date filters.
	 * @return array<string, int|float>
	 */
	private function get_metrics( array $filters ): array {
		global $wpdb;

		$table_name          = $wpdb->prefix . 'wwc_intents';
		list( $where, $args ) = $this->build_where( $filters );
		$sql                 = "SELECT
			COUNT(*) AS intents,
			SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) AS confirmed,
			COUNT(DISTINCT CASE WHEN status = 'converted' THEN wa_id_hash ELSE NULL END) AS unique_senders,
			SUM(CASE WHEN status = 'converted' AND ga4_status = 'sent' THEN 1 ELSE 0 END) AS ga4_sent,
			SUM(CASE WHEN status = 'converted' AND ga4_status = 'failed' THEN 1 ELSE 0 END) AS ga4_failed,
			SUM(CASE WHEN status = 'converted' AND ga4_status = 'skipped' THEN 1 ELSE 0 END) AS ga4_skipped
			FROM {$table_name} {$where}";

		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args );
		}

		$row       = $wpdb->get_row( $sql, ARRAY_A );
		$intents   = isset( $row['intents'] ) ? (int) $row['intents'] : 0;
		$confirmed = isset( $row['confirmed'] ) ? (int) $row['confirmed'] : 0;

		return array(
			'intents'         => $intents,
			'confirmed'       => $confirmed,
			'unique_senders'  => isset( $row['unique_senders'] ) ? (int) $row['unique_senders'] : 0,
			'conversion_rate' => $intents > 0 ? round( ( $confirmed / $intents ) * 100, 2 ) : 0.0,
			'ga4_sent'        => isset( $row['ga4_sent'] ) ? (int) $row['ga4_sent'] : 0,
			'ga4_failed'      => isset( $row['ga4_failed'] ) ? (int) $row['ga4_failed'] : 0,
			'ga4_skipped'     => isset( $row['ga4_skipped'] ) ? (int) $row['ga4_skipped'] : 0,
		);
	}

	/**
	 * Load recent converted intents without personal data.
	 *
	 * @param array{utc_from:string,utc_to:string} $filters Date filters.
	 * @return array<int, array<string, string|int>>
	 */
	private function get_recent_leads( array $filters ): array {
		global $wpdb;

		$table_name          = $wpdb->prefix . 'wwc_intents';
		list( $where, $args ) = $this->build_where( $filters );
		$where              .= " AND status = 'converted'";
		$sql                 = "SELECT converted_at, source, source_url,
			CASE WHEN gclid IS NOT NULL OR gbraid IS NOT NULL OR wbraid IS NOT NULL THEN 1 ELSE 0 END AS has_ad_id,
			ga4_status
			FROM {$table_name} {$where}
			ORDER BY converted_at DESC, id DESC LIMIT 20";

		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build a prepared cohort condition based on intent creation time.
	 *
	 * @param array{utc_from:string,utc_to:string} $filters Date filters.
	 * @return array{0:string,1:array<int, string>}
	 */
	private function build_where( array $filters ): array {
		$clauses = array( '1=1' );
		$args    = array();

		if ( '' !== $filters['utc_from'] ) {
			$clauses[] = 'created_at >= %s';
			$args[]    = $filters['utc_from'];
		}

		if ( '' !== $filters['utc_to'] ) {
			$clauses[] = 'created_at <= %s';
			$args[]    = $filters['utc_to'];
		}

		return array( 'WHERE ' . implode( ' AND ', $clauses ), $args );
	}
}
