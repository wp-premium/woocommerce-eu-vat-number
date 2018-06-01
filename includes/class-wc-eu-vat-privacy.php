<?php
if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_EU_VAT_Privacy extends WC_Abstract_Privacy {
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct( __( 'EU VAT', 'woocommerce-eu-vat-number' ) );

		$this->add_exporter( 'woocommerce-eu-vat-number-order-data', __( 'WooCommerce EU VAT Order Data', 'woocommerce-eu-vat-number' ), array( $this, 'order_data_exporter' ) );
		$this->add_exporter( 'woocommerce-eu-vat-number-customer-data', __( 'WooCommerce EU VAT Customer Data', 'woocommerce-eu-vat-number' ), array( $this, 'customer_data_exporter' ) );

		$this->add_eraser( 'woocommerce-eu-vat-number-customer-data', __( 'WooCommerce EU VAT Customer Data', 'woocommerce-eu-vat-number' ), array( $this, 'customer_data_eraser' ) );
		$this->add_eraser( 'woocommerce-eu-vat-number-order-data', __( 'WooCommerce EU VAT Order Data', 'woocommerce-eu-vat-number' ), array( $this, 'order_data_eraser' ) );
	}

	/**
	 * Returns a list of orders.
	 *
	 * @param string  $email_address
	 * @param int     $page
	 *
	 * @return array WP_Post
	 */
	protected function get_orders( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$order_query    = array(
			'limit'          => 10,
			'page'           => $page,
		);

		if ( $user instanceof WP_User ) {
			$order_query['customer_id'] = (int) $user->ID;
		} else {
			$order_query['billing_email'] = $email_address;
		}

		return wc_get_orders( $order_query );
	}

	/**
	 * Gets the message of the privacy to display.
	 *
	 */
	public function get_privacy_message() {
		return wpautop( sprintf( __( 'By using this extension, you may be storing personal data or sharing data with an external service. <a href="%s" target="_blank">Learn more about how this works, including what you may want to include in your privacy policy.</a>', 'woocommerce-eu-vat-number' ), 'https://docs.woocommerce.com/document/marketplace-privacy/#woocommerce-eu-vat-number' ) );
	}

	/**
	 * Handle exporting data for Orders.
	 *
	 * @param string $email_address E-mail address to export.
	 * @param int    $page          Pagination of data.
	 *
	 * @return array
	 */
	public function order_data_exporter( $email_address, $page = 1 ) {
		$done           = false;
		$data_to_export = array();

		$orders = $this->get_orders( $email_address, (int) $page );

		$done = true;

		if ( 0 < count( $orders ) ) {
			foreach ( $orders as $order ) {
				$data_to_export[] = array(
					'group_id'    => 'woocommerce_orders',
					'group_label' => __( 'Orders', 'woocommerce-eu-vat-number' ),
					'item_id'     => 'order-' . $order->get_id(),
					'data'        => array(
						array(
							'name'  => __( 'EU VAT number', 'woocommerce-eu-vat-number' ),
							'value' => get_post_meta( $order->get_id(), '_vat_number', true ),
						),
						array(
							'name'  => __( 'EU VAT country', 'woocommerce-eu-vat-number' ),
							'value' => get_post_meta( $order->get_id(), '_customer_ip_country', true ),
						),
						array(
							'name'  => __( 'EU VAT self-declared country', 'woocommerce-eu-vat-number' ),
							'value' => get_post_meta( $order->get_id(), '_customer_self_declared_country', true ),
						),
					),
				);
			}

			$done = 10 > count( $orders );
		}

		return array(
			'data' => $data_to_export,
			'done' => $done,
		);
	}

	/**
	 * Finds and exports customer data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function customer_data_exporter( $email_address, $page ) {
		$user           = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
		$data_to_export = array();

		if ( $user instanceof WP_User ) {
			$data_to_export[] = array(
				'group_id'    => 'woocommerce_customer',
				'group_label' => __( 'Customer Data', 'woocommerce-eu-vat-number' ),
				'item_id'     => 'user',
				'data'        => array(
					array(
						'name'  => __( 'VAT number', 'woocommerce-eu-vat-number' ),
						'value' => get_user_meta( $user->ID, 'vat_number', true ),
					),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Finds and erases customer data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function customer_data_eraser( $email_address, $page ) {
		$page = (int) $page;
		$user = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.

		$vat_number = get_user_meta( $user->ID, 'vat_number', true );

		$items_removed  = false;
		$messages       = array();

		if ( ! empty( $vat_number ) ) {
			$items_removed = true;
			delete_user_meta( $user->ID, 'vat_number' );
			$messages[] = __( 'EU VAT User Data Erased.', 'woocommerce-eu-vat-number' );
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Finds and erases order data by email address.
	 *
	 * @since 3.4.0
	 * @param string $email_address The user email address.
	 * @param int    $page  Page.
	 * @return array An array of personal data in name value pairs
	 */
	public function order_data_eraser( $email_address, $page ) {
		$orders = $this->get_orders( $email_address, (int) $page );

		$items_removed  = false;
		$items_retained = false;
		$messages       = array();

		foreach ( (array) $orders as $order ) {
			$order = wc_get_order( $order->get_id() );

			list( $removed, $retained, $msgs ) = $this->maybe_handle_order( $order );
			$items_removed  |= $removed;
			$items_retained |= $retained;
			$messages        = array_merge( $messages, $msgs );
		}

		// Tell core if we have more orders to work on still
		$done = count( $orders ) < 10;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Handle eraser of data tied to Orders
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	protected function maybe_handle_order( $order ) {
		$order_id           = $order->get_id();

		$vat_number      = get_post_meta( $order_id, '_vat_number', true );
		$ip_country      = get_post_meta( $order_id, '_customer_ip_country', true );
		$self_ip_country = get_post_meta( $order_id, '_customer_self_declared_country', true );

		if ( empty( $vat_number ) && empty( $ip_country ) && empty( $self_ip_country ) ) {
			return array( false, false, array() );
		}

		delete_post_meta( $order_id, '_vat_number' );
		delete_post_meta( $order_id, '_customer_ip_country' );
		delete_post_meta( $order_id, '_customer_self_declared_country' );

		return array( true, false, array( __( 'EU VAT Order Data Erased.', 'woocommerce-eu-vat-number' ) ) );
	}
}

new WC_EU_VAT_Privacy();
