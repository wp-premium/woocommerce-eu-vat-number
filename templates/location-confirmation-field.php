<p class="form-row location_confirmation terms">
	<label for="location_confirmation" class="checkbox"><?php printf( __( 'I am established, have my permanent address, or usually reside within <strong>%s</strong>.', 'woocommerce-eu-vat-number' ), $countries[ WC()->customer->get_country() ] ); ?></label>
	<input type="checkbox" class="input-checkbox" name="location_confirmation" <?php checked( $location_confirmation_is_checked, true ); ?> id="location_confirmation" />
</p>
