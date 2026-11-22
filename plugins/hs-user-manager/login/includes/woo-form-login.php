<?php
/**
 * جایگزین قالب ووکامرس myaccount/form-login.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
do_action( 'woocommerce_before_customer_login_form' );
echo do_shortcode( '[hs_auth redirect="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '"]' ); // phpcs:ignore
do_action( 'woocommerce_after_customer_login_form' );
