<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://trewknowledge.com
 * @since      1.0.0
 *
 * @package    TK_Post_Syndication
 * @subpackage TK_Post_Syndication/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    TK_Post_Syndication
 * @subpackage TK_Post_Syndication/public
 * @author     Fernando Claussen <fernandoclaussen@gmail.com>
 */
class TK_Post_Syndication_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $tk_post_syndication    The ID of this plugin.
	 */
	private $tk_post_syndication;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $tk_post_syndication       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $tk_post_syndication, $version ) {

		$this->tk_post_syndication = $tk_post_syndication;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in TK_Post_Syndication_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The TK_Post_Syndication_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->tk_post_syndication, plugin_dir_url( __FILE__ ) . 'css/public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in TK_Post_Syndication_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The TK_Post_Syndication_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->tk_post_syndication, plugin_dir_url( __FILE__ ) . 'js/public.js', array( 'jquery' ), $this->version, false );

	}

}
