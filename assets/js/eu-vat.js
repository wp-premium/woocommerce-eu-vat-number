jQuery(function(){
	function field_is_required( field, is_required ) {
		if ( is_required ) {
			field.find( 'label .optional' ).remove();
			field.addClass( 'validate-required' );

			if ( field.find( 'label .required' ).length === 0 ) {
				field.find( 'label' ).append(
					'&nbsp;<abbr class="required" title="' +
					wc_address_i18n_params.i18n_required_text +
					'">*</abbr>'
				);
			}
		} else {
			field.find( 'label .required' ).remove();
			field.removeClass( 'validate-required woocommerce-invalid woocommerce-invalid-required-field' );

			if ( field.find( 'label .optional' ).length === 0 ) {
				field.find( 'label' ).append( '&nbsp;<span class="optional">(' + wc_address_i18n_params.i18n_optional_text + ')</span>' );
			}
		}
	}
	jQuery( 'form.checkout, form#order_review').on( 'change', 'select#billing_country', function() {
		var country         = jQuery( 'select#billing_country' ).val();
		var check_countries = wc_eu_vat_params.eu_countries;
		var b2b_enabled     = wc_eu_vat_params.b2b_required;
		
		field_is_required( jQuery( '#woocommerce_eu_vat_number_field' ), false );

		if ( country && jQuery.inArray( country, check_countries ) >= 0 ) {
			jQuery( '#woocommerce_eu_vat_number_field' ).fadeIn();
			if ( 'yes' === b2b_enabled ) {
				field_is_required( jQuery( '#woocommerce_eu_vat_number_field' ), true );

			}
		} else {
			jQuery( '#woocommerce_eu_vat_number_field' ).fadeOut();
		}
	});
	jQuery( 'select#billing_country' ).change();
});
