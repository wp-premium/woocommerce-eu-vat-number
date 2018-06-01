<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( dirname( __FILE__ ) . '/vies/class-vies-client.php' );

/**
 * WC_EU_VAT_Number class.
 */
class WC_EU_VAT_Number {

	/**
	 * Stores an array of EU country codes.
	 *
	 * @var array
	 */
	private static $eu_countries = array();

	/**
	 * Stores an array of RegEx patterns for country codes.
	 *
	 * @var array
	 */
	private static $country_codes_patterns = array(
        'AT' => 'U[A-Z\d]{8}',
        'BE' => '0\d{9}',
        'BG' => '\d{9,10}',
        'CY' => '\d{8}[A-Z]',
        'CZ' => '\d{8,10}',
        'DE' => '\d{9}',
        'DK' => '(\d{2} ?){3}\d{2}',
        'EE' => '\d{9}',
        'EL' => '\d{9}',
        'ES' => '[A-Z]\d{7}[A-Z]|\d{8}[A-Z]|[A-Z]\d{8}',
        'FI' => '\d{8}',
        'FR' => '([A-Z]{2}|[A-Z0-9]{2})\d{9}',
        'GB' => '\d{9}|\d{12}|(GD|HA)\d{3}',
        'HR' => '\d{11}',
        'HU' => '\d{8}',
        'IE' => '[A-Z\d]{8,10}',
        'IT' => '\d{11}',
        'LT' => '(\d{9}|\d{12})',
        'LU' => '\d{8}',
        'LV' => '\d{11}',
        'MT' => '\d{8}',
        'NL' => '\d{9}B\d{2}',
        'PL' => '\d{10}',
        'PT' => '\d{9}',
        'RO' => '\d{2,10}',
        'SE' => '\d{12}',
        'SI' => '\d{8}',
        'SK' => '\d{10}'
    );

	/**
	 * VAT Number data.
	 *
	 * @var array
	 */
	private static $data = array(
		'vat_number'  => false,
		'validation'  => false,
	);

	/**
	 * Stores the current IP Address' country code after geolocation.
	 *
	 * @var boolean
	 */
	private static $ip_country = false;

	/**
	 * Init.
	 */
	public static function init() {
		// Add fields to checkout process.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'load_scripts' ) );
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'vat_number_field' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'process_checkout' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'ajax_update_checkout_totals' ) );
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'location_confirmation' ) );

		// Meta data in 2.7.x and 2.6.x.
		if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
			add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'update_order_meta' ) );
			add_action( 'woocommerce_checkout_update_user_meta', array( __CLASS__, 'update_user_meta' ) );
			add_action( 'woocommerce_refund_created', array( __CLASS__, 'refund_vat_number' ) );
		} else {
			add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'set_order_data' ) );
			add_action( 'woocommerce_checkout_update_customer', array( __CLASS__, 'set_customer_data' ) );
			add_action( 'woocommerce_create_refund', array( __CLASS__, 'set_refund_data' ) );
		}

		// Add VAT to addresses.
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'formatted_billing_address' ), 10, 2 );
		add_filter( 'woocommerce_formatted_address_replacements', array( __CLASS__, 'output_company_vat_number' ), 10, 2 );
		add_filter( 'woocommerce_localisation_address_formats', array( __CLASS__, 'localisation_address_formats' ), 10, 2 );

		// Add VAT to emails.
		add_filter( 'woocommerce_email_order_meta_keys', array( __CLASS__, 'order_meta_keys' ) );

		// Digital goods taxable location.
		add_filter( 'woocommerce_get_tax_location', array( __CLASS__, 'woocommerce_get_tax_location' ), 10, 2 );

		// Add VAT Number in order endpoint (REST API).
		add_filter( 'woocommerce_api_order_response', array( __CLASS__, 'add_vat_number_to_order_response' ) );
		add_filter( 'woocommerce_rest_prepare_shop_order', array( __CLASS__, 'add_vat_number_to_order_response' ) );
	}

	/**
	 * Load scripts used on the checkout.
	 */
	public static function load_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_script( 'wc-eu-vat', WC_EU_VAT_PLUGIN_URL . '/assets/js/eu-vat.js', array( 'jquery', 'wc-checkout' ) );
			wp_localize_script( 'wc-eu-vat', 'wc_eu_vat_params', array( 'eu_countries' => self::get_eu_countries() ) );
		}
	}

	/**
	 * Get EU Country codes.
	 *
	 * @return array
	 */
	public static function get_eu_countries() {
		if ( empty( self::$eu_countries ) ) {
			self::$eu_countries = include( 'data/eu-country-codes.php' );
		}
		return self::$eu_countries;
	}

	/**
	 * Reset number.
	 */
	public static function reset() {
		WC()->customer->set_is_vat_exempt( false );
		self::$data = array(
			'vat_number' => false,
			'validation' => false,
		);
	}

	/**
	 * Show the VAT field on the checkout.
	 *
	 * @since 1.0.0
	 * @version 2.3.1
	 */
	public static function vat_number_field() {
		// If order total is zero (free), don't need to proceed.
		if ( ! WC()->cart->needs_payment() ) {
			return;
		}

		wc_get_template( 'vat-number-field.php', array(
			'label'       => get_option( 'woocommerce_eu_vat_number_field_label' ),
			'description' => get_option( 'woocommerce_eu_vat_number_field_description' ),
		), 'woocommerce-eu-vat-number', untrailingslashit( plugin_dir_path( WC_EU_VAT_FILE ) ) . '/templates/' );
	}

	/**
	 * Return the vat number prefix.
	 *
	 * @param  string $country
	 * @return string
	 */
	public static function get_vat_number_prefix( $country ) {
		switch ( $country ) {
			case 'GR' :
				$vat_prefix = 'EL';
			break;
			case 'IM' :
				$vat_prefix = 'GB';
			break;
			case 'MC' :
				$vat_prefix = 'FR';
			break;
			default :
				$vat_prefix = $country;
			break;
		}
		return $vat_prefix;
	}

	/**
	 * Remove unwanted chars and the prefix from a VAT number.
	 *
	 * @param  string $vat
	 * @return string
	 */
	public static function get_formatted_vat_number( $vat ) {
		$vat = strtoupper( str_replace( array( ' ', '-', '_', '.' ), '', $vat ) );

		if ( in_array( substr( $vat, 0, 2 ), array_merge( self::get_eu_countries(), array( 'EL' ) ) ) ) {
			$vat = substr( $vat, 2 );
		}

		return $vat;
	}

	/**
	 * Get IP address country for user.
	 *
	 * @return string
	 */
	public static function get_ip_country() {
		if ( false === self::$ip_country ) {
			$geoip            = WC_Geolocation::geolocate_ip();
			self::$ip_country = $geoip['country'];
		}
		return self::$ip_country;
	}

	/**
	 * Validate a number.
	 *
	 * @return bool if valid/not valid, WP_ERROR if validation failed
	 */
	public static function vat_number_is_valid( $vat_number, $country ) {
		$vat_prefix     = self::get_vat_number_prefix( $country );
		$transient_name = 'vat_number_' . $vat_prefix . $vat_number;
		$cached_result  = get_transient( $transient_name );

		if ( ! empty( $cached_result ) ) {
			return 'yes' === $cached_result;
		}

		$vies = new VIES_Client();

		if ( $vies->get_soap_client() ) {

			$vat_number = str_replace( array( ' ', '.', '-', ',', ', ' ), '', trim( $vat_number ) );

			if ( ! isset( self::$country_codes_patterns[ $vat_prefix ] ) ) {
				return new WP_Error( 'api', __( 'Invalid country code', 'woocommerce-eu-vat-number' ) );
			}

			try {
				$vies_req = $vies->check_vat( $vat_prefix, $vat_number );
				$is_valid = $vies_req->is_valid();

				set_transient( $transient_name, $is_valid ? 'yes' : 'no', 7 * DAY_IN_SECONDS );
				return $is_valid;
			} catch( SoapFault $e ) {
				return new WP_Error( 'api', __( 'Error communicating with the VAT validation server - please try again', 'woocommerce-eu-vat-number' ) );
			}

		}

		return false;
	}

	/**
	 * Validate a number and store the result
	 * @param  string $vat_number
	 * @param  string $billing_country
	 */
	public static function validate( $vat_number, $billing_country ) {
		$vat_number = self::get_formatted_vat_number( $vat_number );
		$valid      = self::vat_number_is_valid( $vat_number, $billing_country );

		if ( is_wp_error( $valid ) ) {
			self::$data['vat_number'] = $vat_number;
			self::$data['validation'] = array(
				'valid' => null,
				'error' => $valid->get_error_message(),
			);
		} else {
			self::$data['vat_number'] = $valid ? self::get_vat_number_prefix( $billing_country ) . $vat_number : $vat_number;
			self::$data['validation'] = array(
				'valid' => $valid,
				'error' => false,
			);
		}
	}

	/**
	 * Set tax exception based on countries.
	 *
	 * @param bool   $exempt Are they exempt?
	 * @param string $billing_country Billing country of customer
	 * @param string $shipping_country Shipping country of customer
	 */
	public static function maybe_set_vat_exempt( $exempt, $billing_country, $shipping_country ) {
		if ( 'billing' === get_option( 'woocommerce_tax_based_on', 'billing' ) ) {
			$base_country_match = ( WC()->countries->get_base_country() === $billing_country );
		} else {
			$base_country_match = in_array( WC()->countries->get_base_country(), array( $billing_country, $shipping_country ) );
		}

		if ( ( $base_country_match && 'yes' === get_option( 'woocommerce_eu_vat_number_deduct_in_base', 'yes' ) ) || ! $base_country_match ) {
			$exempt = apply_filters( 'woocommerce_eu_vat_number_set_is_vat_exempt', $exempt, $base_country_match, $billing_country, $shipping_country );
			WC()->customer->set_is_vat_exempt( $exempt );
		}
	}

	/**
	 * Validate the VAT number when the checkout form is processed.
	 *
	 * For B2C transactions, validate the IP only if this is a digital order.
	 */
	public static function process_checkout() {
		self::reset();

		$billing_country  = wc_clean( $_POST['billing_country'] );
		$shipping_country = wc_clean( ! empty( $_POST['shipping_country'] ) && ! empty( $_POST['ship_to_different_address'] ) ? $_POST['shipping_country'] : $_POST['billing_country'] );

		if ( in_array( $billing_country, self::get_eu_countries() ) && 'yes' === get_option( 'woocommerce_eu_vat_number_b2b', 'no' ) && empty( $_POST['vat_number'] ) ) {
			wc_add_notice( __( 'Please enter your VAT Number.', 'woocommerce-eu-vat-number' ), 'error' );
		}

		// B2B.
		if ( in_array( $billing_country, self::get_eu_countries() ) && ! empty( $_POST['vat_number'] ) ) {
			self::validate( wc_clean( $_POST['vat_number'] ), $billing_country );

			if ( true === (bool) self::$data['validation']['valid'] ) {
				self::maybe_set_vat_exempt( true, $billing_country, $shipping_country );
			} else {
				$fail_handler = get_option( 'woocommerce_eu_vat_number_failure_handling', 'reject' );
				switch ( $fail_handler ) {
					case 'accept_with_vat' :
						// Do no exemption.
					break;
					case 'accept' :
						self::maybe_set_vat_exempt( true, $billing_country, $shipping_country );
					break;
					default :
						if ( false === self::$data['validation']['valid'] ) {
							wc_add_notice( sprintf( __( 'You have entered an invalid VAT number (%1$s) for your billing country (%2$s).', 'woocommerce-eu-vat-number' ), self::$data['vat_number'], $billing_country ), 'error' );
						} else {
							wc_add_notice( self::$data['validation']['error'], 'error' );
						}
					break;
				}
			}

		// B2C.
		} elseif ( 'yes' === get_option( 'woocommerce_eu_vat_number_validate_ip', 'no' ) && self::cart_has_digital_goods() ) {
			if ( self::is_self_declaration_required( self::get_ip_country(), $billing_country ) && empty( $_POST['location_confirmation'] ) ) {
				wc_add_notice( sprintf( __( 'Your IP Address (%1$s) does not match your billing country (%2$s). European VAT laws require your IP address to match your billing country when purchasing digital goods in the EU. Please confirm you are located within your billing country using the checkbox below.', 'woocommerce-eu-vat-number' ), apply_filters( 'wc_eu_vat_self_declared_ip_address', WC_Geolocation::get_ip_address() ), $billing_country ), 'error' );
			}
		}
	}

	/**
	 * See if we need the user to self-declare location.
	 *
	 * This is needed when:
	 * 		The IP country cannot be detected
	 * 		The IP country is inside the EU OR
	 * 		The Billing country is inside the EU AND
	 * 		The IP doesn't match the billing country.
	 *
	 * @param  string  $ip_country
	 * @param  string  $billing_country
	 * @return boolean                  [description]
	 */
	public static function is_self_declaration_required( $ip_country = null, $billing_country = null ) {
		if ( is_null( $ip_country ) ) {
			$ip_country = self::get_ip_country();
		}
		if ( is_null( $billing_country ) ) {
			$billing_country = is_callable( array( WC()->customer, 'get_billing_country' ) ) ? WC()->customer->get_billing_country() : WC()->customer->get_country();
		}
		return ( empty( $ip_country ) || in_array( $ip_country, self::get_eu_countries() ) || in_array( $billing_country, self::get_eu_countries() ) ) && $ip_country !== $billing_country;
	}

	/**
	 * Show checkbox for customer to confirm their location (location evidence for B2C)
	 */
	public static function location_confirmation() {
		if ( 'yes' === get_option( 'woocommerce_eu_vat_number_validate_ip', 'no' ) && self::cart_has_digital_goods() ) {
			if ( false === self::$data['vat_number'] && self::is_self_declaration_required() ) {
				wc_get_template( 'location-confirmation-field.php', array(
					'location_confirmation_is_checked' => isset( $_POST['location_confirmation'] ),
					'countries'                        => WC()->countries->get_countries(),
				), 'woocommerce-eu-vat-number', untrailingslashit( plugin_dir_path( WC_EU_VAT_FILE ) ) . '/templates/' );
			}
		}
	}

	/**
	 * Add VAT number to order emails.
	 *
	 * @param array $keys
	 * @return array
	 */
	public static function order_meta_keys( $keys ) {
		$keys[ __( 'VAT Number', 'woocommerce-eu-vat-number' ) ] = '_vat_number';
		return $keys;
	}

	/**
	 * Triggered when the totals are updated on the checkout.
	 *
	 * @since 1.0.0
	 * @version 2.3.1
	 * @param array $form_data
	 */
	public static function ajax_update_checkout_totals( $form_data ) {
		// If order total is zero (free), don't need to proceed.
		if ( ! WC()->cart->needs_payment() ) {
			return;
		}

		parse_str( $form_data, $form_data );

		self::reset();

		if ( empty( $form_data['billing_country'] ) && empty( $form_data['shipping_country'] ) ) {
			return;
		}

		if ( in_array( $form_data['billing_country'], self::get_eu_countries() ) && ! empty( $form_data['vat_number'] ) ) {
			$shipping_country = ! empty( $form_data['shipping_country'] ) ? $form_data['shipping_country'] : '';

			self::validate( wc_clean( $form_data['vat_number'] ), $form_data['billing_country'] );

			if ( true === (bool) self::$data['validation']['valid'] ) {
				self::maybe_set_vat_exempt( true, $form_data['billing_country'], $shipping_country );
			} else {
				$fail_handler = get_option( 'woocommerce_eu_vat_number_failure_handling', 'reject' );
				switch ( $fail_handler ) {
					case 'accept' :
						self::maybe_set_vat_exempt( true, $form_data['billing_country'], $shipping_country );
					break;
				}
			}
		}
	}

	/**
	 * Sees if a cart contains anything non-shippable. Thanks EU, I hate you.
	 * @return bool
	 */
	public static function cart_has_digital_goods() {
		$has_digital_goods = false;

		if ( WC()->cart->get_cart() ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$_product = $values['data'];
				if ( ! $_product->needs_shipping() ) {
					$has_digital_goods = true;
				}
			}
		}

		return apply_filters( 'woocommerce_cart_has_digital_goods', $has_digital_goods );
	}

	/**
	 * Add VAT ID to the formatted address array
	 * @param  array $address
	 * @param  WC_Order $order
	 * @return array
	 */
	public static function formatted_billing_address( $address, $order ) {
		if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
			$vat_id = get_post_meta( $order->id, '_vat_number', true );
		} else {
			$vat_id = $order->get_meta( '_vat_number', true );
		}
		if ( $vat_id ) {
			$address['vat_id'] = $vat_id;
		}
		return $address;
	}

	/**
	 * Add {vat_id} placeholder
	 * @param  array $formats
	 * @param  array $args
	 * @return array
	 */
	public static function output_company_vat_number( $formats, $args ) {
		if ( isset( $args['vat_id'] ) ) {
			$formats['{vat_id}'] = sprintf( __( 'VAT ID: %s', 'woocommerce-eu-vat-number' ), $args['vat_id'] );
		} else {
			$formats['{vat_id}'] = '';
		}
		return $formats;
	}

	/**
	 * Address formats.
	 *
	 * @param  array $formats
	 * @return array
	 */
	public static function localisation_address_formats( $formats ) {
		foreach ( $formats as $key => $format ) {
			if ( 'default' === $key || in_array( $key, self::get_eu_countries() ) ) {
				$formats[ $key ] .= "\n{vat_id}";
			}
		}
		return $formats;
	}

	/**
	 * Force Digital Goods tax class to use billing address
	 * @param  array $location
	 * @param  string $tax_class
	 * @return array
	 */
	public static function woocommerce_get_tax_location( $location, $tax_class = '' ) {
		if ( ! empty( WC()->customer ) && in_array( sanitize_title( $tax_class ), get_option( 'woocommerce_eu_vat_number_digital_tax_classes', array() ) ) ) {
			if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
				return array(
					WC()->customer->get_country(),
					WC()->customer->get_state(),
					WC()->customer->get_postcode(),
					WC()->customer->get_city(),
				);
			} else {
				return array(
					WC()->customer->get_billing_country(),
					WC()->customer->get_billing_state(),
					WC()->customer->get_billing_postcode(),
					WC()->customer->get_billing_city(),
				);
			}
		}
		return $location;
	}

	/**
	 * Add VAT Number to order endpoint response.
	 *
	 * @since 2.1.12
	 *
	 * @param WP_REST_Response $response The response object
	 *
	 * @return WP_REST_Response The response object with VAT number
	 */
	public static function add_vat_number_to_order_response( $response ) {
		if ( is_a( $response, 'WP_REST_Response' ) ) {
			$response->data['vat_number'] = get_post_meta( $response->data['id'], '_vat_number', true );
		} elseif ( is_array( $response ) && ! empty( $response['id'] ) ) {
			// Legacy endpoint.
			$response['vat_number'] = get_post_meta( $response['id'], '_vat_number', true );
		}
		return $response;
	}

	/**
	 * Save VAT Number to the order during checkout (WC 2.7.x).
	 *
	 * @param  WC_Order $order
	 */
	public static function set_order_data( $order ) {
		$order->update_meta_data( '_vat_number', self::$data['vat_number'] );
		$order->update_meta_data( '_vat_number_is_validated', ! is_null( self::$data['validation']['valid'] ) ? 'true' : 'false' );
		$order->update_meta_data( '_vat_number_is_valid', true === self::$data['validation']['valid'] ? 'true' : 'false' );

		if ( false !== self::get_ip_country() ) {
			$order->update_meta_data( '_customer_ip_country', self::get_ip_country() );
			$order->update_meta_data( '_customer_self_declared_country', ! empty( $_POST['location_confirmation'] ) ? 'true' : 'false' );
		}
	}

	/**
	 * Save VAT Number to the customer during checkout (WC 2.7.x).
	 *
	 * @param  WC_Customer $customer
	 */
	public static function set_customer_data( $customer ) {
		$customer->update_meta_data( 'vat_number', self::$data['vat_number'] );
	}

	/**
	 * Save VAT Number to the customer during checkout (WC 2.7.x).
	 *
	 * @param  WC_Order $refund
	 */
	public static function set_refund_data( $refund ) {
		$order = wc_get_order( $refund->get_parent_id() );
		$refund->update_meta_data( '_vat_number', $order->get_meta( '_vat_number', true ) );
	}

	/**
	 * 2.6.x methods.
	 */

	/**
	 * Save VAT Number to the order during checkout (WC 2.6.x).
	 *
	 * @param int $order_id
	 */
	public static function update_order_meta( $order_id ) {
		update_post_meta( $order_id, '_vat_number', self::$data['vat_number'] );
		update_post_meta( $order_id, '_vat_number_is_validated', ! is_null( self::$data['validation']['valid'] ) ? 'true' : 'false' );
		update_post_meta( $order_id, '_vat_number_is_valid', true === self::$data['validation']['valid'] ? 'true' : 'false' );

		if ( false !== self::get_ip_country() ) {
			update_post_meta( $order_id, '_customer_ip_country', self::get_ip_country() );
			update_post_meta( $order_id, '_customer_self_declared_country', ! empty( $_POST['location_confirmation'] ) ? 'true' : 'false' );
		}
	}

	/**
	 * Stores VAT Number to customer profile
	 *
	 * @param int $user_id
	 */
	public static function update_user_meta( $user_id ) {
		if ( $user_id && self::$data['vat_number'] ) {
			update_user_meta( $user_id, 'vat_number', self::$data['vat_number'] );
		}
	}

	/**
	 * Pass vat number between orders
	 */
	public static function refund_vat_number( $refund_id ) {
		update_post_meta( $refund_id, '_vat_number', get_post_meta( wp_get_post_parent_id( $refund_id ), '_vat_number', true ) );
		update_post_meta( $refund_id, '_billing_country', get_post_meta( wp_get_post_parent_id( $refund_id ), '_billing_country', true ) );
		update_post_meta( $refund_id, '_order_currency', get_post_meta( wp_get_post_parent_id( $refund_id ), '_order_currency', true ) );
	}
}

WC_EU_VAT_Number::init();
