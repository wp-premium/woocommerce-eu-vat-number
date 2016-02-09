<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Reports class
 */
class WC_EU_VAT_Reports {

	/**
	 * Constructor
	 */
	public static function init() {
		add_action( 'woocommerce_admin_reports', array( __CLASS__, 'init_reports' ) );
	}

	/**
	 * Add reports
	 */
	public static function init_reports( $reports ) {
		if ( isset( $reports['taxes'] ) ) {
			$reports['taxes']['reports']['ec_sales_list'] = array(
				'title'       => __( 'EC Sales List', 'woocommerce-eu-vat-number' ),
				'description' => '',
				'hide_title'  => true,
				'callback'    => array( __CLASS__, 'ec_sales_list' )
			);
			$reports['taxes']['reports']['eu_vat'] = array(
				'title'       => __( 'EU VAT by state', 'woocommerce-eu-vat-number' ),
				'description' => '',
				'hide_title'  => true,
				'callback'    => array( __CLASS__, 'eu_vat' )
			);
			$reports['taxes']['reports']['non_eu_vat'] = array(
				'title'       => __( 'Non EU Sales', 'woocommerce-eu-vat-number' ),
				'description' => '',
				'hide_title'  => true,
				'callback'    => array( __CLASS__, 'non_eu_vat' )
			);
		}
		return $reports;
	}

	/**
	 * Get a report
	 */
	public static function ec_sales_list() {
		include_once( 'class-wc-eu-vat-report-ec-sales-list.php' );
		$report = new WC_EU_VAT_Report_EC_Sales_List();
		$report->output_report();
	}

	/**
	 * Get a report
	 */
	public static function eu_vat() {
		include_once( 'class-wc-eu-vat-report-eu-vat.php' );
		$report = new WC_EU_VAT_Report_EU_VAT();
		$report->output_report();
	}

	/**
	 * Get a report
	 */
	public static function non_eu_vat() {
		include_once( 'class-wc-non-eu-sales-report.php' );
		$report = new WC_Non_EU_Sales_Report();
		$report->output_report();
	}
}

WC_EU_VAT_Reports::init();
