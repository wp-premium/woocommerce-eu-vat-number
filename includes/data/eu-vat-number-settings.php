<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( WOOCOMMERCE_VERSION, '2.3', '<' ) ) {
	$tax_classes = array_filter( array_map( 'trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
} else {
	$tax_classes = WC_Tax::get_tax_classes();
}

$classes_options             = array();
$classes_options['standard'] = __( 'Standard', 'woocommerce-eu-vat-number' );
foreach ( $tax_classes as $class ) {
	$classes_options[ sanitize_title( $class ) ] = esc_html( $class );
}

/**
 * Settings for the addon are defined here
 */
return array(
	array(
		'type' => 'sectionend',
	),
	array(
		'type'  => 'title',
		'title' => __( 'EU VAT Number Handling', 'woocommerce-eu-vat-number' ),
		'id'    => 'vat_number'
	),
	array(
		'name'        => __( 'VAT Number Field Label', 'woocommerce-eu-vat-number' ),
		'desc'        => __( 'The label that appears at checkout for the VAT Number field.', 'woocommerce-eu-vat-number' ),
		'id'          => 'woocommerce_eu_vat_number_field_label',
		'type'        => 'text',
		'default'     => __( "VAT Number", 'Default Field Label', 'woocommerce-eu-vat-number' ),
		'placeholder' =>  __( "VAT Number", 'Default Field Label', 'woocommerce-eu-vat-number' ),
		'desc_tip'    => true
	),
	array(
		'name'     => __( 'VAT Number Field Description', 'woocommerce-eu-vat-number' ),
		'desc'     => __( 'The description that appears at checkout below the VAT Number field.', 'woocommerce-eu-vat-number' ),
		'id'       => 'woocommerce_eu_vat_number_field_description',
		'type'     => 'text',
		'desc_tip' => true
	),
	array(
		'name'    => __( 'Remove VAT for Businesses in Your Base Country', 'woocommerce-eu-vat-number' ),
		'desc'    => __( 'Remove the VAT from the order even when the customer is in your base country.', 'woocommerce-eu-vat-number' ),
		'id'      => 'woocommerce_eu_vat_number_deduct_in_base',
		'type'    => 'checkbox',
		'default' => 'yes'
	),
	array(
		'name'     => __( 'Failed Validation Handling', 'woocommerce-eu-vat-number' ),
		'desc'     => __( 'This option determines how orders are handled if the VAT Number does not pass validation.', 'woocommerce-eu-vat-number' ),
		'id'       => 'woocommerce_eu_vat_number_failure_handling',
		'desc_tip' => true,
		'type'     => 'select',
		'options'  => array(
			'reject'          => __( 'Reject the order and show the customer an error message', 'woocommerce-eu-vat-number' ),
			'accept_with_vat' => __( 'Accept the order, but do not remove VAT.', 'woocommerce-eu-vat-number' ),
			'accept'          => __( 'Accept the order and remove VAT.', 'woocommerce-eu-vat-number' )
		)
	),
	array(
		'name'    => __( 'Enable B2B Transactions', 'woocommerce-eu-vat-number' ),
		'desc'    => __( 'This will force users to check out with a VAT Number, useful for sites that transact purely from B2B.', 'woocommerce-eu-vat-number' ),
		'id'      => 'woocommerce_eu_vat_number_b2b',
		'type'    => 'checkbox',
		'default' => 'no'
	),
	array(
		'type' => 'sectionend',
	),
	array(
		'type'  => 'title',
		'title' => __( 'EU VAT Digital Goods Handling', 'woocommerce-eu-vat-number' ),
		'desc'  => sprintf( __( 'EU VAT laws are changing for digital goods from the 1st Jan 2015 (affecting B2C transactions only). The VAT on digital goods must be calculated based on the customer location, and you need to collect evidence of this (IP address and Billing Address). You also need to setup VAT rates to charge the correct amount. %sRead this guide%s for instructions on doing this.', 'woocommerce-eu-vat-number' ), '<a href="http://docs.woothemes.com/document/setting-up-eu-vat-rates-for-digital-products">', '</a>' ),
		'id'    => 'vat_number_digital_goods'
	),
	array(
		'name'     => __( 'Tax Classes for Digital Goods', 'woocommerce-eu-vat-number' ),
		'desc'     => __( 'This option tells the plugin which of your tax classes are for digital goods. This affects the taxable location of the user as of 1st Jan 2015.', 'woocommerce-eu-vat-number' ),
		'id'       => 'woocommerce_eu_vat_number_digital_tax_classes',
		'desc_tip' => true,
		'type'     => 'multiselect',
		'class'    => 'chosen_select wp-enhanced-select',
		'css'      => 'width: 450px;',
		'default'  => '',
		'options'  => $classes_options,
		'custom_attributes' => array(
			'data-placeholder' => __( 'Select some tax classes', 'woocommerce-eu-vat-number' )
		)
	),
	array(
		'name'    => __( 'Collect and Validate Evidence', 'woocommerce-eu-vat-number' ),
		'desc'    => __( 'This validates the customer IP address against their billing address, and prompts the customer to self-declare their address if they do not match. Applies to digital goods and services only.', 'woocommerce-eu-vat-number' ),
		'id'      => 'woocommerce_eu_vat_number_validate_ip',
		'type'    => 'checkbox',
		'default' => 'no'
	)
);