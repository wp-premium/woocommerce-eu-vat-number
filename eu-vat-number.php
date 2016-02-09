<?php
/*
Plugin Name: WooCommerce EU VAT Number
Plugin URI: http://www.woothemes.com/products/eu-vat-number/
Description: The EU VAT Number extension lets you collect and validate EU VAT numbers during checkout to identify B2B transactions verses B2C. IP Addresses can also be validated to ensure they match the billing address. EU businesses with a valid VAT number can have their VAT removed prior to payment.
Version: 2.1.9
Author: WooThemes
Author URI: http://woothemes.com/
Requires at least: 4.0
Tested up to: 4.1

	Copyright: © 2009-2014 WooThemes.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/**
 * Requires WooCommerce
 */
if ( ! is_woocommerce_active() ) {
	return;
}

define( 'WC_EU_VAT_VERSION', '2.1.9' );
define( 'WC_EU_VAT_FILE', __FILE__ );
define( 'WC_EU_VAT_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

/**
 * Init the extension
 */
function __wc_eu_vat_number_init() {
	woothemes_queue_update( plugin_basename( __FILE__ ), 'd2720c4b4bb8d6908e530355b7a2d734', '18592' );

	if ( ! class_exists( 'WC_EU_VAT_Number' ) ) {
		include_once( 'includes/class-wc-eu-vat-number.php' );
		include_once( 'includes/class-wc-eu-vat-my-account.php' );
	}

	if ( ! class_exists( 'WC_EU_VAT_Admin' ) && is_admin() ) {
		include_once( 'includes/class-wc-eu-vat-admin.php' );
		include_once( 'includes/class-wc-eu-vat-reports.php' );
	}

	if ( version_compare( get_option( 'woocommerce_eu_vat_version', 0 ), WC_EU_VAT_VERSION, '<' ) ) {
		__wc_eu_vat_number_install();
	}

	if ( ! class_exists( 'WC_Geolocation' ) ) {
		include_once( 'includes/class-wc-geolocation.php' );
	}
}
add_action( 'plugins_loaded', '__wc_eu_vat_number_init' );

/**
 * Installer
 */
function __wc_eu_vat_number_install() {
	if ( version_compare( WC_VERSION, '2.3', '<' ) ) {
		wp_clear_scheduled_hook( 'woocommerce_geoip_updater' );
		wp_schedule_single_event( time(), 'woocommerce_geoip_updater' );
		wp_schedule_event( strtotime( 'first tuesday of next month' ), 'monthly', 'woocommerce_geoip_updater' );
	}
	update_option( 'woocommerce_eu_vat_version', WC_EU_VAT_VERSION );
}
register_activation_hook( __FILE__, '__wc_eu_vat_number_install' );

/**
 * Load translation
 */
function __wc_eu_vat_number_localization() {
	load_plugin_textdomain( 'woocommerce-eu-vat-number', false, dirname( plugin_basename( WC_EU_VAT_FILE ) ) . '/languages' );
}
add_action( 'plugins_loaded', '__wc_eu_vat_number_localization', 0 );

/**
 * Add custom action links on the plugin screen.
 *
 * @param	mixed $actions Plugin Actions Links
 * @return	array
 */
function __wc_eu_vat_number_plugin_action_links( $actions ) {

	$custom_actions = array();

	// settings
	$custom_actions['settings'] = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-settings&tab=tax' ), __( 'Settings', 'woocommerce-eu-vat-number' ) );

	// add the links to the front of the actions list
	return array_merge( $custom_actions, $actions );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), '__wc_eu_vat_number_plugin_action_links' );

/**
 * Show row meta on the plugin screen.
 *
 * @param	mixed $links Plugin Row Meta
 * @param	mixed $file  Plugin Base file
 * @return	array
 */
function __wc_eu_vat_number_plugin_row_meta( $links, $file ) {
	if ( $file == 'woocommerce-eu-vat-number/eu-vat-number.php' ) {
		$row_meta = array(
			'docs'    => '<a href="' . esc_url( apply_filters( 'wc_eu_vat_number_docs_url', 'http://docs.woothemes.com/document/eu-vat-number-2/' ) ) . '" title="' . esc_attr( __( 'View Plugin Documentation', 'woocommerce-eu-vat-number' ) ) . '">' . __( 'Docs', 'woocommerce-eu-vat-number' ) . '</a>',
			'changelog' => '<a href="' . esc_url( apply_filters( 'wc_eu_vat_number_changelog', 'http://www.woothemes.com/changelogs/extensions/woocommerce-eu-vat-number/changelog.txt' ) ) . '" title="' . esc_attr( __( 'View Plugin Changelog', 'woocommerce-eu-vat-number' ) ) . '">' . __( 'Changelog', 'woocommerce-eu-vat-number' ) . '</a>',
			'support' => '<a href="' . esc_url( apply_filters( 'wc_eu_vat_number_support_url', 'http://support.woothemes.com/' ) ) . '" title="' . esc_attr( __( 'Support', 'woocommerce-eu-vat-number' ) ) . '">' . __( 'Support', 'woocommerce-eu-vat-number' ) . '</a>',
		);
		return array_merge( $links, $row_meta );
	}
	return (array) $links;
}
add_filter( 'plugin_row_meta', '__wc_eu_vat_number_plugin_row_meta', 10, 2 );
