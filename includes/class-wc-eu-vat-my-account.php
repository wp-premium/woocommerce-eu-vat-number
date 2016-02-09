<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Reports class
 */
class WC_EU_VAT_My_Account {

	/**
	 * Constructor
	 */
	public function __construct() {

		add_action( 'woocommerce_after_my_account', array( $this, 'show_my_account' ) );
		
		// Save a VAT number from My Account form if one is submitted
		if ( isset( $_REQUEST[ 'action' ] ) && $_REQUEST[ 'action' ] == 'edit_vat_number' ) {
			$this->save_vat_number();
		}
	}

	/*
	Function to show VAT number form on My Account page
	*/
	public function show_my_account() {
		wc_get_template(
			'my-account/my-vat-number.php',
			array( 'vat_number' => get_user_meta( get_current_user_id(), 'vat_number', true ) ),
			'woocommerce-eu-vat-number',
			untrailingslashit( plugin_dir_path( WC_EU_VAT_FILE ) ) . '/templates/'
		);
	}

	/*
		Function to save VAT number from the my account form
	*/	
	public function save_vat_number() {
		if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'woocommerce-edit_vat_number' ) ) {
			update_user_meta( get_current_user_id(), 'vat_number', $_REQUEST[ 'vat_number' ] );
		}
	}	
}

$wc_eu_vat_my_account = new WC_EU_VAT_My_Account();
