<?php
/**
 * List of countries to consider under EU.
 *
 * @package woocommerce-eu-vat-number
 * @since 1.0.0
 * @return void
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters(
	'woocommerce_eu_vat_number_country_codes',
	array(
		'AT',
		'BE',
		'BG',
		'CY',
		'CZ',
		'DE',
		'DK',
		'EE',
		'ES',
		'FI',
		'FR',
		'GB',
		'GR',
		'HR',
		'HU',
		'IE',
		'IT',
		'LT',
		'LU',
		'LV',
		'MT',
		'NL',
		'PL',
		'PT',
		'RO',
		'SE',
		'SI',
		'SK',
		'IM',
		'MC',
	)
);
