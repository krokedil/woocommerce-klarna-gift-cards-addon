<?php
/*
Plugin Name: WooCommerce Klarna Gift Cards Connector
Plugin URI: http://krokedil.com
Description: Connector for making WooCommerce - Gift Cards compatible with Klarna Checkout.
Version: 1.0
Author: Krokedil
Author URI: http://krokedil.com
*/


/**
 * Displays the gift card in KCO cart widget (if one is used).
 *
 * @since  1.0
 **/

add_action( 'kco_widget_before_cart_total', 'klarna_checkout_get_gift_cards_row_html' );

function klarna_checkout_get_gift_cards_row_html() {
	if ( function_exists( 'rpgc_order_giftcard' ) ) {
		echo rpgc_order_giftcard();
	}
}

add_action( 'kco_widget_before_cart_items', 'klarna_checkout_get_gift_cards_form' );

function klarna_checkout_get_gift_cards_form() {
	if ( function_exists( 'rpgc_checkout_form' ) ) {
		echo rpgc_checkout_form();
	}
}


/**
 * Adds gift card in cart content sent to Klarna
 *
 * @since  1.0
 */
add_filter( 'klarna_process_cart_contents', 'krokedil_klarna_process_cart_contents' );

function krokedil_klarna_process_cart_contents( $cart ) {
	// Process gift certificates
	if ( isset( WC()->session->giftcard_post ) ) {
		if ( WC()->session->giftcard_post ) {

			$giftCards = WC()->session->giftcard_post;

			$giftcard = new KODIAK_Giftcard();
			$price    = $giftcard->wpr_get_payment_amount();

			foreach ( $giftCards as $key => $giftCard ) {
				$cardNumber  = wpr_get_giftcard_number( $giftCard );
				$card_amount = wpr_get_giftcard_balance( $giftCard );
				$card_name   = __( 'Gift Card', 'rpgiftcards' );

				// Check if coupon amount exceeds order total
				/*
				if ( $order_total < $card_amount ) {
					$card_amount = $order_total;
				}
				*/
				if ( true == WC()->session->get( 'klarna_is_rest' ) ) {
					$cart[] = array(
						'type'             => 'discount',
						'reference'        => 'DISCOUNT',
						'name'             => $card_name,
						'quantity'         => 1,
						'unit_price'       => - $price * 100,
						'total_amount'     => - $price * 100,
						'tax_rate'         => 0,
						'total_tax_amount' => 0,
					);
					// $order_total = $order_total - $card_amount;
				} else {
					$cart[] = array(
						'type'       => 'discount',
						'reference'  => 'DISCOUNT',
						'name'       => $card_name,
						'quantity'   => 1,
						'unit_price' => - $price * 100,
						'tax_rate'   => 0,
					);
					// $order_total = $order_total - $card_amount;
				}
			}
		}
	}
	return $cart;
}

/**
 * Add the gift card to the WooCommerce order on completed KCO payment.
 *
 * The gift card plugin is using woocommerce_checkout_order_processed to add it to the order. It also relies on session data.
 * However, since the Klarna plugin is an iframe based checkout (where we don't actually post the WooCommerce checkout form)
 * we execute the action woocommerce_checkout_order_processed in our plugin but we do it on the server to server callback callback from Klarna.
 * This means we don't have any session data available at this point. Insted we need to trigger the adding of the gift card data to
 * the local WC order on the return to the Klarna thankyou page. This is done below.
 *
 * @since  1.0
 */

add_action( 'template_redirect', 'krokedil_rpgc_update_card' );

function krokedil_rpgc_update_card() {
	if ( isset( WC()->session->cart ) ) {
		if ( isset( $_GET['sid'] ) && 'yes' == $_GET['thankyou'] ) {
			rpgc_update_card( $_GET['sid'] );
		}
	}
}

/**
 * Recalculate order total in WC order.
 *
 * Klarna plugin is using $order->calculate_totals( false ) on the server to server push notification from Klarna upon completed purchase.
 * Since the gift card isn't added as a standard WooCommerce discount or order line we need to recalculate order totals directly after
 * the Klarna plugin has calculated totals. This is done in the function below.
 *
 * @since  1.0
 */
add_action( 'klarna_after_kco_push_notification', 'krokedil_update_totals_for_gift_card', 10, 2 );

function krokedil_update_totals_for_gift_card( $order_id, $klarna_order_id ) {
	$gift_card_payments = get_post_meta( $order_id, 'rpgc_payment', true );
	$order              = wc_get_order( $order_id );
	if ( 'klarna_checkout' == $order->payment_method && ! empty( $gift_card_payments ) ) {
		$total_gift_card_payment = 0;
		foreach ( $gift_card_payments as $gift_card_payment ) {
			$total_gift_card_payment += $gift_card_payment;
		}
		$new_total = $order->order_total - $total_gift_card_payment;
		$order->set_total( $new_total );
		$order->save();
	}
}
