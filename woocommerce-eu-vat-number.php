<?php
/**
 * Plugin Name: WooCommerce EU VAT Number
 * Plugin URI: https://woocommerce.com/products/eu-vat-number/
 * Description: The EU VAT Number extension lets you collect and validate EU VAT numbers during checkout to identify B2B transactions verses B2C. IP Addresses can also be validated to ensure they match the billing address. EU businesses with a valid VAT number can have their VAT removed prior to payment.
 * Version: 2.3.7
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Text Domain: woocommerce-eu-vat-number
 * Domain Path: /languages
 * Requires at least: 4.4
 * Tested up to: 4.7
 * WC requires at least: 2.6
 * WC tested up to: 3.4
 *
 * Copyright: © 2009-2017 WooCommerce.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package woocommerce-eu-vat-number
 * Woo: 18592:d2720c4b4bb8d6908e530355b7a2d734
 */

if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

define( 'WC_EU_VAT_VERSION', '2.3.7' );
define( 'WC_EU_VAT_FILE', __FILE__ );
define( 'WC_EU_VAT_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

/**
 * WC_EU_VAT_Number_Init class.
 */
class WC_EU_VAT_Number_Init {

	/**
	 * Min version of WooCommerce supported.
	 *
	 * @var string
	 */
	const WC_MIN_VERSION = '2.3';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'localization' ), 0 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		register_activation_hook( __FILE__, array( $this, 'install' ) );
	}

	/**
	 * Checks that WooCommerce is loaded before doing anything else.
	 *
	 * @return bool True if supported
	 */
	private function check_dependencies() {
		$dependencies = array(
			'wc_installed' => array(
				'callback'        => array( $this, 'is_woocommerce_active' ),
				'notice_callback' => array( $this, 'woocommerce_inactive_notice' ),
			),
			'wc_minimum_version' => array(
				'callback'        => array( $this, 'is_woocommerce_version_supported' ),
				'notice_callback' => array( $this, 'woocommerce_wrong_version_notice' ),
			),
			'soap_required' => array(
				'callback'        => array( $this, 'is_soap_supported' ),
				'notice_callback' => array( $this, 'requires_soap_notice' ),
			),
		);
		foreach ( $dependencies as $check ) {
			if ( ! call_user_func( $check['callback'] ) ) {
				add_action( 'admin_notices', $check['notice_callback'] );
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if the WooCommerce plugin is active.
	 * Note: Must be run after the "plugins_loaded" action fires.
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return class_exists( 'woocommerce' );
	}

	/**
	 * Checks if the current WooCommerce version is supported.
	 * Note: Must be run after the "plugins_loaded" action fires.
	 *
	 * @since 1.0
	 * @return bool
	 */
	public function is_woocommerce_version_supported() {
		return version_compare(
			get_option( 'woocommerce_db_version' ),
			WC_EU_VAT_Number_Init::WC_MIN_VERSION,
			'>='
		);
	}

	/**
	 * Checks if the server supports SOAP.
	 *
	 * @since 2.3.7
	 * @return bool
	 */
	public function is_soap_supported() {
		return class_exists( 'SoapClient' );
	}

	/**
	 * WC inactive notice.
	 *
	 * @since 1.0.0
	 */
	public function woocommerce_inactive_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="error"><p><strong>' . wp_kses_post( __( 'WooCommerce EU VAT Number is inactive.', 'woocommerce-eu-vat-number' ) . '</strong> ' . sprintf( __( 'The WooCommerce plugin must be active for EU VAT Number to work. %1$sPlease install and activate WooCommerce%2$s.', 'woocommerce-eu-vat-number' ), '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">', '</a>' ) ) . '</p></div>';
		}
	}

	/**
	 * Wrong version notice.
	 *
	 * @since 1.0.0
	 */
	public function woocommerce_wrong_version_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="error"><p><strong>' . wp_kses_post( __( 'WooCommerce EU VAT Number is inactive.', 'woocommerce-eu-vat-number' ) . '</strong> ' . sprintf( __( 'The WooCommerce plugin must be at least version %s for EU VAT Number to work. %2$sPlease upgrade WooCommerce%3$s.', 'woocommerce-eu-vat-number' ), WC_EU_VAT_Number_Init::WC_MIN_VERSION, '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">', '</a>' ) ) . '</p></div>';
		}
	}

	/**
	 * No SOAP support notice.
	 *
	 * @since 2.3.7
	 */
	public function requires_soap_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="error"><p><strong>' . __( 'WooCommerce EU VAT Number is inactive.', 'woocommerce-eu-vat-number' ) . '</strong> ' . __( 'Your server does not provide SOAP support which is required functionality for communicating with VIES. You will need to reach out to your web hosting provider to get information on how to enable this functionality on your server.', 'woocommerce-eu-vat-number' ) . '</p></div>';
		}
	}

	/**
	 * Init the plugin once WP is loaded.
	 */
	public function init() {
		woothemes_queue_update( plugin_basename( __FILE__ ), 'd2720c4b4bb8d6908e530355b7a2d734', '18592' );

		if ( $this->check_dependencies() ) {
			if ( version_compare( get_option( 'woocommerce_eu_vat_version', 0 ), WC_EU_VAT_VERSION, '<' ) ) {
				add_action( 'init', array( $this, 'install' ) );
			}

			include_once( 'includes/class-wc-eu-vat-privacy.php' );

			if ( ! class_exists( 'WC_EU_VAT_Number' ) ) {
				include_once( 'includes/class-wc-eu-vat-number.php' );
				include_once( 'includes/class-wc-eu-vat-my-account.php' );
			}

			if ( is_admin() ) {
				include_once( 'includes/class-wc-eu-vat-admin.php' );
				include_once( 'includes/class-wc-eu-vat-reports.php' );
			}
		}
	}

	/**
	 * Load translations.
	 */
	public function localization() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-eu-vat-number' );
		$dir    = trailingslashit( WP_LANG_DIR );
		load_textdomain( 'woocommerce-eu-vat-number', $dir . 'woocommerce-eu-vat-number/woocommerce-eu-vat-number-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-eu-vat-number', false, dirname( plugin_basename( WC_EU_VAT_FILE ) ) . '/languages' );
	}

	/**
	 * Installer
	 */
	public function install() {
		update_option( 'woocommerce_eu_vat_version', WC_EU_VAT_VERSION );
		add_rewrite_endpoint( 'vat-number', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Add custom action links on the plugin screen.
	 *
	 * @param	mixed $actions Plugin Actions Links.
	 * @return	array
	 */
	public function plugin_action_links( $actions ) {
		$custom_actions = array(
			'settings' => sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-settings&tab=tax' ), __( 'Settings', 'woocommerce-eu-vat-number' ) ),
		);
		return array_merge( $custom_actions, $actions );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param	mixed $links Plugin Row Meta.
	 * @param	mixed $file  Plugin Base file.
	 * @return	array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( 'woocommerce-eu-vat-number/woocommerce-eu-vat-number.php' === $file ) {
			$row_meta = array(
				'docs'      => '<a href="' . esc_url( apply_filters( 'wc_eu_vat_number_docs_url', 'http://docs.woothemes.com/document/eu-vat-number-2/' ) ) . '" title="' . esc_attr( __( 'View Plugin Documentation', 'woocommerce-eu-vat-number' ) ) . '">' . __( 'Docs', 'woocommerce-eu-vat-number' ) . '</a>',
				'changelog' => '<a href="' . esc_url( apply_filters( 'wc_eu_vat_number_changelog', 'http://www.woothemes.com/changelogs/extensions/woocommerce-eu-vat-number/changelog.txt' ) ) . '" title="' . esc_attr( __( 'View Plugin Changelog', 'woocommerce-eu-vat-number' ) ) . '">' . __( 'Changelog', 'woocommerce-eu-vat-number' ) . '</a>',
				'support'   => '<a href="' . esc_url( apply_filters( 'wc_eu_vat_number_support_url', 'http://support.woothemes.com/' ) ) . '" title="' . esc_attr( __( 'Support', 'woocommerce-eu-vat-number' ) ) . '">' . __( 'Support', 'woocommerce-eu-vat-number' ) . '</a>',
			);
			return array_merge( $links, $row_meta );
		}
		return (array) $links;
	}
}

new WC_EU_VAT_Number_Init;
