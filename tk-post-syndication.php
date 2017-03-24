<?php

/**
 * @link              http://trewknowledge.com
 * @since             1.0.0
 * @package           TK_Post_Syndication
 *
 * @wordpress-plugin
 * Plugin Name:       TK Post Syndication
 * Plugin URI:        http://trewknowledge.com/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Trew Knowledge, Fernando Claussen
 * Author URI:        http://trewknowledge.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       tk-post-syndication
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.class.php
 */
function activate_tk_post_syndication() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/activator.class.php';
	TK_Post_Syndication_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.class.php
 */
function deactivate_tk_post_syndication() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/deactivator.class.php';
	TK_Post_Syndication_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_tk_post_syndication' );
register_deactivation_hook( __FILE__, 'deactivate_tk_post_syndication' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/tk-post-syndication.class.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_tk_post_syndication() {

	$plugin = new TK_Post_Syndication();
	$plugin->run();

}
run_tk_post_syndication();
