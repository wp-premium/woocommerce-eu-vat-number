<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Number class
 */
class WC_EU_VAT_Number {

	/** Stores the input VAT Number @var string */
	private static $vat_number                  = null;

	/** Stores the IP address country @var string */
	private static $ip_country                  = null;

	/** Stores whether or not the input VAT Number is valid @var bool */
	private static $vat_number_is_valid         = null;

	/** Stores whether or not the input VAT Number was validated @var bool */
	private static $vat_number_validated        = null;

	/** Stores the error if validation was unsuccessful @var string */
	private static $vat_number_validation_error = '';

	/** Stores the URL to the vat number validation API @var string */
	private static $validation_api_url          = 'http://woo-vat-validator.herokuapp.com/v1/validate/';

	/** Stores an array of EU country codes @var array */
	private static $eu_countries                = array();

	/**
	 * Init the extension
	 */
	public static function init() {
		self::$eu_countries     = include( 'data/eu-country-codes.php' );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'load_scripts' ) );
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'vat_number_field' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'process_checkout' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'ajax_update_checkout_totals' ) );
		add_filter( 'woocommerce_email_order_meta_keys', array( __CLASS__, 'order_meta_keys' ) );
		add_action( 'woocommerce_checkout_update_user_meta', array( __CLASS__, 'update_user_meta' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'update_order_meta' ) );
		add_action( 'woocommerce_refund_created', array( __CLASS__, 'refund_vat_number' ) );

		// Self-certification
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'location_confirmation' ) );

		// Add VAT to company
		add_filter( 'woocommerce_order_formatted_billing_address', array( __CLASS__, 'formatted_billing_address' ), 10, 2 );
		add_filter( 'woocommerce_formatted_address_replacements', array( __CLASS__, 'output_company_vat_number' ), 10, 2 );
		add_filter( 'woocommerce_localisation_address_formats', array( __CLASS__, 'localisation_address_formats' ), 10, 2 );

		// Digital goods taxable location
		add_filter( 'woocommerce_get_tax_location', array( __CLASS__, 'woocommerce_get_tax_location' ), 10, 2 );
    }

    /**
     * Load scripts used on the checkout
     */
    public static function load_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_style( 'wc-eu-vat', WC_EU_VAT_PLUGIN_URL . '/assets/css/eu-vat.css' );
			wp_enqueue_script( 'wc-eu-vat', WC_EU_VAT_PLUGIN_URL . '/assets/js/eu-vat.js', array( 'jquery', 'wc-checkout' ) );
			wp_localize_script( 'wc-eu-vat', 'wc_eu_vat_params', array( 'eu_countries' => self::get_eu_countries() ) );
		}
    }

    /**
     * Get EU Country codes
     * @return array
     */
    public static function get_eu_countries() {
    	return self::$eu_countries;
    }

    /**
     * Reset number
     */
    public static function reset() {
		WC()->customer->set_is_vat_exempt( false );
		self::$vat_number                  = '';
		self::$vat_number_is_valid         = null;
		self::$vat_number_validated        = null;
		self::$vat_number_validation_error = '';
		self::$ip_country                  = null;
    }

	/**
	 * Show the VAT field on the checkout
	 */
	public static function vat_number_field() {
		wc_get_template( 'vat-number-field.php', array(
			'label'       => get_option( 'woocommerce_eu_vat_number_field_label' ),
			'description' => get_option( 'woocommerce_eu_vat_number_field_description' ),
		), 'woocommerce-eu-vat-number', untrailingslashit( plugin_dir_path( WC_EU_VAT_FILE ) ) . '/templates/' );
	}

	/**
	 * Return the vat number prefix
	 *
	 * @param  string $country
	 * @return string
	 */
	public static function get_vat_number_prefix( $country ) {
		$vat_prefix = $country;

		// Greece has to be a pain
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
		}

		return $vat_prefix;
	}

	/**
	 * Remove unwanted chars and the prefix from a VAT number.
	 * @param  string $vat
	 * @return string
	 */
	public static function get_formatted_vat_number( $vat ) {
		$vat = strtoupper( str_replace( array( ' ', '-', '_', '.' ), '', $vat ) );

		// Remove country code if set at the begining
		if ( in_array( substr( $vat, 0, 2 ), array_merge( self::get_eu_countries(), array( 'EL' ) ) ) ) {
			$vat = substr( $vat, 2 );
		}

		return $vat;
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

		if ( empty( $cached_result ) ) {

			$response = wp_remote_get( self::$validation_api_url . $vat_prefix . '/' . $vat_number . '/' );

			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'api', sprintf( __( 'VAT API Error: %s', 'woocommerce-eu-vat-number' ), $response->get_error_message() ) );
			} elseif ( empty( $response['body'] ) ) {
				return new WP_Error( 'api', __( 'Error communicating with the VAT validation server - please try again', 'woocommerce-eu-vat-number' ) );
			} elseif ( $response['body'] == "true" ) {
				set_transient( $transient_name, 1, 7 * DAY_IN_SECONDS );
				return true;
			} elseif ( strstr( $response['body'], 'SERVER_BUSY' ) ) {
				return new WP_Error( 'api', __( 'The VAT validation server is busy - please try again', 'woocommerce-eu-vat-number' ) );
			} else {
				set_transient( $transient_name, 0, 7 * DAY_IN_SECONDS );
				return false;
			}

		} elseif ( $cached_result ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate a number and store the result
	 * @param  string $vat_number
	 * @param  string $billing_country
	 */
	public static function validate( $vat_number, $billing_country ) {
		$vat_number                        = self::get_formatted_vat_number( $vat_number );
		$valid                             = self::vat_number_is_valid( $vat_number, $billing_country );
		self::$vat_number_is_valid         = true === $valid;
		self::$vat_number_validated        = is_bool( $valid );
		self::$vat_number                  = ( self::$vat_number_is_valid ? self::get_vat_number_prefix( $billing_country ) : '' ) . $vat_number;
		self::$vat_number_validation_error = is_wp_error( $valid ) ? $valid->get_error_message() : '';
	}

	/**
	 * Maybe set tax exception based on countries
	 * @param  bool $excempt are they excempt?
	 * @param  string country of customer
	 */
	public static function maybe_set_vat_excempt( $excempt, $country ) {
		if ( ( $country === WC()->countries->get_base_country() && 'yes' === get_option( 'woocommerce_eu_vat_number_deduct_in_base', 'yes' ) ) || $country !== WC()->countries->get_base_country() ) {
			WC()->customer->set_is_vat_exempt( $excempt );
		}
	}

	/**
	 * Validate the VAT number when the checkout form is processed.
	 */
	public static function process_checkout() {
		self::reset();

		if ( in_array( WC()->customer->get_country(), self::get_eu_countries() ) && ! empty( $_POST['vat_number'] ) ) {

			$taxed_country = WC()->customer->get_country();
			self::validate( wc_clean( isset( $_POST['vat_number'] ) ? $_POST['vat_number'] : '' ), WC()->customer->get_country() );
			self::validate_ip();

			if ( self::$vat_number_validated && self::$vat_number_is_valid ) {
				self::maybe_set_vat_excempt( true, $taxed_country );
			} elseif ( self::$vat_number_validated ) {
				switch ( get_option( 'woocommerce_eu_vat_number_failure_handling', 'reject' ) ) {
					case 'accept_with_vat' :
					break;
					case 'accept' :
						self::maybe_set_vat_excempt( true, $taxed_country );
					break;
					default :
						wc_add_notice( sprintf( __( 'You have entered an invalid VAT number (%s) for your billing country (%s).', 'woocommerce-eu-vat-number' ), self::$vat_number, WC()->customer->get_country() ), 'error' );
					break;
				}
			} else {
				wc_add_notice( self::$vat_number_validation_error, 'error' );
			}

		} elseif ( 'yes' === get_option( 'woocommerce_eu_vat_number_validate_ip', 'no' ) && self::cart_has_digital_goods() ) {
			// Validate IP for B2C transactions
			self::validate_ip();

			if ( self::is_self_declaration_required( self::$ip_country, WC()->customer->get_country() ) && empty( $_POST['location_confirmation'] ) ) {
				$ip_address = apply_filters( 'wc_eu_vat_self_declared_ip_address', WC_Geolocation::get_ip_address() );

				wc_add_notice( sprintf( __( 'Your IP Address (%s) does not match your billing country (%s). European VAT laws require your IP address to match your billing country when purchasing digital goods in the EU. Please confirm you are located within your billing country using the checkbox below.', 'woocommerce-eu-vat-number' ), $ip_address, WC()->customer->get_country() ), 'error' );

				if ( version_compare( WC_VERSION, '2.3', '<' ) ) {
					WC()->session->set( 'refresh_totals', true );
				}
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
	public static function is_self_declaration_required( $ip_country, $billing_country ) {
		return ( empty( $ip_country ) || in_array( $ip_country, self::get_eu_countries() ) || in_array( $billing_country, self::get_eu_countries() ) ) && $ip_country !== $billing_country;
	}

	/**
	 * Validate the IP address of the customer
	 */
	public static function validate_ip() {
		if ( 'yes' === get_option( 'woocommerce_eu_vat_number_validate_ip', 'no' ) && self::cart_has_digital_goods() ) {
			$geoip            = WC_Geolocation::geolocate_ip();
			self::$ip_country = $geoip['country'];
		}
	}

	/**
	 * Show checkbox for customer to confirm their location (location evidence for B2C)
	 */
	public static function location_confirmation() {
		if ( 'yes' === get_option( 'woocommerce_eu_vat_number_validate_ip', 'no' ) && self::cart_has_digital_goods() ) {
			if ( empty( self::$vat_number ) && self::is_self_declaration_required( self::$ip_country, WC()->customer->get_country() ) ) {
				wc_get_template( 'location-confirmation-field.php', array(
					'location_confirmation_is_checked' => isset( $_POST['location_confirmation'] ),
					'countries'                        => WC()->countries->get_countries()
				), 'woocommerce-eu-vat-number', untrailingslashit( plugin_dir_path( WC_EU_VAT_FILE ) ) . '/templates/' );
			}
		}
	}

	/**
	 * Add VAT number to order emails
	 * @param array $keys
	 * @return array
	 */
	public static function order_meta_keys( $keys ) {
		$keys[ __( 'VAT Number', 'woocommerce-eu-vat-number' ) ] = 'VAT Number';
		return $keys;
	}

	/**
	 * Triggered when the totals are updated on the checkout.
	 *
	 * @param array $form_data
	 */
	public static function ajax_update_checkout_totals( $form_data ) {
		parse_str( $form_data );

		self::reset();

		if ( empty( $billing_country ) && empty( $shipping_country ) ) {
			return;
		}

		if ( in_array( $billing_country, self::get_eu_countries() ) && ! empty( $vat_number ) ) {
			$taxed_country = ! empty( $billing_country ) ? $billing_country : '';
			self::validate( wc_clean( $vat_number ), $billing_country );

			if ( self::$vat_number_validated && self::$vat_number_is_valid ) {
				self::maybe_set_vat_excempt( true, $taxed_country );
			} elseif ( self::$vat_number_validated ) {
				switch ( get_option( 'woocommerce_eu_vat_number_failure_handling', 'reject' ) ) {
					case 'accept' :
						self::maybe_set_vat_excempt( true, $taxed_country );
					break;
				}
			}

		} else {
			self::validate_ip();
		}
	}

	/**
	 * Stores VAT Number to customer profile
	 *
	 * @param int $user_id
	 */
	public static function update_user_meta( $user_id ) {
		if ( $user_id && self::$vat_number ) {
			update_user_meta( $user_id, 'vat_number', self::$vat_number );
		}
	}

	/**
	 * Save VAT Number to the order
	 *
	 * @param int $order_id
	 */
	public static function update_order_meta( $order_id ) {
		update_post_meta( $order_id, 'VAT Number', self::$vat_number ); // Old field name
		update_post_meta( $order_id, '_vat_number', self::$vat_number );
		update_post_meta( $order_id, '_eu_vat_checked', 'true' );

		if ( ! is_null( self::$vat_number_validated ) ) {
			update_post_meta( $order_id, '_vat_number_is_validated', self::$vat_number_validated ? 'true' : 'false' );
		}

		if ( ! is_null( self::$vat_number_is_valid ) ) {
			update_post_meta( $order_id, '_vat_number_is_valid', self::$vat_number_is_valid ? 'true' : 'false' );
		}

		if ( self::$vat_number_validation_error ) {
			update_post_meta( $order_id, '_vat_number_validation_error', self::$vat_number_validation_error );
		}

		if ( ! is_null( self::$ip_country ) ) {
			update_post_meta( $order_id, '_customer_ip_country', self::$ip_country );
			update_post_meta( $order_id, '_customer_self_declared_country', ( ! empty( $_POST['location_confirmation'] ) ? 'true' : 'false' ) );
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
		if ( $vat_id = get_post_meta( $order->id, 'VAT Number', true ) ) {
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
	 * Address formats
	 * @param  [type] $formats [description]
	 * @return [type]          [description]
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
	 * Pass vat number between orders
	 */
	public static function refund_vat_number( $refund_id ) {
		update_post_meta( $refund_id, '_vat_number', get_post_meta( wp_get_post_parent_id( $refund_id ), '_vat_number', true ) );
		update_post_meta( $refund_id, '_billing_country', get_post_meta( wp_get_post_parent_id( $refund_id ), '_billing_country', true ) );
		update_post_meta( $refund_id, '_order_currency', get_post_meta( wp_get_post_parent_id( $refund_id ), '_order_currency', true ) );
	}

	/**
	 * Force Digital Goods tax class to use billing address
	 * @param  array $location
	 * @param  string $tax_class
	 * @return array
	 */
	public static function woocommerce_get_tax_location( $location, $tax_class = '' ) {
		if ( ! empty( WC()->customer ) && in_array( sanitize_title( $tax_class ), get_option( 'woocommerce_eu_vat_number_digital_tax_classes', array() ) ) ) {
			return array(
				WC()->customer->get_country(),
				WC()->customer->get_state(),
				WC()->customer->get_postcode(),
				WC()->customer->get_city()
			);
		}
		return $location;
	}
}

WC_EU_VAT_Number::init();
