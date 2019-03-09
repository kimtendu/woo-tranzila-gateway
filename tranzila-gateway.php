<?php
/*
Plugin Name: WooCommerce Tranzila Gateway (
Description: Tranzila Payment gateway for WooCommerce.
Version: 0.0.1
Author: KimTenDu
Fork from: Anton Bond
Author URI: 
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/


if ( !defined('ABSPATH') ) {
	//Exit if accessed directly or woocommerce is not active
	exit;
}

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


	if (!class_exists('WC_Tranzila_Payment_Gateway_Addon')) {

		class WC_Tranzila_Payment_Gateway_Addon {

			function __construct() {
				$this->tgwc_loader_operations();
			}

			function tgwc_loader_operations() {
				add_action('plugins_loaded', array(&$this, 'tgwc_plugins_loaded_handler')); //plugins loaded hook		
			}

			function tgwc_plugins_loaded_handler() {
				//Runs when plugins_loaded action gets fired
				include_once('include/tranzila-gateway-woo-class.php');

				add_filter('woocommerce_payment_gateways', array(&$this, 'tgwc_init_tranzila_payment_gateway'));
				add_action('admin_notices', array( $this, 'tgwc_terminal_name_checking') );
			}

			function tgwc_if_terminal_name_provided() {

				$tranzilla     = new WC_Tranzila_Payment_Gateway();
				$terminal_name = $tranzilla->get_option('terminal_name');

				if ( !empty( $terminal_name ) ) {
					return true;
				} else {
					return false;
				}
			}

			function tgwc_terminal_name_checking() {
				if ( !$this->tgwc_if_terminal_name_provided() ) {
					$class = 'notice notice-error';
					$message = __( 'No terminal name provided. Please, <a href="admin.php?page=wc-settings&tab=checkout&section=tranzillapayment">provide terminal name</a> for using Tranzila Payment Gateway.' );

					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
				}
			}

			function tgwc_init_tranzila_payment_gateway($methods) {
				array_push($methods, 'WC_Tranzila_Payment_Gateway');
				return $methods;
			}
		}

		new WC_Tranzila_Payment_Gateway_Addon();
	}
} else {
	add_action( 'admin_notices', 'tgwc_woocoommerce_deactivated' );
}


/**
* WooCommerce Deactivated Notice
**/
if ( ! function_exists( 'tgwc_woocoommerce_deactivated' ) ) {
	function tgwc_woocoommerce_deactivated() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Tranzila Gateway %s to be installed and active.', 'woocommerce-360-image' ), '<a href="https://www.woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</p></div>';
	}
}