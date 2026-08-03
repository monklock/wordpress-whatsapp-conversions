<?php
/**
 * Core plugin class.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

require_once WWC_PLUGIN_DIR . 'includes/class-wwc-loader.php';

/**
 * Coordinate plugin hooks and shared maintenance.
 */
class WWC {

	/**
	 * Hook loader.
	 *
	 * @var WWC_Loader
	 */
	private WWC_Loader $loader;

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->loader = new WWC_Loader();
		$this->define_core_hooks();
	}

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * Ensure the maintenance schedule exists.
	 *
	 * @return void
	 */
	public function ensure_maintenance_schedule(): void {
		if ( ! wp_next_scheduled( 'wwc_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wwc_daily_maintenance' );
		}
	}

	/**
	 * Mark pending intents as expired after their seven-day lifetime.
	 *
	 * @return void
	 */
	public function run_daily_maintenance(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wwc_intents';
		$now         = current_time( 'mysql', true );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET status = %s WHERE status = %s AND expires_at < %s",
				'expired',
				'pending',
				$now
			)
		);
	}

	/**
	 * Register core hooks.
	 *
	 * @return void
	 */
	private function define_core_hooks(): void {
		$this->loader->add_action( 'init', $this, 'ensure_maintenance_schedule' );
		$this->loader->add_action( 'wwc_daily_maintenance', $this, 'run_daily_maintenance' );
	}
}
