<?php
/**
 * Plugin Name:       WordPress WhatsApp Conversions
 * Plugin URI:        https://github.com/monklock/wordpress-whatsapp-conversions
 * Description:       Tracks confirmed WhatsApp conversations through Meta webhooks and sends verified leads to GA4.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            monklock
 * Text Domain:       wordpress-whatsapp-conversions
 *
 * @package WordPressWhatsAppConversions
 */

defined( 'ABSPATH' ) || exit;

define( 'WWC_VERSION', '1.0.0' );
define( 'WWC_DB_VERSION', '1.0.0' );
define( 'WWC_PLUGIN_FILE', __FILE__ );
define( 'WWC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once WWC_PLUGIN_DIR . 'includes/class-wwc-activator.php';
require_once WWC_PLUGIN_DIR . 'includes/class-wwc-deactivator.php';

register_activation_hook( __FILE__, array( 'WWC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WWC_Deactivator', 'deactivate' ) );

require_once WWC_PLUGIN_DIR . 'includes/class-wwc.php';

/**
 * Run the plugin.
 *
 * @return void
 */
function wwc_run(): void {
	$plugin = new WWC();
	$plugin->run();
}

wwc_run();
