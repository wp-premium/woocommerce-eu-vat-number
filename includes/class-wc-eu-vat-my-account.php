<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Reports class
 */
class WC_EU_VAT_My_Account {

	public $endpoint = 'vat-number';

	/**
	 * Constructor
	 */
	public function __construct() {

		// New endpoint for vat-number WC >= 2.6.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
		
		// Change My Account page title.
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );

		// Inserting new tab/page into My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_action( 'woocommerce_account_' . $this->endpoint . '_endpoint', array( $this, 'endpoint_content' ) );

		// Legacy WC < 2.6.
		add_action( 'woocommerce_after_my_account', array( $this, 'show_my_account' ) );
		// Save a VAT number from My Account form if one is submitted
		if ( isset( $_POST[ 'action' ] ) && 'edit_vat_number' === $_POST[ 'action' ] ) {
			$this->save_vat_number();
		}
	}

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @since 2.1.12
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add new query var.
	 *
	 * @since 2.1.12
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = $this->endpoint;

		return $vars;
	}

	/**
	 * Set endpoint title.
	 *
	 * @since 2.1.12
	 *
	 * @param string $title
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ $this->endpoint ] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			$title = __( 'VAT Number', 'woocommerce-eu-vat-number' );

			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}

		return $title;
	}

	/**
	 * Insert new endpoint into My Account menu.
	 *
	 * @since 2.1.12
	 *
	 * @param array $items Menu items
	 * @return array Menu items
	 */
	public function new_menu_items( $items ) {
		// Remove logout menu item.
		$logout = $items['customer-logout'];
		unset( $items['customer-logout'] );

		// Insert VAT Number.
		$items[ $this->endpoint ] = __( 'VAT Number', 'woocommerce-eu-vat-number' );

		// Insert back logout item.
		$items['customer-logout'] = $logout;

		return $items;
	}

	/**
	 * Endpoint HTML content.
	 *
	 * @since 2.1.12
	 */
	public function endpoint_content() {
		$this->render_my_vat_number_content();
	}

	/**
	 * Function to show VAT number form on My Account page.
	 *
	 * Hooked to woocommerce_after_my_account and only affect WC < 2.6.
	 */
	public function show_my_account() {
		if ( version_compare( WC()->version, '2.6', '<' ) ) {
			$this->render_my_vat_number_content();
		}
	}

	/**
	 * Render My VAT Number content.
	 *
	 * @since 2.1.12
	 */
	public function render_my_vat_number_content() {
		$vars = array(
			'vat_number' => get_user_meta( get_current_user_id(), 'vat_number', true ),
			'show_title' => version_compare( WC()->version, '2.6', '<' ),
		);

		wc_get_template(
			'my-account/my-vat-number.php',
			$vars,
			'woocommerce-eu-vat-number',
			untrailingslashit( plugin_dir_path( WC_EU_VAT_FILE ) ) . '/templates/'
		);
	}

	/*
		Function to save VAT number from the my account form
	*/	
	public function save_vat_number() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-edit_vat_number' ) ) {
			update_user_meta( get_current_user_id(), 'vat_number', $_POST['vat_number'] );
		}
	}	
}

$wc_eu_vat_my_account = new WC_EU_VAT_My_Account();
