<?php
/*
Plugin Name: Moo Wallet
Description: A plugin that connects the users balances in Tera wallet in wordpress website with wallet ballance in enrol_wallet in moodle.
Version: 2.2
Author: Mohamed Farouk
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// Register rest api callbacks.
add_action('rest_api_init', function () {
    register_rest_route('moo-wallet/v1', '/balance', [
        'methods' => 'POST',
        'callback' => 'moo_wallet_get_credits',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('moo-wallet/v1', '/debit', [
        'methods' => 'POST',
        'callback' => 'moo_wallet_deduct_credits',
		'permission_callback' => '__return_true',
    ]);

    register_rest_route('moo-wallet/v1', '/get_coupon_value', [
        'methods' => 'POST',
        'callback' => 'moo_wallet_get_coupon_value',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('moo-wallet/v1', '/wallet_topup', [
        'methods' => 'POST',
        'callback' => 'moo_wallet_topup',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('moo-wallet/v1', '/create_user', [
        'methods' => 'POST',
        'callback' => 'moo_wallet_create_user',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Get wordpress user id by moodle user id.
 * @param int $moodle_user_id the id of user in moodle.
 * @return int|false the user->ID or false if not found.
 */
function moo_wallet_userid_by_moodleid($moodle_user_id) {
    $users = get_users([
        'meta_key' => 'moodle_user_id',
        'meta_value' => $moodle_user_id,
        'fields' => 'ID',
    ]);
    if (!empty($users)) {
        return $users[0];
    }
    return false;
}


/**
 * Callback function from the request from moodle website to credit the wallet.
 * @param mixed $request the request data (encrypted)
 * @return array
 */
function moo_wallet_topup($request) {
    $encdata = $request->get_param('encdata');
    $data = moo_wallet_decrypt_data($encdata);
    // Get the coupon code and Moodle user ID from the request.
    $amount = $data['amount'];
    $moodle_user_id = $data['moodle_user_id'];
    $charger = $data['charger'];
    $desc = $data['description'];
    $amount = (float)$amount;
    // Check if there is a correct value.
    if ($amount <= 0 || !is_numeric($amount)) {
        // Invalid value for amount.
        return ['err' => "invalid amount entered.", 'success' => 'false'];
    }

    if ($desc == '') {
        $desc = 'charged by payment from LMS';
    }

    // Add credits to the Moodle user's wallet.
    $addition = moo_wallet_add_credits($moodle_user_id, $amount, $desc, $charger);
    if (!is_numeric($addition)){
        return ['err' => $addition, 'success' => 'false'];
    }

    return ['success' => 'true'];
}
/**
 * Get the value of a coupon, its type and mark it used if applied.
 * @param mixed $request
 * @return array|string array of coupon data or string in case of error.
 */
function moo_wallet_get_coupon_value($request) {
    $encdata = $request->get_param('encdata');
    $data = moo_wallet_decrypt_data($encdata);
    if (empty($data)) {
        return 'Key not match';
    }
    // Get the coupon code and Moodle user ID from the request.
    $coupon_code = $data['coupon'];
    $instanceid= $data['instanceid'];
    $apply = $data['apply'];
    if ($apply == 'true' || $apply == 1 || $apply == true || $apply == 'yes') {
        $apply = true;
    } else {
        $apply = false;
    }
    // Create a new WC_Coupon object with the coupon code.
    $coupon = new WC_Coupon($coupon_code);

    // Check if the coupon exists.
    if (empty($coupon)) {
        // Coupon is not valid.
        return ['err' => "Coupon code is not valid."];
    }
    // Check if the type of the coupon.
    $coupon_type = $coupon->get_discount_type();

    // Get the coupon value.
    $coupon_value = $coupon->get_amount();
    if ($coupon_value == 0) {
        // Coupon is not valid or is not a fixed cart discount.
        return ['err' => "Coupon has no value or not exist."];
    }
    // Check if the coupon has reached its maximum usage.
    $max_usage = $coupon->get_usage_limit();
    $current_usage = $coupon->get_usage_count();
    if ($max_usage && $current_usage >= $max_usage) {
        // Coupon has reached its maximum usage.
        // error_log("Coupon has reached its maximum usage.");
        return ['err' => "Coupon has reached its maximum usage."];
    }

    if ($apply) {
        // Mark the coupon as used.
        $coupon->set_usage_count($current_usage + 1);
        $coupon->save();
        // if ($coupon_type == 'fixed_cart') {
        //     $desc = 'charging wallet from LMS by coupon code: '.$coupon_code;
        //     // Add credits to the Moodle user's wallet.
        //     moo_wallet_add_credits($moodle_user_id, (int)$coupon_value, $desc, $charger);
        // }
    }

    $response = ['coupon_value' => $coupon_value,
                'instanceid' => $instanceid,
                'coupon_type' => $coupon_type];
    // Return the coupon type and value.
    return $response;
}

/**
 * Get the balance of a certain user.
 * @param mixed $request
 * @return float|string return balance or error.
 */
function moo_wallet_get_credits($request) {
    $encdata = $request->get_param('encdata');
    $data = moo_wallet_decrypt_data($encdata);
    if (empty($data)) {
        return 'Key not match';
    }
    $moodle_user_id = $data['moodle_user_id'];
    // Retrieve the WordPress user ID from the user_meta with key 'moodle_user_id'.
    $wordpress_user_id = moo_wallet_userid_by_moodleid($moodle_user_id);
    if (empty($wordpress_user_id) || !is_numeric($wordpress_user_id)) {
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
/**
 * Deduct credit from user's wallet
 * @param mixed $request
 * @return int|string returns the new balance or error string on failure.
 */
function moo_wallet_deduct_credits($request) {
    $encdata = $request->get_param('encdata');
    $data = moo_wallet_decrypt_data($encdata);
    if (empty($data)) {
        return 'Key not match';
    }
    $moodle_user_id = $data['moodle_user_id'];
    $amount = $data['amount'];
	$name = $data['course'];
    $charger = $data['charger'];
    // Retrieve the WordPress user ID from the user_meta with key 'moodle_user_id'.
    $wordpress_user_id = moo_wallet_userid_by_moodleid($moodle_user_id);
    if (empty($wordpress_user_id) || !is_numeric($wordpress_user_id)) {
        return 'no associated wordpress user';
    }
    $chargerid = moo_wallet_userid_by_moodleid($charger);
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

			[
				'blog_id'    => $GLOBALS['blog_id'],
				'user_id'    => $wordpress_user_id,
				'type'       => 'debit',
				'amount'     => $amount,
				'balance'    => $balance,
				'currency'   => get_woocommerce_currency(),
				'details'    => 'enrolment from the LMS in '.$name,
				'date'       => current_time( 'mysql' ),
				'created_by' => $chargerid,
			],
			[ '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d' ]	
		);
    $transaction_id = $wpdb->insert_id;
    update_user_meta( $wordpress_user_id, '_current_woo_wallet_balance', $balance );
    clear_woo_wallet_cache( $wordpress_user_id );
    do_action( 'woo_wallet_transaction_recorded', $transaction_id, $wordpress_user_id, $amount, 'depit' );
    return $balance;
}

/**
 * Called to add credit to user's wallet.
 * @param int $moodle_user_id id of user in moodle
 * @param float $amount amount to add
 * @param string $desc description for the reason of charging
 * @param int $charger the moodle id of the user who add the credit
 * @return float|string return new balance or error string.
 */
function moo_wallet_add_credits($moodle_user_id, $amount, $desc, $charger) {
    // Retrieve the WordPress user ID from the user_meta with key 'moodle_user_id'.
    $wordpress_user_id = moo_wallet_userid_by_moodleid($moodle_user_id);
    if (empty($wordpress_user_id) || !is_numeric($wordpress_user_id)) {
        return 'no associated wordpress user';
    }
    $chargerid = moo_wallet_userid_by_moodleid($charger);
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

			[
				'blog_id'    => $GLOBALS['blog_id'],
				'user_id'    => $wordpress_user_id,
				'type'       => 'credit',
				'amount'     => $amount,
				'balance'    => $balance,
				'currency'   => get_woocommerce_currency(),
				'details'    => $desc,
				'date'       => current_time( 'mysql' ),
				'created_by' => $chargerid,
			],
			[ '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d' ]	
		);
    $transaction_id = $wpdb->insert_id;
    update_user_meta( $wordpress_user_id, '_current_woo_wallet_balance', $balance );
    clear_woo_wallet_cache( $wordpress_user_id );
    do_action( 'woo_wallet_transaction_recorded', $transaction_id, $wordpress_user_id, $amount, 'credit' );
    return $balance;
}

/**
 * Creating user from moodle in wordpress.
 * @param mixed $request
 * @return bool|int|string
 */
function moo_wallet_create_user($request) {
    $encdata = $request->get_param('encdata');
    $data = moo_wallet_decrypt_data($encdata);
    if (empty($data)) {
        return 'Key not match';
    }

    $username = $data['username'];
    $password = $data['password'];
    $email = $data['email'];
    $moodleid = $data['moodle_user_id'];

    $id = moo_wallet_create_user_local($username, $password, $email, $moodleid);

    return $id;
}

/**
 * Creating or update user from moodle in wordpress.
 * @param string $username
 * @param string $password
 * @param string $email
 * @param int $moodleid
 * @return bool|int|string
 */
function moo_wallet_create_user_local($username, $password, $email, $moodleid) {

    $user1 = get_user_by('email', $email);
    $user2 = get_user_by('username', $username);
    $userid = moo_wallet_userid_by_moodleid($moodleid);
    if (!empty($userid)) {
        $user3 = get_user_by('id', $userid);
    }

    if (empty($user1) && empty($user2) && empty($user3)) {
        $id = wp_create_user($username, $password, $email);
    } else {
        if (!empty($user1) && empty($user2) && empty($user3)) {
            $user = $user1;
        } else if (empty($user1) && !empty($user2) && empty($user3)) {
            $user = $user2;
        } else if (empty($user1) && empty($user2) && !empty($user3)) {
            $user = $user3;
        } else if (!empty($user1) && $user1 === $user2 && empty($user3)) {
            $user = $user1;
        } else if (!empty($user3) && $user1 === $user2 && $user2 === $user3) {
            $user = $user3;
        } else {
            return 'Multiple users existed.';
        }

        $userdata = [
            'id' => $user->ID,
            'username' => $username,
            'password' => $password,
            'email' => $email,
        ];
        wp_update_user($userdata);
    }

    if (!update_user_meta($id, 'moodle_user_id', $moodleid)) {
        return 'Cannot update user meta.';
    }

    return $id;
}

/**
 * Used to login or logout the user from wordpress website when the same action done on moodle.
 * @return void
 */
function moo_wallet_login_logout_user() {
    $encdata = $_GET['encdata'];
    $moodleurl = $_GET['moodleurl'];

    $data = moo_wallet_decrypt_data($encdata);
    if (empty($data)) {
        moo_wallet_debug('Key not match');
        wp_redirect($moodleurl);
        exit;
    }

    $moodleid = $data['moodle_user_id'];
    $method = $data['method'];
    $url = $data['url'];

    if (empty($url)) {
        $url = $moodleurl;
    }

    $wordpress_user_id = moo_wallet_userid_by_moodleid($moodleid);

    if (empty($wordpress_user_id)) {
        $username = $data['username'];
        $email = $data['email'];
        $password = wp_generate_password();
        $wordpress_user_id = moo_wallet_create_user_local($username, $password, $email, $moodleid);
    }

    if (!empty($wordpress_user_id)) {
        // Login the user.
        if ($method == 'login') {
            if (is_user_logged_in()) {
                if (get_current_user_id() != $wordpress_user_id) {
                    // Logout the user because it is not the same user.
                    wp_destroy_current_session();
                    wp_clear_auth_cookie();
                    wp_set_current_user(0);
                }
            }

            $user = get_userdata($wordpress_user_id);

            if (!$user) {
                wp_redirect($moodleurl);
            }

            wp_set_auth_cookie($wordpress_user_id, true);
            wp_set_current_user($wordpress_user_id, $user->user_login);
            // do_action('wp_login', $user->user_login, $user);

            wp_redirect($url);
            exit;
        // Logout the user.
        } else if ($method == 'logout') {
            wp_logout();
            wp_clear_auth_cookie();
            wp_redirect($url);
            do_action('wp_logout', $wordpress_user_id);
            exit;
        } else { // Invalid method.
            wp_redirect($moodleurl);
            exit;
        }
    } else {
        wp_redirect($moodleurl);
        // User not found.
        exit;
    }
}

if (isset($_GET['encdata']) && isset($_GET['moodleurl'])) {
    add_action('template_redirect', 'moo_wallet_login_logout_user');
}

function moo_wallet_debug($msg) {
    $time = date('F j, Y h:i:s a');
    $message = "($time): $msg \n";
    error_log($message, 3, __DIR__.'/debug.log');
}
/**
 * Decrypt the data came from moodle request.
 * @param mixed $encrypteddata
 * @return array<string>|null
 */
function moo_wallet_decrypt_data($encrypteddata) {
    $secretkey = get_option( 'moo_wallet_secret_key' );
    $data = str_replace( [ '-', '_' ], [ '+', '/' ], $encrypteddata );
    $mod4 = strlen( $data ) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }

    $crypttext = base64_decode($data);

    if (preg_match("/^(.*)::(.*)$/", $crypttext, $regs)) {
        list(, $crypted_token, $enc_iv) = $regs;
        $enc_method = 'AES-128-CTR';
        $enc_key = openssl_digest( $secretkey, 'SHA256', true );
        $decrypted_data = openssl_decrypt($crypted_token, $enc_method, $enc_key, 0, hex2bin($enc_iv));
    }

    $decrypted_args = trim($decrypted_data);
    $list = explode('&', str_replace('&amp;', '&', $decrypted_args));
    $output = [];
    foreach ($list as $pair) {
        $item = explode('=', $pair);
        if ($key = strtolower($item[0])) {
            $output[$key] = urldecode($item[1]);
        }
    }
    if ($output['sk'] != $secretkey) {
        return null;
    }
    return $output;
}

// Add a new item to the WordPress admin menu
add_action( 'admin_menu', 'moo_wallet_add_menu_page' );
function moo_wallet_add_menu_page() {
    add_submenu_page(
        'options-general.php',
        'MooWallet Settings',
        'MooWallet',
        'manage_options',
        'moo-wallet-settings',
        'moo_wallet_render_settings_page'
    );
}

// Render the HTML for settings page
function moo_wallet_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'moo-wallet-settings' );
            do_settings_sections( 'moo-wallet-settings' );
            submit_button( 'Save Settings' );
            ?>
        </form>
    </div>
    <?php
}

// Register the plugin's settings with the WordPress Settings API
add_action( 'admin_init', 'moo_wallet_register_settings' );
function moo_wallet_register_settings() {
    register_setting(
        'moo-wallet-settings',
        'moo_wallet_secret_key',
        [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]
    );

    add_settings_section(
        'moo-wallet-section',
        'MooWallet Settings',
        'moo_wallet_section_callback',
        'moo-wallet-settings'
    );

    add_settings_field(
        'moo-wallet-field',
        'Secret Key',
        'moo_wallet_field_callback',
        'moo-wallet-settings',
        'moo-wallet-section'
    );
}

// Add a callback function for settings section
function moo_wallet_section_callback() {
    // Nothing to do.
}

// Add a callback function for settings field
function moo_wallet_field_callback() {
    $value = get_option( 'moo_wallet_secret_key' );
    ?>
    <input type="text" name="moo_wallet_secret_key" value="<?php echo esc_attr( $value ); ?>">
    <?php
}