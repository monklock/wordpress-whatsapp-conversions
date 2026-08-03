<?php
/**
 * Plugin deactivation tasks.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clear plugin schedules without deleting conversion data.
 */
class WWC_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'wwc_daily_maintenance' );
		wp_clear_scheduled_hook( 'wwc_send_ga4_event' );
	}
}
