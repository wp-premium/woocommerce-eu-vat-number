<?php
/**
 * Gets the VAT ID from order.
 *
 * @since 2.3.21
 * @param object $order The order in context.
 * @return string $vat;
 */
function wc_eu_vat_get_vat_from_order( $order ) {
	if ( ! $order ) {
		return '';
	}

	$vat = $order->get_meta( '_billing_vat_number', true ) ? $order->get_meta( '_billing_vat_number', true ) : '';

	if ( ! $vat ) {
		$vat = $order->get_meta( '_vat_number', true ) ? $order->get_meta( '_vat_number', true ) : '';
	}

	return $vat;
}
