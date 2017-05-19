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

	/**
	 * Stores the Origin Site gmt offset
	 * @var float $origin_gmt_offset
	 */
	public $origin_gmt_offset;

	public function __construct() {
		$this->setup( 'tk-post-syndication' );
		$this->version = '0.1.0';
		$this->origin_gmt_offset = get_option( 'gmt_offset' );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_sync_meta_box' ) );
			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
			add_action( 'load-post.php', array( $this, 'block_synced_post_edit' ) );

			add_action( 'wp_trash_post', array( $this, 'trash_post' ) );
			add_action( 'before_delete_post', array( $this, 'delete_synced_posts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_ajax_update_author', array( $this, 'update_author_ajax_callback' ) );
		} else {
			add_action( 'comment_post', array( $this, 'sync_comments' ), 10, 3 );
			add_action( 'preprocess_comment', array( $this, 'preprocess_comment' ) );
			add_action( 'get_comments_number', array( $this, 'get_comments_number' ), 10, 2 );
		}
	}

	/**
	 * Checks if a user belongs to a blog and if he has publish and edit capabilities
	 * @param  int $user_id The user ID
	 * @param  int $blog_id The blog ID
	 * @return bool         True if user can publish and edit on target blog
	 */
	private function user_can_for_blog( $user_id, $blog_id ) {
		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			return false;
		}

		switch_to_blog( $blog_id );
		if ( user_can( $user_id, 'publish_posts' ) && user_can( $user_id, 'edit_published_posts' ) ) {
			$user_can = true;
		} else {
			$user_can = false;
		}
		restore_current_blog();

		return $user_can;
	}

	/**
	 * Gets the sites the chosen user is a member of and is capable of publishing and editing.
	 * Ignores the current site
	 * See: user_can_for_blog
	 * @param  int $user_id The chosen user ID
	 * @return Array        Returns an array where the site ID is the Key and the site name is the value
	 */
	private function get_user_sites( $user_id ) {
		$sites = get_sites();
		$user_sites = array();
		foreach ( $sites as $blog ) {
			if ( absint( $blog->blog_id ) !== get_current_blog_id() ) {
				if ( $this->user_can_for_blog( $user_id, $blog->blog_id ) ) {
					$site_details = get_blog_details( $blog->blog_id );
					$user_sites[ $blog->blog_id ] = $site_details->blogname;
				}
			}
		}

		return $user_sites;
	}

	/**
	 * Loads and localizes the javascript
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'tk_post_syndication', plugin_dir_url( __FILE__ ) . 'js/tk-post-syndication.js', array( 'jquery' ), $this->version, true );
		wp_localize_script( 'tk_post_syndication', 'AJAX', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'tkps_ajax_nonce' ),
			'pluginfolder' => plugin_dir_url( __FILE__ ),
		) );
	}

	/**
	 * Function used by the ajax call. This sends some info to the JS so it can update the metabox accordingly
	 * @return json Sends a json response to the ajax call
	 */
	public function update_author_ajax_callback() {
		$response = array(
			'error' => false,
			'msg' => esc_html__( 'All good!', 'tk-post-syndication' ),
		);

		if ( ! wp_verify_nonce( $_POST['security'], 'tkps_ajax_nonce' ) ) {
			$response['error'] = true;
			$response['msg'] = esc_html__( 'Failed Security Checkpoint', 'tk-post-syndication' );
			wp_send_json( $response );
		}

		$new_author = esc_html( absint( $_POST['new_author'] ) );
		$user_sites = $this->get_user_sites( $new_author );

		$response['sites'] = $user_sites;

		$url = wp_get_referer();
		$post_id = explode( 'post=', $url );
		$post_id = explode( '&', $post_id[1] );
		$response['existing_meta'] = get_post_meta( $post_id[0], 'tkps_sync_with', true );

		wp_send_json( $response );
	}

	/**
	 * Adds our metabox to the post edit page
	 */
	public function add_sync_meta_box() {
		$this->add_meta_box( 'sync-meta-box', esc_html__( 'Post Syndication', 'tk-post-syndication' ), 'sync_meta_box_callback', 'post', 'side', 'high' );
	}

	/**
	 * Prints the metabox contents to the post edit page.
	 * @param  WP_Post $post    The post object
	 * @return void
	 */
	public function sync_meta_box_callback( $post ) {
		$user_sites = $this->get_user_sites( $post->post_author );
		foreach ( $user_sites as $blog_id => $blogname ) {
			$existing_meta = get_post_meta( $post->ID, 'tkps_sync_with', true );
			?>
			<label>
				<input type="checkbox" <?php if ( is_array( $existing_meta ) && in_array( $blog_id, $existing_meta, false ) ) { echo 'checked="checked"'; } ?> name="tkps_sites_to_sync[]" value="<?php echo esc_attr( $blog_id ); ?>" />
				<?php echo esc_html( $blogname ); ?>
			</label>
			<br>
		<?php
		}
	}

	/**
	 * Find the ID of a media item, given it's URL.
	 *
	 * @param string $image_url URL to the media item.
	 *
	 * @return int Media item's ID
	 */
	protected function get_image_id( $image_url ) {
		global $wpdb;
		// Try to retrieve the attachment ID from the cache.
		$cache_key = 'image_id_' . md5( $image_url );
		$attachment = wp_cache_get( $cache_key, 'tkps' );
		if ( false === $attachment ) {
			// Query the DB to get the attachment ID.
			// @codingStandardsIgnoreStart
			$attachment = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT ID FROM ' . $wpdb->prefix . 'posts' . " WHERE guid='%s';",
					$image_url
				)
			);
			// @codingStandardsIgnoreEnd
			// Store attachment ID in the cache.
			wp_cache_set( $cache_key, $attachment, 'tkps' );
		}
		// ID should be the first element of the returned array.
		if ( is_array( $attachment ) && isset( $attachment[0] ) ) {
			return $attachment[0];
		}
		return false;
	}

	/**
	 * The most important method. This is triggered when the post is saved. Not necessarily published.
	 * @param  int $post_id The post ID
	 * @param  WP_Post $post    The post Object
	 * @return void
	 */
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

				if ( ! is_user_member_of_blog( $post->post_author, $site ) ) {
					continue;
				}

				if ( ! user_can( $post->post_author, 'publish_posts' ) || ! user_can( $post->post_author, 'edit_published_posts' ) ) {
					restore_current_blog();
					continue;
				}

				$gmt_offset = get_option( 'gmt_offset' );
				$time_diff = $this->origin_gmt_offset - $gmt_offset;

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

				$post_date = new DateTime( $post->post_date );
				if ( $time_diff < 0 ) {
					$post_date->add( new DateInterval( 'PT' . abs( $time_diff ) . 'H' ) );
				} else {
					$post_date->sub( new DateInterval( 'PT' . abs( $time_diff ) . 'H' ) );
				}

				$orig_post_data = array(
					'ID' => $posts_to_update[ $site ] ? $posts_to_update[ $site ] : 0,
					'post_author' => $post->post_author,
					'post_content' => $post->post_content,
					'post_title' => $post->post_title,
					'post_status' => $post->post_status,
					'post_type' => $post->post_type,
					'post_name' => $post->post_name,
					'post_date' => $post_date->format( 'Y-m-d H:i:s' ),
					'comment_status' => $post->comment_status,
					'ping_status' => $post->ping_status,
					'tax_input' => array(
						'post_tag' => $parent_post_tags,
					),
				);

				// Fix so wp_insert_post does not run an infinite amount of times
				remove_action( 'save_post', array( $this, 'save_post' ) );
				$target_post_id = wp_insert_post( $orig_post_data );
				add_action( 'save_post', array( $this, 'save_post' ) );

				$posts_arr[ $site ] = $target_post_id;

				if ( isset( $feat_image ) && $feat_image ) {
					$target_feat_image = media_sideload_image( $feat_image, $target_post_id );
					if ( is_wp_error( $target_feat_image ) ) {
						error_log( "Failed to add featured image to post $target_post_id" );
						return;
					}
					$array = array();
					preg_match( "/src='([^']*)'/i", $target_feat_image, $array );

					$target_feat_image = $this->get_image_id( $array[1] );
					$target_feat_image_data = wp_generate_attachment_metadata( $target_feat_image, get_attached_file( $target_feat_image ) );
					update_post_meta( $target_post_id, '_thumbnail_id', $target_feat_image );
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

				update_post_meta( $target_post_id, 'tkps_parent_post_id', array(
					$parent_blog_id => $post->ID,
				) );
				restore_current_blog();
			}// End foreach().
			update_post_meta( $post_id, 'tkps_posts_to_update', $posts_arr );
			update_post_meta( $post_id, 'tkps_sync_with', $_POST['tkps_sites_to_sync'] );
		}// End if().
	}

	/**
	 * Checks if a post has a parent.
	 * @param  int $post_id The post ID. Defaults to the current post ID
	 * @return array        Returns an array containing the parent blog ID and post ID
	 */
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

	/**
	 * Runs when a user tries to go into the edit page for a post. If the post has
	 * a parent, then block him from editing and display a link to the parent edit page.
	 * @return void
	 */
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

	/**
	 * Syncs the comment being made in a site with it's parent.
	 * @param  int $comment_id  The comment ID
	 * @param  int|string $approved   1 if the comment is approved, 0 if not, 'spam' if spam.
	 * @param  array $commentdata The comment data
	 * @return void
	 */
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

	/**
	 * Grabs the comment parent and store it
	 * @param  array $commentdata The comment data
	 * @return array              The modified $commentdata array
	 */
	public function preprocess_comment( $commentdata ) {
		$this->comment_parent = absint( $commentdata['comment_parent'] );
		return $commentdata;
	}

	/**
	 * Get the parent comment count so children display the right amount
	 * @param  int $count The comment count
	 * @param  int $post_id The post ID
	 * @return int          The total comment count
	 */
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

	/**
	 * Delete the child posts when the master is deleted
	 * @param  int $post_id The post ID
	 * @return void
	 */
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

	/**
	 * Trashes the child posts when the master is sent to trash
	 * @param  int $post_id The post ID
	 * @return void
	 */
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
