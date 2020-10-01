<?php
/**
 * Admin handling.
 *
 * @package woocommerce-eu-vat-number
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Admin class.
 */
class WC_EU_VAT_Admin {

	/**
	 * Admin settings array.
	 *
	 * @var array
	 */
	private static $settings = array();

	/**
	 * Constructor.
	 */
	public static function init() {
		self::$settings = require_once 'data/eu-vat-number-settings.php';
		add_action( 'woocommerce_admin_billing_fields', array( __CLASS__, 'admin_billing_fields' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'woocommerce_settings_tax_options_end', array( __CLASS__, 'admin_settings' ) );
		add_action( 'woocommerce_update_options_tax', array( __CLASS__, 'save_admin_settings' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'show_column' ), 5, 2 );
		add_action( 'woocommerce_order_before_calculate_taxes', array( __CLASS__, 'admin_order' ), 10, 2 );
		add_filter( 'woocommerce_customer_meta_fields', array( __CLASS__, 'add_customer_meta_fields' ) );
		add_filter( 'woocommerce_ajax_get_customer_details', array( __CLASS__, 'get_customer_details' ), 10, 3 );
	}

	/**
	 * Add fields to admin. This also handles save.
	 *
	 * @param  array $fields Fields being shown in admin.
	 * @return array
	 */
	public static function admin_billing_fields( $fields ) {
		global $theorder;

		$vat_number = is_object( $theorder ) ? wc_eu_vat_get_vat_from_order( $theorder ) : '';

		$fields['vat_number'] = array(
			'label' => get_option( 'woocommerce_eu_vat_number_field_label', 'VAT number' ),
			'show'  => false,
			'id'    => '_billing_vat_number',
			'value' => $vat_number,
		);
		return $fields;
	}

	/**
	 * Add Meta Boxes.
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'wc_eu_vat', __( 'EU VAT', 'woocommerce-eu-vat-number' ), array( __CLASS__, 'output' ), 'shop_order', 'side' );
	}

	/**
	 * Enqueue styles.
	 */
	public static function styles() {
		wp_enqueue_style( 'wc_eu_vat_admin_css', plugins_url( 'assets/css/admin.css', WC_EU_VAT_FILE ), array(), WC_EU_VAT_VERSION );
	}

	/**
	 * Is this is an EU order?
	 *
	 * @param  WC_Order $order The order object.
	 * @return boolean
	 */
	protected static function is_eu_order( $order ) {
		return in_array( $order->get_billing_country(), WC_EU_VAT_Number::get_eu_countries() );
	}

	/**
	 * Get order VAT Number data in one object/array.
	 *
	 * @param  WC_Order $order The order object.
	 * @return object
	 */
	protected static function get_order_vat_data( $order ) {
		if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
			return (object) array(
				'vat_number'      => wc_eu_vat_get_vat_from_order( $order ),
				'valid'           => 'true' === get_post_meta( $order->id, '_vat_number_is_valid', true ),
				'validated'       => 'true' === get_post_meta( $order->id, '_vat_number_is_validated', true ),
				'billing_country' => $order->billing_country,
				'ip_address'      => get_post_meta( $order->id, '_customer_ip_address', true ),
				'ip_country'      => get_post_meta( $order->id, '_customer_ip_country', true ),
				'self_declared'   => 'true' === get_post_meta( $order->id, '_customer_self_declared_country', true ),
			);
		} else {
			return (object) array(
				'vat_number'      => wc_eu_vat_get_vat_from_order( $order ),
				'valid'           => wc_string_to_bool( $order->get_meta( '_vat_number_is_valid', true ) ),
				'validated'       => wc_string_to_bool( $order->get_meta( '_vat_number_is_validated', true ) ),
				'billing_country' => $order->get_billing_country(),
				'ip_address'      => $order->get_customer_ip_address(),
				'ip_country'      => $order->get_meta( '_customer_ip_country', true ),
				'self_declared'   => wc_string_to_bool( $order->get_meta( '_customer_self_declared_country', true ) ),
			);
		}
	}

	/**
	 * Output meta box.
	 */
	public static function output() {
		global $post, $theorder;

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}

		// We only need this box for EU orders.
		if ( ! self::is_eu_order( $theorder ) ) {
			echo wpautop( __( 'This order is out of scope for EU VAT.', 'woocommerce-eu-vat-number' ) );
			return;
		}

		$data      = self::get_order_vat_data( $theorder );
		$countries = WC()->countries->get_countries();
		?>
		<table class="wc-eu-vat-table" cellspacing="0">
			<tbody>
				<tr>
					<th><?php _e( 'B2B', 'woocommerce-eu-vat-number' ); ?></th>
					<td><?php echo $data->vat_number ? __( 'Yes', 'woocommerce-eu-vat-number' ) : __( 'No', 'woocommerce-eu-vat-number' ); ?></td>
					<td></td>
				</tr>

				<?php if ( $data->vat_number ) : ?>
					<tr>
						<th><?php echo get_option( 'woocommerce_eu_vat_number_field_label', 'VAT number' ); ?></th>
						<td><?php echo esc_html( $data->vat_number ); ?></td>
						<td><?php
							if ( ! $data->validated ) {
								echo '<span class="tips" data-tip="' . wc_sanitize_tooltip( __( 'Validation was not possible', 'woocommerce-eu-vat-number' ) ) . '">?<span>';
							} else {
								echo $data->valid ? '&#10004;' : '&#10008;';
							}
						?></td>
					</tr>
				<?php else : ?>
					<tr>
						<th><?php _e( 'IP Address', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php echo $data->ip_address ? esc_html( $data->ip_address ) : __( 'Unknown', 'woocommerce-eu-vat-number' ); ?></td>
						<td></td>
					</tr>
					<tr>
						<th><?php _e( 'IP Country', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php
							if ( $data->ip_country ) {
								echo esc_html__( $countries[ $data->billing_country ] ) . ' ';

								if ( $data->billing_country === $data->ip_country ) {
									echo '<span style="color:green">&#10004;</span>';
								} elseif ( $data->self_declared ) {
									esc_html_e( '(self-declared)', 'woocommerce-eu-vat-number' );
								} else {
									echo '<span style="color:red">&#10008;</span>';
								}
							} else {
								esc_html_e( 'Unknown', 'woocommerce-eu-vat-number' );
							}
						?><td></td>
					</tr>
					<tr>
						<th><?php _e( 'Billing Country', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php echo $data->billing_country ? esc_html( $countries[ $data->billing_country ] ) : __( 'Unknown', 'woocommerce-eu-vat-number' ); ?></td>
						<td></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Add settings to WC.
	 */
	public static function admin_settings() {
		woocommerce_admin_fields( self::$settings );
	}

	/**
	 * Save settings.
	 */
	public static function save_admin_settings() {
		global $current_section;

		if ( ! $current_section ) {
			woocommerce_update_options( self::$settings );
		}
	}

	/**
	 * Add column.
	 *
	 * @param array $existing_columns Columns array.
	 */
	public static function add_column( $existing_columns ) {
		$columns = array();

		foreach ( $existing_columns as $existing_column_key => $existing_column ) {
			$columns[ $existing_column_key ] = $existing_column;

			if ( 'shipping_address' === $existing_column_key ) {
				$columns['eu_vat'] = __( 'EU VAT', 'woocommerce-eu-vat-number' );
			}
		}

		return $columns;
	}

	/**
	 * Show Column.
	 *
	 * @param string $column Column being shown.
	 */
	public static function show_column( $column ) {
		global $post, $the_order;

		if ( 'eu_vat' === $column ) {
			echo '<p class="eu-vat-overview">';

			if ( ! self::is_eu_order( $the_order ) ) {
				echo '<span class="na">&ndash;</span>';
			} else {
				$data = self::get_order_vat_data( $the_order );

				if ( $data->vat_number ) {
					echo esc_html__( $data->vat_number ) . ' ';

					if ( $data->validated && $data->valid ) {
						echo '<span style="color:green">&#10004;</span>';
					} elseif ( ! $data->validated ) {
						esc_html_e( '(validation failed)', 'woocommerce-eu-vat-number' );
					} else {
						echo '<span style="color:red">&#10008;</span>';
					}
				} else {
					$countries = WC()->countries->get_countries();

					echo esc_html__( $countries[ $data->billing_country ] ) . ' ';

					if ( $data->billing_country === $data->ip_country ) {
						echo '<span style="color:green">&#10004;</span>';
					} elseif ( $data->self_declared ) {
						esc_html_e( '(self-declared)', 'woocommerce-eu-vat-number' );
					} else {
						echo '<span style="color:red">&#10008;</span>';
					}
				}
			}
			echo '</p>';
		}
	}

	/**
	 * Handles VAT when order is created/edited within admin manually.
	 *
	 * @since 2.3.14
	 * @param array $args
	 * @param object $order
	 */
	public static function admin_order( $args, $order ) {
		if ( ! is_object( $order ) ) {
			return;
		}

		/*
		 * First try and get the billing country from the
		 * address form (adding new order). If it is not
		 * found, get it from the order (editing the order).
		 */
		$billing_country  = ! empty( $_POST['_billing_country'] ) ? wc_clean( $_POST['_billing_country'] ) : $order->get_billing_country();
		$shipping_country = ! empty( $_POST['_shipping_country'] ) ? wc_clean( $_POST['_shipping_country'] ) : $order->get_shipping_country();

		/*
		 * First try and get the VAT number from the
		 * address form (adding new order). If it is not
		 * found, get it from the order (editing the order).
		 */
		$vat_number         = wc_eu_vat_get_vat_from_order( $order );
		$vat_number         = WC_EU_VAT_Number::get_formatted_vat_number( $vat_number );
		$valid              = WC_EU_VAT_Number::vat_number_is_valid( $vat_number, $billing_country );
		$base_country_match = WC_EU_VAT_Number::is_base_country_match( $billing_country, $shipping_country );

		// Allow empty input to clear VAT field.
		if ( empty( $vat_number ) || ( 'no' === get_option( 'woocommerce_eu_vat_number_deduct_in_base', 'yes' ) && $base_country_match ) ) {
			add_filter( 'woocommerce_order_is_vat_exempt', '__return_false' );
			return;
		}

		$order->update_meta_data( '_vat_number_is_validated', 'true' );

		try {
			if ( is_wp_error( $valid ) ) {
				throw new Exception( $valid->get_error_message() );
			}

			if ( ! $valid ) {
				throw new Exception( sprintf( __( 'You have entered an invalid %1$s (%2$s) for your billing country (%3$s).', 'woocommerce-eu-vat-number' ), get_option( 'woocommerce_eu_vat_number_field_label', 'VAT number' ), $vat_number, $billing_country ) );
			}

			$order->update_meta_data( '_vat_number_is_valid', 'true' );
			add_filter( 'woocommerce_order_is_vat_exempt', '__return_true' );
			return;
		} catch ( Exception $e ) {
			$order->update_meta_data( '_vat_number_is_valid', 'false' );
			echo '<script>alert( "' . $e->getMessage() . '" )</script>';
		}
	}

	/**
	 * Adds custom fields to user profile.
	 *
	 * @since 2.3.21
	 * @param array $fields WC defined user fields.
	 * @return array $fields Modified user fields.
	 */
	public static function add_customer_meta_fields( $fields ) {
		$fields['billing']['fields']['vat_number'] = array(
			'label'       => __( 'VAT number', 'woocommerce-eu-vat-number' ),
			'description' => '',
		);

		return $fields;
	}

	/**
	 * Return VAT information to get customer details via AJAX.
	 *
	 * @since 2.3.21
	 * @param array  $data The customer's data in context.
	 * @param object $customer The customer object in context.
	 * @param int    $user_id The user ID in context.
	 * @return array $data Modified user data.
	 */
	public static function get_customer_details( $data, $customer, $user_id ) {
		$data['billing']['vat_number'] = get_user_meta( $user_id, 'vat_number', true );

		return $data;
	}
}

WC_EU_VAT_Admin::init();
