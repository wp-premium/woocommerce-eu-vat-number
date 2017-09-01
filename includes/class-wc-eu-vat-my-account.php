<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Reports class
 */
class WC_EU_VAT_My_Account {

	public $endpoint = 'vat-number';
	public $messages = array();

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

		add_filter( 'woocommerce_product_is_taxable', array( $this, 'maybe_remove_vat_from_cart' ), 10, 2 );
	}

	/**
	 * Checks to see if we need to remove vat from displaying in the cart.
	 * This may not be the best way to do this however I have not found a better way.
	 *
	 * @since 2.3.1
	 * @version 2.3.2
	 *
	 * @see https://github.com/woocommerce/woocommerce-eu-vat-number/issues/71
	 * @see https://github.com/woocommerce/woocommerce-eu-vat-number/issues/74
	 *
	 * @param bool       $is_taxable Is taxable?
	 * @param WC_Product $product    Product object.
	 *
	 * @return bool True if taxable.
	 */
	public function maybe_remove_vat_from_cart( $is_taxable, $product ) {
		/**
		 * Checking `is_checkout` in case cart page is also set to checkout.
		 *
		 * @see https://github.com/woocommerce/woocommerce-eu-vat-number/issues/77
		 */
		if ( ! wc_tax_enabled() || ! is_cart() || is_checkout() || ! is_user_logged_in() ) {
			return $is_taxable;
		}

		$vat_number = get_user_meta( get_current_user_id(), 'vat_number', true );
		if ( empty( $vat_number ) ) {
			return $is_taxable;
		}

		// Validate if VAT is valid. If valid, check for VAT exempt.
		try {
			$billing_country = version_compare( WC_VERSION, '3.0', '<' )
				? WC()->customer->country
				: WC()->customer->get_billing_country();

			$shipping_country = WC()->customer->get_shipping_country();

			if ( self::validate( $vat_number, $billing_country ) ) {
				WC_EU_VAT_Number::maybe_set_vat_exempt( true, $billing_country, $shipping_country );
			}
		} catch( Exception $e ) {}

		return $is_taxable;
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
			'messages'   => $this->messages,
		);

		wc_get_template(
			'my-account/my-vat-number.php',
			$vars,
			'woocommerce-eu-vat-number',
			untrailingslashit( plugin_dir_path( WC_EU_VAT_FILE ) ) . '/templates/'
		);
	}

	/**
	 * Validate a VAT number.
	 * @version 2.3.0
	 * @since 2.3.0
	 * @param  string $vat_number
	 * @param  string $billing_country
	 */
	public function validate( $vat_number, $billing_country ) {
		$vat_number = WC_EU_VAT_Number::get_formatted_vat_number( wc_clean( $vat_number ) );
		$valid      = WC_EU_VAT_Number::vat_number_is_valid( $vat_number, wc_clean( $billing_country ) );

		// Allow empty input to clear VAT field.
		if ( empty( $vat_number ) ) {
			return true;
		}

		if ( is_wp_error( $valid ) ) {
			throw new Exception( $valid->get_error_message() );
		}

		if ( ! $valid ) {
			throw new Exception( sprintf( __( 'You have entered an invalid VAT number (%1$s) for your billing country (%2$s).', 'woocommerce-eu-vat-number' ), $vat_number, $billing_country ) );
		}

		return true;
	}

	/**
	 * Function to save VAT number from the my account form.
	 */
	public function save_vat_number() {
		if ( wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-edit_vat_number' ) ) {
			try {
				$posted_vat      = $_POST['vat_number'];
				$user            = get_userdata( get_current_user_id() );
				$billing_country = $user->billing_country;

				self::validate( $posted_vat, $billing_country );

				update_user_meta( get_current_user_id(), 'vat_number', $posted_vat );
				$this->messages = array( 'message' => __( 'VAT number saved successfully!', 'woocommerce-eu-vat-number' ), 'status' => 'info' );
			} catch ( Exception $e ) {
				$this->messages = array( 'message' => $e->getMessage(), 'status' => 'error' );
			}
		}
	}
}

$wc_eu_vat_my_account = new WC_EU_VAT_My_Account();
