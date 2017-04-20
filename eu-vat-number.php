<?php
/**
 * Backwards compatibility for old main file.
 *
 * @package woocommerce-eu-vat-number
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_plugins = get_option( 'active_plugins', array() );

foreach ( $active_plugins as $key => $active_plugin ) {
	if ( strstr( $active_plugin, '/eu-vat-number.php' ) ) {
		$active_plugins[ $key ] = str_replace( '/eu-vat-number.php', '/woocommerce-eu-vat-number.php', $active_plugin );
	}
}
update_option( 'active_plugins', $active_plugins );
