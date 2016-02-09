<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_EU_VAT_Admin class
 */
class WC_EU_VAT_Admin {

	/** Stores settings @var array */
	private static $settings                    = array();

	/**
	 * Constructor
	 */
	public static function init() {
		self::$settings = include( 'data/eu-vat-number-settings.php' );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		add_action( 'woocommerce_admin_billing_fields', array( __CLASS__, 'admin_billing_fields' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 30 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'woocommerce_process_shop_order_meta', 'WC_EU_VAT_Admin::save', 50, 2 );

		// Settings
		add_action( 'woocommerce_settings_tax_options_end', array( __CLASS__, 'admin_settings' ) );
		add_action( 'woocommerce_update_options_tax', array( __CLASS__, 'save_admin_settings' ) );

		// Columns
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'show_column' ), 5, 2 );
	}

	/**
	 * Admin notices
	 */
	public static function admin_notices() {
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2.9', '<' ) ) {
			echo '<div class="error fade"><p>' . __( 'WooCommerce EU VAT Numbers Requries WooCommerce 2.2.9 - please update as soon as possible.', 'woocommerce-eu-vat-number' ) . '</p></div>' . "\n";
		}
	}

	/**
	 * Save meta box data
	 */
	public static function save( $post_id, $post ) {
		update_post_meta( $post_id, '_vat_number', wc_clean( $_POST[ '_vat_number' ] ) );
		update_post_meta( $post_id, 'VAT Number', wc_clean( $_POST[ '_vat_number' ] ) );
	}

	/**
	 * Add fields to admin
	 * @param  array $fields
	 * @return array
	 */
	public static function admin_billing_fields( $fields ) {
		$fields['vat_number'] = array(
			'label' => __( 'VAT Number', 'woocommerce-eu-vat-number' ),
			'show'  => false,
			'id'    => '_vat_number'
		);
		return $fields;
	}

	/**
	 * Enqueue styles
	 */
	public static function styles() {
		wp_enqueue_style( 'wc_eu_vat_admin_css', plugins_url( 'assets/css/admin.css', WC_EU_VAT_FILE ), array(), WC_EU_VAT_VERSION );
	}

	/**
	 * Add Meta Boxes
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'wc_eu_vat', __( 'EU VAT', 'woocommerce-eu-vat-number' ), array( __CLASS__, 'output' ), 'shop_order', 'side' );
	}

	/**
	 * Output meta box
	 */
	public static function output() {
		global $post, $theorder;

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}

		// We only need this box for EU orders
		if ( ! in_array( $theorder->billing_country, WC_EU_VAT_Number::get_eu_countries() ) ) {
			echo wpautop( __( 'This order is out of scope for EU VAT.', 'woocommerce-eu-vat-number' ) );
			return;
		}
		?>
		<table class="wc-eu-vat-table" cellspacing="0">
			<tbody>
				<tr>
					<th><?php _e( 'B2B Transaction?', 'woocommerce-eu-vat-number' ); ?></th>
					<td><?php echo get_post_meta( $post->ID, '_vat_number', true ) ? __( 'Yes', 'woocommerce-eu-vat-number' ) : __( 'No', 'woocommerce-eu-vat-number' ); ?></td>
					<td></td>
				</tr>
				<?php if ( get_post_meta( $post->ID, '_vat_number', true ) ) : ?>
					<tr>
						<th><?php _e( 'VAT ID', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php echo esc_html( get_post_meta( $post->ID, '_vat_number', true ) ); ?></td>
						<td><?php
						if ( get_post_meta( $post->ID, '_vat_number_is_validated', true ) !== 'true' ) {
							echo '<span class="tips" data-tip="' . __( 'Validation was not possible', 'woocommerce-eu-vat-number' ) . '">?<span>';
						} else {
							echo get_post_meta( $post->ID, '_vat_number_is_valid', true ) === 'true' ? '&#10004;' : '&#10008;';
						}
						?></td>
					</tr>
				<?php endif; ?>
				<?php if ( metadata_exists( 'post', $post->ID, '_customer_ip_country' ) ) : ?>
					<tr>
						<th><?php _e( 'IP Address', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php echo get_post_meta( $post->ID, '_customer_ip_address', true ) ? esc_html( get_post_meta( $post->ID, '_customer_ip_address', true ) ) : __( 'Unknown', 'woocommerce-eu-vat-number' ); ?></td>
						<td></td>
					</tr>
					<tr>
						<th><?php _e( 'IP Country', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php echo get_post_meta( $post->ID, '_customer_ip_country', true ) ? esc_html( get_post_meta( $post->ID, '_customer_ip_country', true ) ) : __( 'Unknown', 'woocommerce-eu-vat-number' ); ?></td>
						<td></td>
					</tr>
					<tr>
						<th><?php _e( 'Billing Country', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php echo get_post_meta( $post->ID, '_billing_country', true ) ? esc_html( get_post_meta( $post->ID, '_billing_country', true ) ) : __( 'Unknown', 'woocommerce-eu-vat-number' ); ?></td>
						<td></td>
					</tr>
				<?php endif; ?>
				<?php if ( get_post_meta( $post->ID, '_customer_self_declared_country', true ) === 'true' ) : ?>
					<tr>
						<th><?php _e( 'Self-declared Country', 'woocommerce-eu-vat-number' ); ?></th>
						<td><?php echo '&#10004;'; ?></td>
						<td></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Add settings to WC
	 */
	public static function admin_settings() {
		woocommerce_admin_fields( self::$settings );
	}

	/**
	 * Save settings
	 */
	public static function save_admin_settings() {
		global $current_section;

		if ( ! $current_section ) {
			woocommerce_update_options( self::$settings );
		}
	}

	/**
	 * Add column
	 */
	public static function add_column( $existing_columns ) {
		$columns = array();

		foreach ( $existing_columns as $existing_column_key => $existing_column ) {
			$columns[ $existing_column_key ] = $existing_column;

			if ( 'shipping_address' === $existing_column_key ) {
				$columns[ 'eu_vat' ] = __( 'EU VAT', 'woocommerce-eu-vat-number' );
			}
		}

		return $columns;
	}

	/**
	 * Show Column
	 */
	public static function show_column( $column ) {
		global $post, $woocommerce, $the_order;

		if ( $column === 'eu_vat' ) {
			if ( empty( $the_order ) || $the_order->id != $post->ID ) {
				$the_order = wc_get_order( $post->ID );
			}
			if ( ! in_array( $the_order->billing_country, WC_EU_VAT_Number::get_eu_countries() ) ) {
				echo wpautop( __( 'Out of scope.', 'woocommerce-eu-vat-number' ) );
			} elseif ( get_post_meta( $post->ID, '_eu_vat_checked', true ) ) {
				if ( $vat_number = get_post_meta( $post->ID, '_vat_number', true ) ) : ?>

					<ul class="eu-vat-overview">
						<li><strong><?php _e( 'VAT ID', 'woocommerce-eu-vat-number' ); ?>:</strong> <?php echo esc_html( $vat_number ); ?></li>
					</ul>

				<?php elseif ( metadata_exists( 'post', $post->ID, '_customer_ip_country' ) ) : ?>

					<ul class="eu-vat-overview">
						<li><strong><?php _e( 'Billing', 'woocommerce-eu-vat-number' ); ?>:</strong> <?php echo get_post_meta( $post->ID, '_billing_country', true ) ? esc_html( get_post_meta( $post->ID, '_billing_country', true ) ) : __( 'Unknown', 'woocommerce-eu-vat-number' ); ?></li>
						<li><strong><?php _e( 'Matches IP?', 'woocommerce-eu-vat-number' ); ?>:</strong> <?php echo get_post_meta( $post->ID, '_billing_country', true ) === get_post_meta( $post->ID, '_customer_ip_country', true ) ? '&#10004;' : __( 'No', 'woocommerce-eu-vat-number' ); ?></li>
						<li><strong><?php _e( 'Self Declared?', 'woocommerce-eu-vat-number' ); ?>:</strong> <?php echo get_post_meta( $post->ID, '_customer_self_declared_country', true ) === 'true' ? '&#10004;' : __( 'No', 'woocommerce-eu-vat-number' ); ?></li>
					</ul>

				<?php else :

					echo '-';

				endif;
			} else {
				echo '-';
			}
		}
	}
}

WC_EU_VAT_Admin::init();
