<?php
/**
 * Main TK_Post_Syndication plugin file
 *
 * @package TK_Post_Syndication
 */

/*
Plugin Name:       TK Post Syndication
Plugin URI:        http://trewknowledge.com/
Description:       Synchronise posts and comments between blogs in a multisite network
Version:           0.1.0
Author:            Trew Knowledge / Fernando Claussen
Author URI:        http://trewknowledge.com/
License:           GPL-2.0+
License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain:       tkps
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

register_activation_hook( __FILE__, function( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		$sites = get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
				add_option( 'tkps_post_types', array('post', 'page') );
			restore_current_blog();
		}
	} else {
		die( esc_html__( 'This plugin is meant to be used as a network wide activation. Go to your network dashboard and click on "Network Enable".', 'tk-post-syndicate' ) );
	}
} );

require_once( 'class-helpers.php' );
require_once( 'class-tk-post-syndication.php' );
