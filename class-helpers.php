<?php
/**
 * Plugin helper
 *
 * @package TK_Post_Syndication
 */


class TK_Post_Syndication_Helper {

	/**
	 * The name of this plugin
	 *
	 * @var string
	 **/
	protected $name;

	/**
	 * The filepath to the directory containing this plugin
	 *
	 * @var string
	 **/
	protected $dir;

	/**
	 * The URL for the directory containing this plugin
	 *
	 * @var string
	 **/
	protected $url;

	/**
	 * The name of the directory containing this plugin, e.g. "my-plugin"
	 *
	 * @var string
	 **/
	protected $folder;

	/**
	 * Useful for switching between debug and compressed scripts.
	 *
	 * @var string
	 **/
	protected $suffix;

	/**
	 * Records the type of this class, either 'plugin' or 'theme'.
	 *
	 * @var string
	 **/
	protected $type;

	/**
	 * Note the name of the function to call when the theme is activated.
	 *
	 * @var string
	 **/
	protected $theme_activation_function;

	/**
	 * Initiate!
	 *
	 * @param string      $name The plugin/theme name.
	 * @param null|string $type Either plugin or theme, to denote what we're using.
	 *
	 * @throws exception Exception.
	 * @author Simon Wheatley
	 */
	public function setup( $name = '', $type = null ) {

		// Requrie the name parameter so that we can set things up.
		if ( ! $name ) {
			throw new exception( 'Please pass the name parameter into the setup method.' );
		}
		$this->name = $name;

		// Discover the plugin directory.
		$dir_separator = ( defined( 'DIRECTORY_SEPARATOR' ) ) ? DIRECTORY_SEPARATOR : '\\';
		$file = str_replace( $dir_separator, '/', __FILE__ );
		$plugins_dir = str_replace( $dir_separator, '/', WP_PLUGIN_DIR );

		// Setup the dir and url for this plugin/theme.
		if ( 'theme' === $type ) {

			// This is a theme so set up the theme directory and URL accordingly.
			$this->type = 'theme';
			$this->dir  = get_stylesheet_directory();
			$this->url  = get_stylesheet_directory_uri();

		} elseif ( stripos( $file, $plugins_dir ) !== false || 'plugin' === $type ) {

			// This is a plugin.
			$this->folder = trim( basename( dirname( $file ) ), '/' );
			$this->type   = 'plugin';

			// Allow someone to override the assumptions we're making here about where
			// the plugin is held. For example, if this plugin is included as part of
			// the files for a theme, in wp-content/themes/[your theme]/plugins/ then
			// you could hook `sil_plugins_dir` and `sil_plugins_url` to correct
			// our assumptions.
			// N.B. Because this code is running when the file is required, other plugins
			// may not be loaded and able to hook these filters!
			$plugins_dir = apply_filters( 'sil_plugins_dir', $plugins_dir, $this->name );
			$plugins_url = apply_filters( 'sil_plugins_url', plugins_url(), $this->name );
			$this->dir   = trailingslashit( $plugins_dir ) . $this->folder . '/';
			$this->url   = trailingslashit( $plugins_url ) . $this->folder . '/';

		}

		// Suffix for enqueuing scripts and styles, makes for easier debugging.
		$this->suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';

		if ( is_admin() ) {
			// Admin notices.
			add_action( 'admin_notices', array( $this, '_admin_notices' ) );
		}

		// Localise!
		add_action( 'init', array( $this, 'load_locale' ) );

	}

	/**
	 * Hook called to change the locale directory.
	 *
	 * @return void
	 * @author © John Godley
	 **/
	function load_locale() {

		// Here we manually fudge the plugin locale as WP doesn't allow many options.
		$locale = get_locale();
		if ( empty( $locale ) ) {
			$locale = 'en_US';
		}

		$mofile = $this->dir( "/locale/$locale.mo" );
		load_textdomain( $this->name, $mofile );

	}

	/**
	 * Special activation function that takes into account the plugin directory
	 *
	 * @param string $pluginfile The plugin file location (i.e. __FILE__).
	 * @param string $function Optional function name, or default to 'activate'.
	 *
	 * @return void
	 * @author © John Godley
	 **/
	function register_activation( $pluginfile = __FILE__, $function = '' ) {

		if ( 'plugin' === $this->type ) {

			add_action( 'activate_' . basename( dirname( $pluginfile ) ) . '/' . basename( $pluginfile ),
			array( &$this, '' === $function ? 'activate' : $function ) );

		} elseif ( 'theme' === $this->type ) {

			$this->theme_activation_function = ( $function ) ? $function : 'activate';
			add_action( 'load-themes.php', array( & $this, 'theme_activation' ) );

		}

	}

	/**
	 * Special deactivation function that takes into account the plugin directory
	 *
	 * @param string $pluginfile The plugin file location (i.e. __FILE__).
	 * @param string $function Optional function name, or default to 'deactivate'.
	 *
	 * @return void
	 * @author © John Godley
	 **/
	function register_deactivation( $pluginfile, $function = '' ) {

		add_action( 'deactivate_' . basename( dirname( $pluginfile ) ) . '/' . basename( $pluginfile ),
		array( &$this, '' === $function ? 'deactivate' : $function ) );

	}

	/**
	 * Renders an admin template from this plugin's /templates-admin/ directory.
	 *
	 * @param string $template_file Template file location.
	 * @param array  $vars Array of template variables.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function render_admin( $template_file, $vars = null ) {

		// Try to render.
		if ( file_exists( $this->dir( "templates-admin/$template_file" ) ) ) {
			require( $this->dir( "templates-admin/$template_file" ) );
		} else {
			$msg = sprintf( __( 'This plugin admin template could not be found: %s' ),
			$this->dir( "templates-admin/$template_file" ) );
			echo sprintf(
				'<p style="background-color: #ffa; border: 1px solid red; color: #300; padding: 10px;">%s</p>',
				esc_html( $msg )
			);
		}

	}

	/**
	 * Hooks the WP admin_notices action to render any notices
	 * that have been set with the set_admin_notice method.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function _admin_notices() {

		$notices = $this->get_option( 'admin_notices' );
		$errors  = $this->get_option( 'admin_errors' );
		if ( $errors ) {
			foreach ( $errors as $error ) {
				$this->render_admin_error( $error );
			}
		}
		$this->delete_option( 'admin_errors' );
		if ( $notices ) {
			foreach ( $notices as $notice ) {
				$this->render_admin_notice( $notice );
			}
		}
		$this->delete_option( 'admin_notices' );

	}

	/**
	 * Echoes some HTML for an admin notice.
	 *
	 * @param string $notice The notice.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function render_admin_notice( $notice ) {

		echo sprintf(
			'<div class="updated"><p>%s</p></div>',
			esc_html( $notice )
		);

	}

	/**
	 * Echoes some HTML for an admin error.
	 *
	 * @param string $error The error.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function render_admin_error( $error ) {

		echo sprintf(
			'<div class="error"><p>%s</p></div>',
			esc_html( $error )
		);

	}

	/**
	 * Sets a string as an admin notice.
	 *
	 * @param string $msg A *localised* admin notice message.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function set_admin_notice( $msg ) {

		$notices    = (array) $this->get_option( 'admin_notices' );
		$notices[] = $msg;
		$this->update_option( 'admin_notices', $notices );

	}

	/**
	 * Sets a string as an admin error.
	 *
	 * @param string $msg A *localised* admin error message.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function set_admin_error( $msg ) {

		$errors    = (array) $this->get_option( 'admin_errors' );
		$errors[] = $msg;
		$this->update_option( 'admin_errors', $errors );

	}

	/**
	 * Takes a filename and attempts to find that in the designated plugin templates
	 * folder in the theme (defaults to main theme directory, but uses a custom filter
	 * to allow theme devs to specify a sub-folder for all plugin template files using
	 * this system).
	 *
	 * Searches in the STYLESHEETPATH before TEMPLATEPATH to cope with themes which
	 * inherit from a parent theme by just overloading one file.
	 *
	 * @param string $template_file A template filename to search for.
	 *
	 * @return string The path to the template file to use
	 * @author Simon Wheatley
	 **/
	protected function locate_template( $template_file ) {

		$located = '';

		// Get the sub-dir if one is specified.
		$sub_dir = apply_filters( 'sw_plugin_tpl_dir', '' );
		if ( $sub_dir ) {
			$sub_dir = trailingslashit( $sub_dir );
		}

		// If there's a tpl in a (child theme or theme with no child).
		if ( file_exists( get_stylesheet_directory() . "/$sub_dir" . $template_file ) ) {
			return get_stylesheet_directory() . "/$sub_dir" . $template_file;
		} // If there's a tpl in the parent of the current child theme.
		elseif ( file_exists( get_template_directory() . "/$sub_dir" . $template_file ) ) {
			return get_template_directory() . "/$sub_dir" . $template_file;
		} // Fall back on the bundled plugin template (N.B. no filtered subfolder involved).
		elseif ( file_exists( $this->dir( "templates/$template_file" ) ) ) {
			return $this->dir( "templates/$template_file" );
		}

		// Oh dear. We can't find the template.
		$msg = sprintf( __( 'This plugin template could not be found, perhaps you need to hook `sil_plugins_dir` and `sil_plugins_url`: %s' ),
		$this->dir( "templates/$template_file" ) );

		// Echo the message to the user.
		echo sprintf(
			'<p style="background-color: #ffa; border: 1px solid red; color: #300; padding: 10px;">%s</p>',
			esc_html( $msg )
		);

	}

	/**
	 * Register a WordPress meta box
	 *
	 * @param string     $id ID for the box, also used as a function name if none is given.
	 * @param string     $title Title for the box.
	 * @param string     $function Function name (optional).
	 * @param int        $page The type of edit page on which to show the box (post, page, link).
	 * @param string     $context e.g. 'advanced' or 'core' (optional).
	 * @param string|int $priority Priority, rough effect on the ordering (optional).
	 * @param mixed      $args Some arguments to pass to the callback function as part of a larger object (optional).
	 *
	 * @return void
	 * @author © John Godley
	 **/
	function add_meta_box( $id, $title, $function = '', $page, $context = 'advanced', $priority = 'default', $args = null ) {

		require_once( ABSPATH . 'wp-admin/includes/template.php' );
		add_meta_box(
			$id,
			$title,
			array( &$this, '' === $function ? $id : $function ),
			$page,
			$context,
			$priority,
			$args
		);

	}

	/**
	 * Add hook for shortcode tag.
	 *
	 * There can only be one hook for each shortcode. Which means that if another
	 * plugin has a similar shortcode, it will override yours or yours will override
	 * theirs depending on which order the plugins are included and/or ran.
	 *
	 * @param string        $tag Shortcode tag to be searched in post content.
	 * @param callable|null $function Hook to run when shortcode is found.
	 */
	protected function add_shortcode( $tag, $function = null ) {
		add_shortcode( $tag, array( &$this, '' === $function ? $tag : $function ) );
	}

	/**
	 * Returns the filesystem path for a file/dir within this plugin.
	 *
	 * @param string $path The path within this plugin, e.g. '/js/clever-fx.js'.
	 *
	 * @return string Filesystem path
	 * @author Simon Wheatley
	 **/
	protected function dir( $path ) {
		return trailingslashit( $this->dir ) . trim( $path, '/' );
	}

	/**
	 * Returns the URL for for a file/dir within this plugin.
	 *
	 * @param string $path The path within this plugin, e.g. '/js/clever-fx.js'.
	 *
	 * @return string URL
	 * @author Simon Wheatley
	 **/
	protected function url( $path ) {
		return esc_url( trailingslashit( $this->url ) . trim( $path, '/' ) );
	}

	/**
	 * Gets the value of an option named as per this plugin.
	 *
	 * @return mixed Whatever
	 * @author Simon Wheatley
	 **/
	protected function get_all_options() {
		return get_option( $this->name );
	}

	/**
	 * Sets the value of an option named as per this plugin.
	 *
	 * @param mixed $value Option value.
	 *
	 * @return mixed Whatever
	 * @author Simon Wheatley
	 **/
	protected function update_all_options( $value ) {
		return update_option( $this->name, $value );
	}

	/**
	 * Gets the value from an array index on an option named as per this plugin.
	 *
	 * @param string $key A string.
	 * @param mixed  $value The value.
	 *
	 * @return mixed Whatever
	 * @author Simon Wheatley
	 **/
	public function get_option( $key, $value = null ) {

		$option = get_option( $this->name );
		if ( ! is_array( $option ) || ! isset( $option[ $key ] ) ) {
			return $value;
		}

		return $option[ $key ];

	}

	/**
	 * Sets the value on an array index on an option named as per this plugin.
	 *
	 * @param string $key A string.
	 * @param mixed  $value Whatever.
	 *
	 * @return bool False if option was not updated and true if option was updated.
	 * @author Simon Wheatley
	 **/
	protected function update_option( $key, $value ) {

		$option         = get_option( $this->name );
		$option[ $key ] = $value;

		return update_option( $this->name, $option );

	}

	/**
	 * Deletes the array index on an option named as per this plugin.
	 *
	 * @param string $key A string.
	 *
	 * @return bool False if option was not updated and true if option was updated.
	 * @author Simon Wheatley
	 **/
	protected function delete_option( $key ) {

		$option = get_option( $this->name );
		if ( isset( $option[ $key ] ) ) {
			unset( $option[ $key ] );
		}

		return update_option( $this->name, $option );

	}

	/**
	 * Echoes out some JSON indicating that stuff has gone wrong.
	 *
	 * @param string $msg The error message.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function ajax_die( $msg ) {

		$data = array( 'msg' => $msg, 'success' => false );
		echo wp_json_encode( $data );
		// N.B. No 500 header.
		exit;

	}

	/**
	 * Truncates a string in a human friendly way.
	 *
	 * @param string $str The string to truncate.
	 * @param int    $num_words The number of words to truncate to.
	 *
	 * @return string The truncated string
	 * @author Simon Wheatley
	 **/
	protected function truncate( $str, $num_words ) {

		$str   = strip_tags( $str );
		$words = explode( ' ', $str );
		if ( count( $words ) > $num_words ) {
			$k             = $num_words;
			$use_dotdotdot = 1;
		} else {
			$k             = count( $words );
			$use_dotdotdot = 0;
		}
		$words   = array_slice( $words, 0, $k );
		$excerpt = trim( join( ' ', $words ) );
		$excerpt .= ( $use_dotdotdot ) ? '…' : '';

		return $excerpt;
	}

}
