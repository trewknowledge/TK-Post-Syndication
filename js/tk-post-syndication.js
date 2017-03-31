jQuery(function( $ ) {
	'use strict';

	$('#post_author_override').change(function(){
		var new_author_id = $(this).val();

		$.ajax({
			url:  AJAX.ajax_url,
			type: 'post',
			dataType: 'json',
			data: {
				action: 'update_author',
				new_author: new_author_id,
				security: AJAX.nonce,
			},
			beforeSend: function() {
				$('#sync-meta-box .inside').html('<img src="' + AJAX.pluginfolder + 'img/loading.gif" style="display:block; margin:20px auto; width:50px;" />');
			},
			success: function(response) {
				if( response.error === false ) {
					$('#sync-meta-box .inside').html('');
					for (var site in response.sites) {
						var checked = '';
						if ( $.inArray( site, response.existing_meta ) > -1 ) {
							checked = 'checked="checked"';
						}
						$('#sync-meta-box .inside').append('<label><input type="checkbox" ' + checked + ' name="tkps_sites_to_sync[]" value="' + site + '" />' + response.sites[ site ] + '</label><br>');
					}
				}
				console.log(response.msg);
			}
		});
	});


});
