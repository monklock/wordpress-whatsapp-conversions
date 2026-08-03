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
		self::install_or_upgrade();
		self::schedule_maintenance();
	}

	/**
	 * Upgrade an existing installation when the schema version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( WWC_DB_VERSION === get_option( 'wwc_db_version' ) ) {
			return;
		}

		self::install_or_upgrade();
	}

	/**
	 * Install the generic schema and preserve rows from the legacy table.
	 *
	 * @return void
	 */
	private static function install_or_upgrade(): void {
		$legacy_table = self::migrate_legacy_table();

		self::create_table();

		if ( null !== $legacy_table && self::table_exists( $legacy_table ) ) {
			self::copy_legacy_rows( $legacy_table );
		}

		self::cleanup_legacy_schedules();
		delete_option( implode( '_', array( 'cb', 'wa', 'db', 'version' ) ) );
		update_option( 'wwc_db_version', WWC_DB_VERSION, false );
	}

	/**
	 * Rename the legacy table when the generic table does not exist yet.
	 *
	 * @return string|null Legacy table name when it may still contain rows.
	 */
	private static function migrate_legacy_table(): ?string {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'wwc_intents';
		$legacy_name = $wpdb->prefix . implode( '_', array( 'cb', 'wa', 'intents' ) );

		if ( ! self::table_exists( $legacy_name ) ) {
			return null;
		}

		if ( self::table_exists( $table_name ) ) {
			return $legacy_name;
		}

		if ( ! self::is_safe_identifier( $legacy_name ) || ! self::is_safe_identifier( $table_name ) ) {
			return $legacy_name;
		}

		// Table identifiers cannot be passed as values to wpdb::prepare().
		$wpdb->query( "RENAME TABLE `{$legacy_name}` TO `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return self::table_exists( $legacy_name ) ? $legacy_name : null;
	}

	/**
	 * Create or update the generic intents table.
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
	}

	/**
	 * Copy rows when a database does not support the table rename operation.
	 *
	 * @param string $legacy_table Legacy table name.
	 * @return void
	 */
	private static function copy_legacy_rows( string $legacy_table ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wwc_intents';

		if ( ! self::is_safe_identifier( $legacy_table ) || ! self::is_safe_identifier( $table_name ) ) {
			return;
		}

		$columns = 'token, source, source_url, gclid, gbraid, wbraid, ga_client_id, ga_session_id, status, wa_id_hash, message_id, created_at, expires_at, converted_at, ga4_status, ga4_sent_at';

		// Both identifiers are validated before interpolation.
		$wpdb->query( "INSERT IGNORE INTO `{$table_name}` ({$columns}) SELECT {$columns} FROM `{$legacy_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool
	 */
	private static function table_exists( string $table_name ): bool {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
		);

		return $table_name === $found;
	}

	/**
	 * Validate an interpolated database identifier.
	 *
	 * @param string $identifier Database identifier.
	 * @return bool
	 */
	private static function is_safe_identifier( string $identifier ): bool {
		return 1 === preg_match( '/\A[A-Za-z0-9_]+\z/', $identifier );
	}

	/**
	 * Remove schedules created with the legacy hook prefix.
	 *
	 * @return void
	 */
	private static function cleanup_legacy_schedules(): void {
		$legacy_parts  = array( 'cb', 'wa' );
		$legacy_prefix = implode( '_', $legacy_parts );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), implode( '-', $legacy_parts ) );
		}

		wp_unschedule_hook( $legacy_prefix . '_daily_maintenance' );
		wp_unschedule_hook( $legacy_prefix . '_send_ga4_event' );
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
