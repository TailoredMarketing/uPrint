jQuery(document).ready( function($) {
	$('.application-button').live( 'click', function( e ) {
			e.preventDefault();
			var thislink = $(this);
			var data = {
				'action'  : 'ajax_applcation_decision',
				'decision': $(this).data('decision'),
				'post'    : $(this).data('post')
			};
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			$.post(ajaxurl, data, function(response) {
				if( response.status == 1) {
					location.reload();
				} else if( response.status == 2 ) {
					window.location.replace( response.redirect );
				}
			}, 'json');
	});
});