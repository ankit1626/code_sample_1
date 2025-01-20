<?php
/**
 * Plugin Name: WDM Customization
 * Plugin URI:  https://github.com/ankit1626/code_sample_1
 * Description: This plugins allows the users and the admin to create teams and manage them.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0.30
 * Author:      Ankit Parekh
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wdm-customization
 * Domain Path: /languages
 *
 * @package wdm-customization
 */

define( 'WDM_CUSTOMIZATION_DIR', __DIR__ );
define( 'WDM_CUSTOMIZATION_BASENAME', plugin_basename( __FILE__ ) );
define( 'WDM_DT_FORMAT', 'Y-m-d H:i' );
define( 'WDM_TIMEZONE', get_option( 'timezone_string' ) );

if ( ! function_exists( 'wdm_get_mepr_events' ) ) {
	/**
	 * Returns an array of event types.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $only_values Whether to return only the event type values.
	 *
	 * @return array The event types.
	 */
	function wdm_get_mepr_events( bool $only_values = true ) {
		if ( ! class_exists( 'MeprOptions' ) || ! method_exists( 'MeprOptions', 'fetch' ) || ! method_exists( 'MeprOptions', 'get_custom_field' ) ) {
			return array();
		}
		$mepr_options     = MeprOptions::fetch();
		$mepr_cstm_fields = $mepr_options->get_custom_field( 'mepr_bh' );
		if ( empty( $mepr_cstm_fields ) ) {
			return array();
		}
		$event_types = $mepr_cstm_fields->options;
		if ( ! is_array( $event_types ) || empty( $event_types ) ) {
			return array();
		}
		if ( $only_values ) {
			return array_map( fn( $event ) => $event->option_value, $event_types );
		}
		return $event_types;
	}
}

require_once WDM_CUSTOMIZATION_DIR . '/includes/class-events.php';
require_once WDM_CUSTOMIZATION_DIR . '/includes/class-event-registration.php';
require_once WDM_CUSTOMIZATION_DIR . '/includes/class-team-requests.php';
require_once WDM_CUSTOMIZATION_DIR . '/includes/class-db-initializer.php';
require_once WDM_CUSTOMIZATION_DIR . '/includes/class-db-helper.php';
require_once WDM_CUSTOMIZATION_DIR . '/admin/class-wdm-customizer-settings.php';
require_once WDM_CUSTOMIZATION_DIR . '/admin/class-wdm-ajax-calls.php';

register_activation_hook(
	__FILE__,
	fn() => DB_Initializer::get_instance()
);

WDM_Ajax_Calls::get_instance();
WDM_Customizer_Settings::get_instance();
