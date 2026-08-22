<?php
/**
 * Plugin Name:       Roga Forms
 * Plugin URI:        https://gazteco.fr/roga
 * Description:       Conversational forms that ask one question at a time: branching logic, stored submissions, notifications and acknowledgements. No ads, no limits, no third-party calls.
 * Version:           1.1.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Gazte Co.
 * Author URI:        https://gazteco.fr/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       roga
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'ROGA_VERSION', '1.1.4' );
define( 'ROGA_FILE', __FILE__ );
define( 'ROGA_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROGA_URL', plugin_dir_url( __FILE__ ) );

require_once ROGA_DIR . 'includes/class-roga-install.php';
require_once ROGA_DIR . 'includes/class-roga-forms.php';
require_once ROGA_DIR . 'includes/class-roga-logic.php';
require_once ROGA_DIR . 'includes/class-roga-entries.php';
require_once ROGA_DIR . 'includes/class-roga-mailer.php';
require_once ROGA_DIR . 'includes/class-roga-render.php';
require_once ROGA_DIR . 'includes/class-roga-rest.php';
require_once ROGA_DIR . 'includes/class-roga-admin.php';
require_once ROGA_DIR . 'includes/class-roga-updater.php';

register_activation_hook( __FILE__, array( 'ROGA_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ROGA_Install', 'deactivate' ) );

/**
 * Boot the plugin once WordPress is ready.
 */
function roga_boot() {
	load_plugin_textdomain( 'roga', false, dirname( plugin_basename( ROGA_FILE ) ) . '/languages' );

	ROGA_Forms::init();
	ROGA_Render::init();
	ROGA_Rest::init();
	ROGA_Updater::init();

	if ( is_admin() ) {
		ROGA_Admin::init();
	}

	// Runs the schema upgrade when the plugin files are updated without reactivation.
	ROGA_Install::maybe_upgrade();
}
add_action( 'plugins_loaded', 'roga_boot' );

/**
 * Product name, as shown in the admin menu and page titles.
 *
 * @return string
 */
function roga_brand() {
	return apply_filters( 'roga_brand', __( 'Roga Forms', 'roga' ) );
}

/**
 * Attribution line, shown once on the main screen rather than in the menu.
 * Return an empty string to hide it entirely.
 *
 * @return string
 */
function roga_byline() {
	return apply_filters( 'roga_byline', __( 'by Gazte Co.', 'roga' ) );
}

/**
 * Convenience accessor used across the plugin.
 *
 * @return string The capability required to manage forms and entries.
 */
function roga_capability() {
	return apply_filters( 'roga_capability', 'manage_options' );
}
