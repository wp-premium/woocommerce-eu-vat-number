<?php
/**
 * My Vat Number
 *
 * @author 		WooThemes
 * @package 	woocommerce-eu-vat-number/templates
 * @version     2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<header class="title">
	<h3><?php _e( 'VAT Number', 'woocommerce-eu-vat-number' ); ?></h3>
</header>
<form method="post">
	<p class="form-row form-row form-row-first">
		<input type="text" value="<?php echo esc_attr( $vat_number ); ?>" id="vat_number" name="vat_number" class="input-text" />
	</p>
	<div class="clear"></div>
	<p>
		<input type="submit" value="<?php echo esc_attr( _e( 'Save', 'woocommerce-eu-vat-number' ) ); ?>" class="button" />
		<?php wp_nonce_field( 'woocommerce-edit_vat_number' ); ?>
		<input type="hidden" name="action" value="edit_vat_number" />
	</p>
</form>
