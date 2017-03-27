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

	public function add_sync_meta_box() {
		add_meta_box( 'sync-meta-box', __('Should this post be synced between sites on this network?', 'tk-post-syndication'), array($this, 'sync_meta_box_callback'), 'post', 'side', 'high', array( 'sites' => get_sites() ) );
	}

	public function sync_meta_box_callback($post, $metabox){
		foreach ( $metabox['args']['sites'] as $site ) {
			if ( absint( $site->blog_id ) !== get_current_blog_id() ) {
				if ( is_user_member_of_blog( get_current_user_id(), $site->blog_id ) && current_user_can_for_blog( $site->blog_id, 'author' ) || current_user_can_for_blog( $site->blog_id, 'editor' ) || current_user_can_for_blog( $site->blog_id, 'administrator' ) ) {
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

			if ( has_post_thumbnail( $post_id ) ) {
				$feat_image = get_the_post_thumbnail_url( $post_id, 'full' );
			}

			$parent_post_format = get_post_format( $post_id );
			$parent_category_terms = wp_get_post_categories( $post_id, array( 'fields' => 'all' ) );
			$parent_post_tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

			foreach ( $_POST['sites_to_sync'] as $site ) {
				switch_to_blog( $site );

				$categoriesArr = array();
				if ( $parent_category_terms ) {
					foreach ( $parent_category_terms as $term ) {
						// Adds or Updates the category on the child site
						wp_insert_term(
			        $term->name,
			        'category',
			        array(
			          'description' => $term->description,
			          'slug'    => $term->slug
		          )
		        );
						$categoriesArr[] = $term->name;
			    }
				}

				$postarr = array(
					'ID' => $posts_to_update[ $site ] ? $posts_to_update[ $site ] : 0,
					'post_content' => $post->post_content,
					'post_title' => $post->post_title,
					'post_status' => $post->post_status,
					'post_type' => $post->post_type,
					'post_name' => $post->post_name,
					'comment_status' => $post->comment_status,
					'ping_status' => $post->ping_status,
					'tax_input' => array(
						'post_tag' => $parent_post_tags,
					),
				);

				// Fix so wp_insert_post does not run an infinite amount of times
				remove_action( 'save_post', array( $this, 'save_post' ) );
				$target_post_id = wp_insert_post( $postarr );
				add_action( 'save_post', array( $this, 'save_post' ) );

				$posts_arr[ $site ] = $target_post_id;

				if ( isset( $feat_image ) && $feat_image ) {
					update_post_meta( $target_post_id, 'parent_featured_image_url', $feat_image );
				} else {
					delete_post_thumbnail( $target_post_id );
				}

				// Clear all categories
				wp_set_object_terms( $target_post_id, null, 'category' );
				foreach ( $categoriesArr as $cat ) {
					// Set the categories
					wp_set_object_terms( $target_post_id, $cat, 'category', true );
				}
				// Sets the post format
				set_post_format( $target_post_id , $parent_post_format);

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
