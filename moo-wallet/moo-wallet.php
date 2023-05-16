<?php
/*
Plugin Name: Moo Wallet
Description: A plugin that manages user credits.
Version: 1.0
Author: Your Name
*/

add_action('rest_api_init', function () {
    register_rest_route('moo-wallet/v1', '/balance/(?P<moodle_user_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'moo_wallet_get_credits',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('moo-wallet/v1', '/debit', array(
        'methods' => 'POST',
        'callback' => 'moo_wallet_deduct_credits',
		'permission_callback' => '__return_true',
    ));

    register_rest_route('moo-wallet/v1', '/get_coupon_value', array(
        'methods' => 'POST',
        'callback' => 'moo_wallet_get_coupon_value',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('moo-wallet/v1', '/wallet_topup', array(
        'methods' => 'POST',
        'callback' => 'moo_wallet_topup',
        'permission_callback' => '__return_true',
    ));
});

function get_user_id_by_meta($meta_key, $meta_value) {
    $users = get_users(array(
        'meta_key' => $meta_key,
        'meta_value' => $meta_value,
        'fields' => 'ID',
    ));
    if (!empty($users)) {
        return $users[0];
    }
    return 'user not exist';
}
function moo_wallet_topup($request) {
    // Get the coupon code and Moodle user ID from the request.
    $amount = $request->get_param('amount');
    $moodle_user_id = $request->get_param('moodle_user_id');
    $charger = $request->get_param('charger');
    $desc = $request->get_param('description');
    $amount = (float)$amount;
    // Check if there is a correct value.
    if ($amount <= 0 || !is_numeric($amount)) {
        // Invalid value for amount.
        return array('err' => "invalid amount entered.", 'success' => 'false');
    }
    if ($desc == '') {
        $desc = 'charged by payment from LMS';
    }
    // Add credits to the Moodle user's wallet.
    $addition = moo_wallet_add_credits($moodle_user_id, $amount, $desc, $charger);
    if (!is_numeric($addition)){
        return array('err' => $addition, 'success' => 'false');
    }
    return array('success' => 'true');
}
function moo_wallet_get_coupon_value($request) {
    // Get the coupon code and Moodle user ID from the request.
    $coupon_code = $request->get_param('coupon');
    $moodle_user_id = $request->get_param('moodle_user_id');
    $instanceid= $request->get_param('instanceid');
    $charger = $request->get_param('charger');
    $apply = $request->get_param('apply');
    // Create a new WC_Coupon object with the coupon code.
    $coupon = new WC_Coupon($coupon_code);

    // Check if the coupon exists.
    if (empty($coupon)) {
        // Coupon is not valid.
        // error_log("Coupon code is not valid.");
        return array('err' => "Coupon code is not valid.");
    }
    // Check if the type of the copoun.
    $coupon_type = $coupon->get_discount_type();

    // Get the coupon value.
    $coupon_value = $coupon->get_amount();
    // error_log("Coupon Value: " . $coupon_value);
    if ($coupon_value == 0) {
        // Coupon is not valid or is not a fixed cart discount.
        // error_log("Coupon has no value.");
        return array('err' => "Coupon has no value or not exist.");
    }
    // Check if the coupon has reached its maximum usage.
    $max_usage = $coupon->get_usage_limit();
    $current_usage = $coupon->get_usage_count();
    // error_log("Max Usage: " . $max_usage);
    // error_log("Current Usage: " . $current_usage);
    if ($max_usage && $current_usage >= $max_usage) {
        // Coupon has reached its maximum usage.
        // error_log("Coupon has reached its maximum usage.");
        return array('err' => "Coupon has reached its maximum usage.");
    }

    if ($apply == 'true' || $apply === true) {
        // Mark the coupon as used.
        $coupon->set_usage_count($current_usage + 1);
        $coupon->save();
        if ($coupon_type == 'fixed_cart') {
            $desc = 'charging wallet from LMS by coupon code: '.$coupon_code;
            // Add credits to the Moodle user's wallet.
            moo_wallet_add_credits($moodle_user_id, (int)$coupon_value, $desc, $charger);
        }
    }

    // error_log("Coupon marked as used.");
    $response = ['coupon_value' => $coupon_value,
                'instanceid' => $instanceid,
                'coupon_type' => $coupon_type];
    // Return the coupon type and value.
    return $response;
}

function moo_wallet_get_credits($request) {
    $moodle_user_id = $request->get_param('moodle_user_id');
    // Retrieve the WordPress user ID from the user_meta with key 'moodle_user_id'.
    $wordpress_user_id = get_user_id_by_meta('moodle_user_id', $moodle_user_id);
    if (!$wordpress_user_id || 0 == $wordpress_user_id) {
        return 'no associated wordpress user';
    }
    // Retrieve the user's credit from the user_meta with key '_current_woo_wallet_balance'.
    // $credits_array = get_user_meta($wordpress_user_id, '_current_woo_wallet_balance', true);
	// $credits = isset($credits_array[0]) ? intval($credits_array) : 0;
	global $wpdb;
	$wallet_balance = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(
													CASE WHEN t.type = 'credit'
													THEN t.amount
													ELSE -t.amount
													END)
													as balance
													FROM {$wpdb->base_prefix}woo_wallet_transactions
													AS t
													WHERE t.user_id=%d
													AND t.deleted=0",
													$wordpress_user_id ) );
	$wallet_balance = apply_filters( 'woo_wallet_current_balance', $wallet_balance, $wordpress_user_id);
    // Return the user's credit amount.
	return (float)number_format( $wallet_balance, 2, '.', '' );
}
function moo_wallet_deduct_credits($request) {
    $moodle_user_id = $request->get_param('moodle_user_id');
    $amount = $request->get_param('amount');
	$name = $request->get_param('course');
    $charger = $request->get_param('charger');
    // Retrieve the WordPress user ID from the user_meta with key 'moodle_user_id'.
    $wordpress_user_id = get_user_id_by_meta('moodle_user_id', $moodle_user_id);
    if (!$wordpress_user_id || 0 === $wordpress_user_id) {
        return 'no associated wordpress user';
    }
    $chargerid = get_user_id_by_meta('moodle_user_id', $charger);
    // Retrieve the user's credit from the woo_wallet_transactions.
	global $wpdb;
	$balance = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(
												CASE WHEN t.type = 'credit' 
												THEN t.amount ELSE -t.amount END) 
												as balance 
												FROM {$wpdb->base_prefix}woo_wallet_transactions 
												AS t 
												WHERE t.user_id=%d 
												AND t.deleted=0",
												$wordpress_user_id ) );

	$balance -= $amount;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		"{$wpdb->base_prefix}woo_wallet_transactions",

			array(
				'blog_id'    => $GLOBALS['blog_id'],
				'user_id'    => $wordpress_user_id,
				'type'       => 'debit',
				'amount'     => $amount,
				'balance'    => $balance,
				'currency'   => get_woocommerce_currency(),
				'details'    => 'enrolment from the LMS in '.$name,
				'date'       => current_time( 'mysql' ),
				'created_by' => $chargerid,
			),
			array( '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d' )	
		);
    $transaction_id = $wpdb->insert_id;
    update_user_meta( $wordpress_user_id, '_current_woo_wallet_balance', $balance );
    clear_woo_wallet_cache( $wordpress_user_id );
    do_action( 'woo_wallet_transaction_recorded', $transaction_id, $wordpress_user_id, $amount, 'depit' );
    return $balance;
}

function moo_wallet_add_credits($moodle_user_id, $amount, $desc, $charger) {
    // Retrieve the WordPress user ID from the user_meta with key 'moodle_user_id'.
    $wordpress_user_id = get_user_id_by_meta('moodle_user_id', $moodle_user_id);
    if (!$wordpress_user_id || 0 == $wordpress_user_id) {
        return 'no associated wordpress user';
    }
    $chargerid = get_user_id_by_meta('moodle_user_id', $charger);
    // Retrieve the user's credit from the woo_wallet_transactions.
	global $wpdb;
    
	$balance = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(
												CASE WHEN t.type = 'credit' 
												THEN t.amount ELSE -t.amount END) 
												as balance 
												FROM {$wpdb->base_prefix}woo_wallet_transactions 
												AS t 
												WHERE t.user_id=%d 
												AND t.deleted=0",
												$wordpress_user_id ) );
	$balance += $amount;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		"{$wpdb->base_prefix}woo_wallet_transactions",

			array(
				'blog_id'    => $GLOBALS['blog_id'],
				'user_id'    => $wordpress_user_id,
				'type'       => 'credit',
				'amount'     => $amount,
				'balance'    => $balance,
				'currency'   => get_woocommerce_currency(),
				'details'    => $desc,
				'date'       => current_time( 'mysql' ),
				'created_by' => $chargerid,
			),
			array( '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d' )	
		);
    $transaction_id = $wpdb->insert_id;
    update_user_meta( $wordpress_user_id, '_current_woo_wallet_balance', $balance );
    clear_woo_wallet_cache( $wordpress_user_id );
    do_action( 'woo_wallet_transaction_recorded', $transaction_id, $wordpress_user_id, $amount, 'credit' );
    return $balance;
}

function moo_wallet_create_user($request) {
    $username = $request->get_param('username');
    $password = $request->get_param('password');
    $email = $request->get_param('email');
    $moodleid = $request->get_param('userid');

    $id = wp_create_user($username, $password, $email);
    if (!$id) {
        $user = get_user_by('email', $email);
    } else {
        $user = get_user_by('id', $id);
    }

    update_user_meta($user->id, 'moodle_user_id', $moodleid);
}