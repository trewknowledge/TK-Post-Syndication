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

require_once( 'class-helpers.php' );
require_once( 'class-tk-post-syndication.php' );
