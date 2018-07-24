<div class="wrap">
	<h1><?php esc_html_e( 'Post Syndication', 'tk-post-syndication' ); ?></h1>

	<form action="options.php" method="post">
	<?php settings_fields( 'tk-post-syndication' ); ?>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="tkps_post_types"><?php echo esc_html_x( 'Post Types', 'Admin - Settings', 'tk-post-syndication' ); ?>:</label>
				</th>
				<td>
					<ul>
						<?php foreach ( $post_types as $k => $v ): ?>
							<li>
								<label>
									<input type="checkbox" name="tkps_post_types[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $selected_pt ), true ) ?>><?php echo esc_html( $v->label ) ?>
								</label>
							</li>
						<?php endforeach ?>
					</ul>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button(); ?>
</form>

</div>
