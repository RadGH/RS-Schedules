<?php
/*
Plugin Name: RS Schedule
Description: Adds a <code>schedule</code> post type, allowing you to create standard and recurring events. The events can be seen on a Calendar page which uses the FullCalendar API with the RRule integration. On the day of each event, a cron action is triggered, allowing you to run custom code or send notifications on a customizable schedule.
Version: 1.0.2
Author: Radley Sustaire
Author URI: https://radleysustaire.com
Date Created: 6/27/2025
GitHub Plugin URI: https://github.com/RadGH/RS-Schedules
GitHub Branch: master
Alchemy Update URI: https://plugins.zingmap.com/plugin/rs-schedules/
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'RS_SCHED_PATH', __DIR__ );
define( 'RS_SCHED_URL', untrailingslashit(plugin_dir_url(__FILE__)) );
define( 'RS_SCHED_VERSION', '1.0.2' );

class RS_Schedule_Plugin {
	
	/**
	 * Checks that required plugins are loaded before continuing
	 * @return void
	 */
	public static function load_plugin() {
		
		// Check for required plugins
		$missing_plugins = array();
		
		if ( ! class_exists( 'ACF' ) ) {
			$missing_plugins[] = 'Advanced Custom Fields Pro';
		}
		
		if ( $missing_plugins ) {
			self::add_admin_notice( '<strong>RS Schedule:</strong> The following plugins are required: ' . implode( ', ', $missing_plugins ) . '.', 'error' );
			
			return;
		}
		
		// Load the plugin updater
		if ( ! class_exists('Alchemy_Updater') ) {
			require_once( RS_SCHED_PATH . '/includes/alchemy-updater.php' );
		}
		
		// Load plugin files
		require_once( RS_SCHED_PATH . '/includes/cron.php' );
		require_once( RS_SCHED_PATH . '/includes/post-type-schedule.php' );
		require_once( RS_SCHED_PATH . '/includes/settings.php' );
		
		// After the plugin has been activated, flush rewrite rules, upgrade database, etc.
		add_action( 'admin_init', array( __CLASS__, 'after_plugin_activated' ) );
		
	}
	
	/**
	 * When the plugin is activated, set up the post types and refresh permalinks
	 */
	public static function on_plugin_activation() {
		update_option( 'rs_sched_plugin_activated', 1, true );
	}
	
	/**
	 * Flush rewrite rules if the option is set
	 * @return void
	 */
	public static function after_plugin_activated() {
		if ( get_option( 'rs_sched_plugin_activated' ) ) {
			
			// Flush rewrite rules
			// flush_rewrite_rules();
			
			// Upgrade the database
			// require_once( RS_SCHED_PATH . '/includes/database.php' );
			// do_action( 'rs_schedule/plugin_activated' );
			
			// Clear the option
			update_option( 'rs_sched_plugin_activated', 0, true );
			
		}
	}
	
	/**
	 * Adds an admin notice to the dashboard's "admin_notices" hook.
	 *
	 * @param string $message The message to display
	 * @param string $type    The type of notice: info, error, warning, or success. Default is "info"
	 * @param bool $format    Whether to format the message with wpautop()
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'info', $format = true ) {
		add_action( 'admin_notices', function() use ( $message, $type, $format ) {
			?>
			<div class="notice notice-<?php
			echo $type; ?>">
				<?php
				echo $format ? wpautop( $message ) : $message; ?>
			</div>
			<?php
		} );
	}
	
	/**
	 * Add a link to the settings page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public static function add_settings_link( $links ) {
		// array_unshift( $links, '<a href="edit.php?post_type=something">Settings</a>' );
		return $links;
	}
	
}

// Add a link to the settings page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'RS_Schedule_Plugin', 'add_settings_link' ) );

// When the plugin is activated, set up the post types and refresh permalinks
register_activation_hook( __FILE__, array( 'RS_Schedule_Plugin', 'on_plugin_activation' ) );

// Initialize the plugin
add_action( 'plugins_loaded', array( 'RS_Schedule_Plugin', 'load_plugin' ), 20 );

