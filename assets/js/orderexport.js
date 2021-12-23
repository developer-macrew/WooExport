jQuery(document).ready(function() {
	jQuery('.wpg-datepicker').datepicker({
			dateFormat : 'yy-mm-dd',
			timeFormat:  "HH:mm"
		}
	);

	/**
	 * Export csv ajax
	 */
	jQuery('.wpg-order-export').on('click', function(e){

		e.preventDefault();
		var rel=jQuery(this).attr('rel');
		var data = jQuery(this).parents('form').serialize() + '&rel=' + rel;
		// data.push({ name: "rel", value: rel });

		jQuery.post( ajaxurl, data, function(response){

			response = jQuery.parseJSON(response);
			if( response.error === false ) {
				window.location = window.location.href+'&filename='+response.msg+'&downloadname='+response.downloadname+'&oe=1';
			}else{
				jQuery('.wpg-response-msg').html( response.msg ).addClass('wpg-error');
			}

		});
	});

	/**
	 * Advanced options
	 */
	jQuery('#woo-soe-advanced').on('click', function(e){
		e.preventDefault();
		jQuery('.woo-soe-advanced').slideToggle();
	});
	
});