<p class="form-row location_confirmation terms">
	<label for="location_confirmation" class="checkbox"><input type="checkbox" class="input-checkbox" name="location_confirmation" <?php checked( $location_confirmation_is_checked, true ); ?> id="location_confirmation" /> <span><?php
		$billing_country = is_callable( array( WC()->customer, 'get_billing_country' ) ) ? WC()->customer->get_billing_country() : WC()->customer->get_country();
		printf( __( 'I am established, have my permanent address, or usually reside within <strong>%s</strong>.', 'woocommerce-eu-vat-number' ), $countries[ $billing_country ] );
	?></span></label>
</p>
