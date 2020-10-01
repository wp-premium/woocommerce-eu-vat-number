<?php
/**
 * My Vat Number
 *
 * @package woocommerce-eu-vat-number/templates
 * @version 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php if ( $show_title ) : ?>
<header class="title">
	<h3><?php echo esc_html( get_option( 'woocommerce_eu_vat_number_field_label', 'VAT number' ) ); ?></h3>
</header>
<?php endif; ?>

<?php if ( ! empty( $messages ) ) { ?>
	<ul class="woocommerce-<?php echo esc_attr( $messages['status'] ); ?>">
		<li><?php echo wp_kses_post( $messages['message'] ); ?></li>
	</ul>
<?php } ?>
<form method="post">
	<p class="form-row form-row form-row-first">
		<input type="text" value="<?php echo esc_attr( $vat_number ); ?>" id="vat_number" name="vat_number" class="input-text" />
	</p>
	<div class="clear"></div>
	<p>
		<input type="submit" value="<?php echo esc_attr( __( 'Save', 'woocommerce-eu-vat-number' ) ); ?>" class="button" />
		<?php wp_nonce_field( 'woocommerce-edit_vat_number' ); ?>
		<input type="hidden" name="action" value="edit_vat_number" />
	</p>
</form>
