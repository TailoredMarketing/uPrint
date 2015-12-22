jQuery(document).ready( function($) {

	$('.add-order-row').live( 'click', function(e) {
		e.preventDefault();
		var $tr    = $('.tr_clone:last');
		var $clone = $tr.clone();
		$clone.find('input').val('');
		$tr.after($clone);
	});

	$('.delete-order-row').live( 'click', function(e) {
		e.preventDefault();
		var $tr    = $(this).closest('.tr_clone');
	    if( $('.tr_clone').length > 1 ) {
	    	$tr.remove();
	    } else {
	    	$('.tr_clone').find('input').val('');
	    }
	});

	$('[data-toggle="tooltip"]').tooltip();
	$('.tooltip_show').tooltip();

	$('.show_order_modal').click(function(e) {
		var target = $(this).data('target');
		var order  = $(this).data('order');

		var data = {
			'action'  : 'ajax_order_modal',
			'order'   : order
		};
		$.post(ajax_object.ajax_url, data, function(response) {
			$(target).find('.modal-body').html(response);
			$(target).modal('show');
		}, 'html');
	});

	$('#applyPostcode').keyup( function() {
		$('.postCodeAlert').remove();
		var postcode = $('#applyPostcode');
		if( postcode.val().length >= 3 ) {
			var data = {
				'action'  	: 'ajax_location_lookup',
				'postcode'  : postcode.val()
			};
			$.post(ajax_object.ajax_url, data, function(response) {
				//$(target).find('.modal-body').html(response);
				//$(target).modal('show');
				if( response.status == 1 || response.status == 2 ) {
					$('.postCodeAlert').remove();
					postcode.after('<div id="" class="alert alert-info postCodeAlert" role="alert"><strong>Notice</strong> Your application will be sent to '+response.loc_name+'.</div>');
					$('#locationID').val(response.location);
				} else {
					$('#locationID').val('');
				}
				console.log( response );
			}, 'json');	
		}
	});

});