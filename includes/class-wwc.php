<?php
/**
 * Core plugin class.
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

require_once WWC_PLUGIN_DIR . 'includes/class-wwc-loader.php';
require_once WWC_PLUGIN_DIR . 'includes/class-wwc-ga4.php';
require_once WWC_PLUGIN_DIR . 'includes/class-wwc-webhook.php';
require_once WWC_PLUGIN_DIR . 'public/class-wwc-public.php';

if ( is_admin() ) {
	require_once WWC_PLUGIN_DIR . 'admin/class-wwc-admin.php';
}

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
		$this->define_public_hooks();
		$this->define_webhook_hooks();
		$this->define_ga4_hooks();
		$this->define_admin_hooks();
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
		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( 'wwc_daily_maintenance', array(), WWC_GA4::ACTION_GROUP ) ) {
				as_schedule_recurring_action(
					time() + HOUR_IN_SECONDS,
					DAY_IN_SECONDS,
					'wwc_daily_maintenance',
					array(),
					WWC_GA4::ACTION_GROUP,
					true
				);
			}

			wp_unschedule_hook( 'wwc_daily_maintenance' );

			return;
		}

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
		$this->loader->add_action( 'action_scheduler_ensure_recurring_actions', $this, 'ensure_maintenance_schedule' );
		$this->loader->add_action( 'wwc_daily_maintenance', $this, 'run_daily_maintenance' );
	}

	/**
	 * Register public hooks.
	 *
	 * @return void
	 */
	private function define_public_hooks(): void {
		$public = new WWC_Public();

		$this->loader->add_action( 'template_redirect', $public, 'handle_redirect', 1 );
	}

	/**
	 * Register webhook hooks.
	 *
	 * @return void
	 */
	private function define_webhook_hooks(): void {
		$webhook = new WWC_Webhook();

		$this->loader->add_action( 'rest_api_init', $webhook, 'register_routes' );
		$this->loader->add_filter( 'rest_pre_serve_request', $webhook, 'serve_plain_challenge', 10, 4 );
	}

	/**
	 * Register GA4 hooks.
	 *
	 * @return void
	 */
	private function define_ga4_hooks(): void {
		$ga4 = new WWC_GA4();

		$this->loader->add_action( 'wwc_intent_converted', $ga4, 'schedule_event', 10, 2 );
		$this->loader->add_action( 'wwc_send_ga4_event', $ga4, 'send_event' );
		$this->loader->add_action( 'wwc_daily_maintenance', $ga4, 'schedule_pending_events' );
	}

	/**
	 * Register administration hooks.
	 *
	 * @return void
	 */
	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}

		$admin = new WWC_Admin();

		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
	}
}
