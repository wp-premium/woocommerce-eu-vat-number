jQuery(function(){
	jQuery( 'form.checkout, form#order_review').on( 'change', 'select#billing_country', function() {
		var country         = jQuery('select#billing_country').val();
		var check_countries = wc_eu_vat_params.eu_countries;

		if ( country && jQuery.inArray( country, check_countries ) >= 0 ) {
			jQuery('#woocommerce_eu_vat_number').fadeIn();
		} else {
			jQuery('#woocommerce_eu_vat_number').fadeOut();
		}
	});
	jQuery('select#billing_country').change();
});
