<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://trewknowledge.com
 * @since      1.0.0
 *
 * @package    TK_Post_Syndication
 * @subpackage TK_Post_Syndication/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    TK_Post_Syndication
 * @subpackage TK_Post_Syndication/admin
 * @author     Fernando Claussen <fernandoclaussen@gmail.com>
 */
class TK_Post_Syndication_Admin {

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
	 * The comment parent as passed from the preprocess_comment hook
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var 		 int			 $comment_parent		The parent comment ID
	 */
	private $comment_parent;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $tk_post_syndication       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $tk_post_syndication, $version ) {

		$this->tk_post_syndication = $tk_post_syndication;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->tk_post_syndication, plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->tk_post_syndication, plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), $this->version, false );

	}

	public function add_sync_meta_box() {
		add_meta_box( 'sync-meta-box', __('Should this post be synced between sites on this network?', 'tk-post-syndication'), array($this, 'sync_meta_box_callback'), 'post', 'side', 'high', array( 'sites' => get_sites() ) );
	}

	public function sync_meta_box_callback($post, $metabox){
		foreach ( $metabox['args']['sites'] as $site ) {
			if ( absint( $site->blog_id ) !== get_current_blog_id() ) {
				if ( is_user_member_of_blog( get_current_user_id(), $site->blog_id ) && current_user_can_for_blog( $site->blog_id, 'author' ) ) {
					$site_details = get_blog_details( $site->blog_id );
					$existing_meta = get_post_meta( $post->ID, 'sync_with', true );
					?>
					<label>
						<input type="checkbox" <?php if( in_array( $site->blog_id, $existing_meta ) ) { echo 'checked="checked"'; } ?> name="sites_to_sync[]" value="<?php echo $site->blog_id; ?>" /> <?php echo $site_details->blogname; ?>
					</label>
					<br>
					<?php
				}
			}
		}
	}

	public function save_post($post_id, $post) {
		// Autosave, do nothing
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
      return;
		// AJAX? Not used here
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
      return;
		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) )
      return;
		// Return if it's a post revision
		if ( false !== wp_is_post_revision( $post_id ) )
      return;

		if ( isset( $_POST['sites_to_sync'] ) && $_POST['sites_to_sync'] ) {
			$posts_to_update = get_post_meta( $post_id, 'posts_to_update', true );
			$posts_arr = array();
			$parent_blog_id = get_current_blog_id();
			foreach ( $_POST['sites_to_sync'] as $site ) {
				switch_to_blog( $site );
				$postarr = array(
					'ID' => $posts_to_update[ $site ] ? $posts_to_update[ $site ] : 0,
					'post_content' => $post->post_content,
					'post_title' => $post->post_title,
					'post_excerpt' => $post->post_excerpt,
					'post_status' => $post->post_status,
					'post_type' => $post->post_type,
					'post_name' => $post->post_name,
					'comment_status' => $post->comment_status,
					'ping_status' => $post->ping_status,
				);
				remove_action( 'save_post', array( $this, 'save_post' ) );
					$target_post_id = wp_insert_post( $postarr );
				add_action( 'save_post', array( $this, 'save_post' ) );

				$posts_arr[ $site ] = $target_post_id;

				update_post_meta( $target_post_id, 'parent_post_id', array( $parent_blog_id => $post->ID ) );
				restore_current_blog();
			}
			update_post_meta( $post_id, 'posts_to_update', $posts_arr );
			update_post_meta( $post_id, 'sync_with', $_POST['sites_to_sync'] );
		}
	}

	public function get_master_post( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$master_post = get_post_meta( $post_id, 'parent_post_id', true );

		if ( $master_post ) {
			return array(
				'blog_id' => key( $master_post ),
				'post_id' => current( $master_post ),
			);
		}

		return false;
	}

	public function block_synced_post_edit() {
		$master_post = $this->get_master_post( $_GET[ 'post' ] );
		if ( $master_post ) {
			$parent_blog_id = $master_post[ 'blog_id' ];
			$parent_post_id = $master_post[ 'post_id' ];

			if ( $parent_blog_id && $parent_post_id ) {
				wp_die(
					sprintf(
						__('Sorry, you must edit this post from the original site: <a href="%s">edit post</a>', 'tk-post-syndication'),
						get_admin_url($parent_blog_id, '/post.php?post=' . $parent_post_id . '&action=edit')
					)
				);
			}
		}
	}

	public function sync_comments( $comment_ID, $approved, $commentdata ) {
		$master_post = $this->get_master_post( $commentdata[ 'comment_post_ID' ] );
		if ( $master_post ) {
			$parent_blog_id = $master_post[ 'blog_id' ];
			$parent_post_id = $master_post[ 'post_id' ];

			if ( $parent_blog_id && $parent_post_id ) {
				$commentdata[ 'comment_post_ID' ] = $parent_post_id;
				$commentdata[ 'comment_parent' ] = $this->comment_parent;

				switch_to_blog( $parent_blog_id );
					wp_insert_comment( $commentdata );
				restore_current_blog();
			}
		}
	}

	public function preprocess_comment( $commentdata ) {
		$this->comment_parent = absint($commentdata['comment_parent']);
		return $commentdata;
	}

}
