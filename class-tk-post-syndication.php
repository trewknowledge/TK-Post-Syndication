<?php
/**
 * File contains the main TK Post Syndication class
 *
 * @package TK_Post_Syndication
 */

require_once( 'class-helpers.php' );

class TK_Post_Syndication extends TK_Post_Syndication_Helper {

	/**
	 * A version number for cache busting, etc.
	 *
	 * @var int A version tag for cache-busting
	 */
	public $version;

	/**
	 * The comment parent as passed from the preprocess_comment hook
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var 		 int			 $comment_parent		The parent comment ID
	 */
	private $comment_parent;

	public function __construct() {
		$this->setup( 'tk-post-syndication' );
		$this->version = '0.1.0';

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_sync_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
			add_action( 'load-post.php', array( $this, 'block_synced_post_edit' ) );

			add_action( 'wp_trash_post', array( $this, 'trash_post' ) );
			add_action( 'before_delete_post', array( $this, 'delete_synced_posts' ) );
		} else {
			add_action( 'comment_post', array( $this, 'sync_comments' ), 10, 3 );
			add_action( 'preprocess_comment', array( $this, 'preprocess_comment' ) );
			add_action( 'get_comments_number', array( $this, 'get_comments_number' ), 10, 2 );
		}
	}

	public function add_sync_meta_box() {
		$this->add_meta_box( 'sync-meta-box', esc_html__( 'Post Syndication', 'tk-post-syndication' ), 'sync_meta_box_callback', 'post', 'side', 'high', array( 'sites' => get_sites() ) );
	}

	public function sync_meta_box_callback( $post, $metabox ) {
		foreach ( $metabox['args']['sites'] as $blog ) {
			if ( absint( $blog->blog_id ) !== get_current_blog_id() ) {
				if ( is_user_member_of_blog( get_current_user_id(), $blog->blog_id ) && current_user_can_for_blog( $blog->blog_id, 'author' ) || current_user_can_for_blog( $blog->blog_id, 'editor' ) || current_user_can_for_blog( $blog->blog_id, 'administrator' ) ) {
					$site_details = get_blog_details( $blog->blog_id );
					$existing_meta = get_post_meta( $post->ID, 'tkps_sync_with', true );
					?>
					<label>
						<input type="checkbox" <?php if ( is_array( $existing_meta ) && in_array( $blog->blog_id, $existing_meta, true ) ) { echo 'checked="checked"'; } ?> name="tkps_sites_to_sync[]" value="<?php echo esc_attr( $blog->blog_id ); ?>" />
						<?php echo esc_html( $site_details->blogname ); ?>
					</label>
					<br>
					<?php
				}
			}
		}
	}

	public function save_post( $post_id, $post ) {
		// Autosave, do nothing
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		// AJAX? Not used here
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Return if it's a post revision
		if ( false !== wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['tkps_sites_to_sync'] ) && $_POST['tkps_sites_to_sync'] ) {
			$posts_to_update = get_post_meta( $post_id, 'tkps_posts_to_update', true );
			$posts_arr = array();
			$parent_blog_id = get_current_blog_id();

			if ( has_post_thumbnail( $post_id ) ) {
				$feat_image = get_the_post_thumbnail_url( $post_id, 'full' );
			}

			$parent_post_format = get_post_format( $post_id );
			$parent_category_terms = get_the_terms( $post_id, 'category' );
			$parent_post_tags = get_the_terms( $post_id, 'post_tag' );
			$parent_post_tags = wp_list_pluck( $parent_post_tags, 'name' );

			foreach ( $_POST['tkps_sites_to_sync'] as $site ) {
				switch_to_blog( $site );

				$categories_arr = array();
				if ( $parent_category_terms ) {
					foreach ( $parent_category_terms as $term ) {

						// Adds or Updates the category on the child site
						wp_insert_term(
							$term->name,
							'category',
							array(
							'description' => $term->description,
							'slug'    => $term->slug,
							)
						);

						$categories_arr[] = $term->name;

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
					update_post_meta( $target_post_id, 'tkps_parent_featured_image_url', $feat_image );
				} else {
					delete_post_thumbnail( $target_post_id );
				}

				// Clear all categories
				wp_set_object_terms( $target_post_id, null, 'category' );
				foreach ( $categories_arr as $cat ) {
					// Set the categories
					wp_set_object_terms( $target_post_id, $cat, 'category', true );
				}
				// Sets the post format
				set_post_format( $target_post_id , $parent_post_format );

				update_post_meta( $target_post_id, 'tkps_parent_post_id', array( $parent_blog_id => $post->ID ) );
				restore_current_blog();
			}
			update_post_meta( $post_id, 'tkps_posts_to_update', $posts_arr );
			update_post_meta( $post_id, 'tkps_sync_with', $_POST['tkps_sites_to_sync'] );
		}
	}

	public static function get_master_post( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( $master_post = get_post_meta( $post_id, 'tkps_parent_post_id', true ) ) {
			return array(
				'blog_id' => key( $master_post ),
				'post_id' => current( $master_post ),
			);
		}

		return false;
	}

	public function block_synced_post_edit() {
		if ( $master_post = self::get_master_post( absint( $_GET['post'] ) ) ) {
			$parent_blog_id = $master_post['blog_id'];
			$parent_post_id = $master_post['post_id'];

			if ( $parent_blog_id && $parent_post_id ) {
				$edit_url = get_admin_url( $parent_blog_id ) . 'post.php?action=edit&post=' . absint( $parent_post_id );
				$edit_link = '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'edit post', 'tk-post-syndication' ) . '</a>';
				$message = sprintf( __( 'Sorry, you must edit this post from the original site: %1$s', 'tk-post-syndication' ), $edit_link );

				wp_die( wp_kses_post( $message ) );
			}
		}
	}

	public function sync_comments( $comment_id, $approved, $commentdata ) {
		if ( $master_post = self::get_master_post( $commentdata['comment_post_ID'] ) ) {
			$parent_blog_id = $master_post['blog_id'];
			$parent_post_id = $master_post['post_id'];
			if ( $parent_blog_id && $parent_post_id ) {
				$commentdata['comment_post_ID'] = $parent_post_id;
				$commentdata['comment_parent'] = $this->comment_parent;
				switch_to_blog( $parent_blog_id );
					wp_insert_comment( $commentdata );
				restore_current_blog();
			}
		}
	}

	public function preprocess_comment( $commentdata ) {
		$this->comment_parent = absint( $commentdata['comment_parent'] );
		return $commentdata;
	}

	public function get_comments_number( $count, $post_id ) {
		if ( $master_post = self::get_master_post( $post_id ) ) {
			$parent_blog_id = $master_post['blog_id'];
			$parent_post_id = $master_post['post_id'];

			if ( $parent_blog_id && $parent_post_id ) {
				switch_to_blog( $parent_blog_id );
				$count = wp_count_comments( $parent_post_id );
				restore_current_blog();
			}
			return $count->total_comments;
		}
		return $count;
	}

	public function delete_synced_posts( $post_id ) {
		$posts_to_update = get_post_meta( $post_id, 'tkps_posts_to_update', true );

		if ( $posts_to_update ) {
			foreach ( $posts_to_update as $blog => $post ) {
				switch_to_blog( $blog );
					remove_action( 'wp_delete_post', array( $this, 'trash_post' ) );
					$target_post_id = wp_delete_post( $post );
					add_action( 'wp_delete_post', array( $this, 'trash_post' ) );
				restore_current_blog();
			}
		}
	}

	public function trash_post( $post_id ) {
		$posts_to_update = get_post_meta( $post_id, 'tkps_posts_to_update', true );

		if ( $posts_to_update ) {
			foreach ( $posts_to_update as $blog => $post ) {
				switch_to_blog( $blog );
					remove_action( 'wp_trash_post', array( $this, 'trash_post' ) );
					$target_post_id = wp_trash_post( $post );
					add_action( 'wp_trash_post', array( $this, 'trash_post' ) );
				restore_current_blog();
			}
		}
	}


}

$tk_post_syndication = new TK_Post_Syndication();
