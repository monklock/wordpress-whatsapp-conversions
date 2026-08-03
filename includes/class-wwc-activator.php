<?php
/**
 * Plugin activation tasks.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create the database schema and maintenance schedule.
 */
class WWC_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_table();
		self::schedule_maintenance();
	}

	/**
	 * Create or update the intents table.
	 *
	 * @return void
	 */
	private static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'wwc_intents';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			source varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			source_url text NULL,
			gclid varchar(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
			gbraid varchar(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
			wbraid varchar(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
			ga_client_id varchar(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
			ga_session_id varchar(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
			status varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
			wa_id_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
			message_id varchar(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
			created_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			converted_at datetime NULL,
			ga4_status varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
			ga4_sent_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			UNIQUE KEY message_id (message_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY converted_at (converted_at),
			KEY wa_id_hash (wa_id_hash)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wwc_db_version', WWC_DB_VERSION, false );
	}

	/**
	 * Schedule daily maintenance with WP-Cron.
	 *
	 * @return void
	 */
	private static function schedule_maintenance(): void {
		if ( ! wp_next_scheduled( 'wwc_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wwc_daily_maintenance' );
		}
	}
}
