<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Report_B2C_Sales class
 */
class WC_EU_VAT_Report_EU_VAT extends WC_Admin_Report {

	/**
	 * Get the legend for the main chart sidebar
	 * @return array
	 */
	public function get_chart_legend() {
		return array();
	}

	/**
	 * Output an export link
	 */
	public function get_export_button() {
		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : 'last_month';
		?>
		<a
			href="#"
			download="report-<?php echo esc_attr( $current_range ); ?>-<?php echo date_i18n( 'Y-m-d', current_time('timestamp') ); ?>.csv"
			class="export_csv"
			data-export="table"
		>
			<?php _e( 'Export CSV', 'woocommerce-eu-vat-number' ); ?>
		</a>
		<?php
	}

	/**
	 * Output the report
	 */
	public function output_report() {
		$ranges = array(
			'prev_quarter' => __( 'Previous Quarter', 'woocommerce-eu-vat-number' ),
			'last_quarter' => __( 'Last Quarter', 'woocommerce-eu-vat-number' ),
			'quarter'      => __( 'This Quarter', 'woocommerce-eu-vat-number' ),
		);

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : 'quarter';

		if ( ! in_array( $current_range, array( 'custom', 'prev_quarter', 'last_quarter', 'quarter' ) ) ) {
			$current_range = 'quarter';
		}

		$this->calculate_current_range( $current_range );

		$hide_sidebar = true;

		include( WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php');
	}

	/**
	 * Get the current range and calculate the start and end dates
	 * @param  string $current_range
	 */
	public function calculate_current_range( $current_range ) {
		$this->chart_groupby = 'month';
		$quarter             = absint( ceil( date( 'm', current_time( 'timestamp' ) ) / 3 ) );
		$year                = absint( date( 'Y', current_time( 'timestamp' ) ) );

		switch ( $current_range ) {
			case 'prev_quarter' :
				$quarter = $quarter - 2;
				if ( 0 === $quarter ) {
					$quarter = 4;
					$year --;
				} elseif ( -1 === $quarter ) {
					$quarter = 3;
					$year --;
				}
			break;
			case 'last_quarter' :
				$quarter = $quarter - 1;
				if ( 0 === $quarter ) {
					$quarter = 4;
					$year --;
				}
			break;
			case 'custom' :
				parent::calculate_current_range( $current_range );
				return;
			break;
		}

		if ( 1 === $quarter ) {
			$this->start_date = strtotime( $year . '-01-01' );
			$this->end_date   = strtotime( date( 'Y-m-t', strtotime( $year . '-03-01' ) ) );
		} elseif ( 2 === $quarter ) {
			$this->start_date = strtotime( $year . '-04-01' );
			$this->end_date   = strtotime( date( 'Y-m-t', strtotime( $year . '-06-01' ) ) );
		} elseif ( 3 === $quarter ) {
			$this->start_date = strtotime( $year . '-07-01' );
			$this->end_date   = strtotime( date( 'Y-m-t', strtotime( $year . '-09-01' ) ) );
		} elseif ( 4 === $quarter ) {
			$this->start_date = strtotime( $year . '-10-01' );
			$this->end_date   = strtotime( date( 'Y-m-t', strtotime( $year . '-12-01' ) ) );
		}
	}

	/**
	 * Get the main chart
	 *
	 * @return string
	 */
	public function get_main_chart() {
		global $wpdb;

		$debug = false;

		// If debug is enabled, don't use cached data.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug = true;
		}

		$line_data = $this->get_order_report_data( array(
			'data' => array(
				'_line_total' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => '',
					'function'        => '',
					'name'            => '_line_total'
				),
				'_line_tax_data' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => '',
					'function'        => '',
					'name'            => '_line_tax_data'
				),
				'ID' => array(
					'type'     => 'post_data',
					'function' => '',
					'name'     => 'refund_id',
				),
			),
			'filter_range' => true,
			'query_type'   => 'get_results',
			'group_by'     => '',
			'order_types'  => array( 'shop_order', 'shop_order_refund' ),
			'order_status' => array( 'completed', 'refunded' ),
			'nocache'        => $debug,
		) );

		$grouped_tax_rows = array();

		foreach ( $line_data as $data ) {
			$line_total    = $data->_line_total;
			$line_tax_data = maybe_unserialize( $data->_line_tax_data );

			foreach ( $line_tax_data['total'] as $tax_id => $tax_value ) {
				if ( ! isset( $grouped_tax_rows[ $tax_id ] ) ) {
					$grouped_tax_rows[ $tax_id ] = (object) array(
						'amount'              => 0,
						'refunded_amount'     => 0,
						'tax_amount'          => 0,
						'refunded_tax_amount' => 0
					);
				}

				if ( $line_total < 0 ) {
					$grouped_tax_rows[ $tax_id ]->refunded_amount += $line_total;
				} else {
					$grouped_tax_rows[ $tax_id ]->amount += $line_total;
				}

				if ( $tax_value < 0 ) {
					$grouped_tax_rows[ $tax_id ]->refunded_tax_amount += wc_round_tax_total( $tax_value );
				} else {
					$grouped_tax_rows[ $tax_id ]->tax_amount += wc_round_tax_total( $tax_value );
				}
			}
		}

		$refunded_line_data = $this->get_order_report_data( array(
			'data' => array(
				'_line_total' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => '',
					'function'        => '',
					'name'            => '_line_total'
				),
				'_line_tax_data' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => '',
					'function'        => '',
					'name'            => '_line_tax_data'
				)
			),
			'filter_range' => true,
			'query_type'   => 'get_results',
			'group_by'     => '',
			'order_types'  => array( 'shop_order', 'shop_order_refund' ),
			'order_status' => array( 'refunded' ),
			'nocache'        => $debug,
		) );

		foreach ( $refunded_line_data as $data ) {
			$line_total    = $data->_line_total;
			$line_tax_data = maybe_unserialize( $data->_line_tax_data );

			foreach ( $line_tax_data['total'] as $tax_id => $tax_value ) {
				if ( ! isset( $grouped_tax_rows[ $tax_id ] ) ) {
					$grouped_tax_rows[ $tax_id ] = (object) array(
						'amount'              => 0,
						'refunded_amount'     => 0,
						'tax_amount'          => 0,
						'refunded_tax_amount' => 0
					);
				}

				$grouped_tax_rows[ $tax_id ]->refunded_amount += ( $line_total * -1 );
				$grouped_tax_rows[ $tax_id ]->refunded_tax_amount += wc_round_tax_total( $tax_value * -1 );
			}
		}

		$shipping_tax_amount = $this->get_order_report_data( array(
			'data' => array(
				'rate_id' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => '',
					'function'        => '',
					'name'            => 'rate_id',
				),
				'shipping_tax_amount' => array(
					'type'            => 'order_item_meta',
					'order_item_type' => 'tax',
					'function'        => '',
					'name'            => 'shipping_tax_amount',
				),
			),
			'filter_range' => true,
			'query_type'   => 'get_results',
			'group_by'     => '',
			'order_types'  => array( 'shop_order', 'shop_order_refund' ),
			'order_status' => array( 'completed' ),
			'nocache'        => $debug,
		) );

		foreach ( $shipping_tax_amount as $data ) {
			$tax_value  = $data->shipping_tax_amount;
			$tax_id     = $data->rate_id;

			if ( ! isset( $grouped_tax_rows[ $tax_id ] ) ) {
				$grouped_tax_rows[ $tax_id ] = (object) array(
					'amount'              => 0,
					'refunded_amount'     => 0,
					'tax_amount'          => 0,
					'refunded_tax_amount' => 0
				);
			}

			$grouped_tax_rows[ $tax_id ]->tax_amount += wc_round_tax_total( $tax_value );
		}

		$refund_amounts = $this->get_order_report_data( array(
			'data' => array(
				'ID' => array(
					'type'     => 'post_data',
					'function' => '',
					'name'     => 'refund_id',
				),
				'post_parent' => array(
					'type'     => 'post_data',
					'function' => '',
					'name'     => 'order_id',
				),
				'_refund_amount' => array(
					'type'     => 'meta',
					'function' => '',
					'name'     => 'total_refund',
				),
			),
			'group_by'            => 'refund_id',
			'query_type'          => 'get_results',
			'filter_range'        => true,
			'order_status'        => false,
			'parent_order_status' => array( 'completed', 'processing', 'on-hold' ),
			'nocache'               => $debug,
		) );

		foreach ( $refund_amounts as $refund_data ) {
			$order = wc_get_order( $refund_data->order_id );

			if ( is_object( $order ) ) {
				$cached_results = get_transient( strtolower( get_class( $this ) . '_' . $refund_data->order_id ) );

				if ( false === $cached_results ) {
					$cached_results = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta LEFT JOIN {$wpdb->prefix}woocommerce_order_items AS order_items ON order_itemmeta.order_item_id = order_items.order_item_id WHERE order_items.order_id = %d AND order_items.order_item_type = %s AND order_itemmeta.meta_key = %s", $refund_data->order_id, 'tax', 'rate_id' ) );

					set_transient( strtolower( get_class( $this ) . '_' . $refund_data->order_id ), $cached_results, DAY_IN_SECONDS );
				}

				$tax_id = $cached_results;

				if ( isset( $grouped_tax_rows[ $tax_id ] ) ) {

					$total_refund = $refund_data->total_refund;

					// Subtract any line items from this total
					foreach ( $line_data as $data ) {
						if ( $refund_data->refund_id === $data->refund_id ) {
							$total_refund += $data->_line_total;
							$line_tax_data = maybe_unserialize( $data->_line_tax_data );

							foreach ( $line_tax_data['total'] as $tax_id => $tax_value ) {
								$total_refund += wc_round_tax_total( $tax_value );
							}
						}
					}

					$grouped_tax_rows[ $tax_id ]->refunded_amount += -$total_refund;
				}
			}
		}
		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php _e( 'Country', 'woocommerce-eu-vat-number' ); ?></th>
					<th><?php _e( 'Code', 'woocommerce-eu-vat-number' ); ?></th>
					<th><?php _e( 'Tax Rate', 'woocommerce-eu-vat-number' ); ?></th>
					<th class="total_row"><?php _e( 'Amount', 'woocommerce-eu-vat-number-eu-vat' ); ?></th>
					<th class="total_row"><?php _e( 'Refunded Amount', 'woocommerce-eu-vat-number' ); ?></th>
					<th class="total_row"><?php _e( 'Final Amount', 'woocommerce-eu-vat-number' ); ?></th>
					<th class="total_row"><?php _e( 'Tax Amount', 'woocommerce-eu-vat-number' ); ?></th>
					<th class="total_row"><?php _e( 'Tax Refunded Amount', 'woocommerce-eu-vat-number' ); ?></th>
					<th class="total_row"><?php _e( 'Final Tax Amount ', 'woocommerce-eu-vat-number' ); ?></th>
				</tr>
			</thead>
			<?php if ( $grouped_tax_rows ) : ?>
				<tbody>
					<?php
					foreach ( $grouped_tax_rows as $rate_id => $tax_row ) {
						$rate = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d;", $rate_id ) );

						if ( ! is_object( $rate ) || ! in_array( $rate->tax_rate_country, WC_EU_VAT_Number::get_eu_countries() ) ) {
							continue;
						}
						?>
						<tr>
							<th scope="row"><?php echo esc_html( WC()->countries->countries[ $rate->tax_rate_country ] ); ?></th>
							<th scope="row"><?php echo esc_html( $rate->tax_rate_country ); ?></th>
							<td><?php echo apply_filters( 'woocommerce_reports_taxes_rate', $rate->tax_rate, $rate_id, $tax_row ); ?>%</td>
							<td class="total_row"><?php echo wc_price( $tax_row->amount ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->refunded_amount * -1 ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->amount + $tax_row->refunded_amount ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->tax_amount ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->refunded_tax_amount * -1 ); ?></td>
							<td class="total_row"><?php echo wc_price( $tax_row->tax_amount + $tax_row->refunded_tax_amount ); ?></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			<?php else : ?>
				<tbody>
					<tr>
						<td><?php _e( 'No taxes found in this period', 'woocommerce-eu-vat-number' ); ?></td>
					</tr>
				</tbody>
			<?php endif; ?>
		</table>
		<?php
	}
}
