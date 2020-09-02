<?php
/**
 * Twispay Custom Processor Page
 *
 * Here the Twispay Form is created and processed to the gateway
 *
 * @package  Twispay/Admin
 * @category Admin
 * @author   Twispay
 * @version  1.0.8
 */

?>


<style>
    .loader {
        margin: 15% auto 0;
        border: 14px solid #f3f3f3;
        border-top: 14px solid #3498db;
        border-radius: 50%;
        width: 110px;
        height: 110px;
        animation: spin 1.1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
</style>

<div class="loader"></div>

<script>window.history.replaceState('twispay', 'Twispay', '../twispay.php');</script>


<?php
$parse_uri = explode('wp-content', $_SERVER['SCRIPT_FILENAME']);
require_once($parse_uri[0] . 'wp-load.php');

/* Require the "Twispay_TW_Helper_Notify" class. */
require_once(TWISPAY_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Twispay_TW_Helper_Notify.php');


/* Load languages. */
$lang = explode('-', get_bloginfo('language'))[0];
if (file_exists(TWISPAY_PLUGIN_DIR . 'lang/' . $lang . '/lang.php')) {
    require(TWISPAY_PLUGIN_DIR . 'lang/' . $lang . '/lang.php');
} else {
    require(TWISPAY_PLUGIN_DIR . 'lang/en/lang.php');
}


/* Exit if no order is placed */
if (empty($_GET['order_id'])) {
    echo '<style>.loader {display: none;}</style>';
    die($tw_lang['twispay_processor_error_general']);
}

/* Extract the WooCommerce order. */
$order = wc_get_order($_GET['order_id']);

if (false === $order) {
    echo '<style>.loader {display: none;}</style>';
    die($tw_lang['twispay_processor_error_general']);
}

/* Get all information for the Twispay Payment form. */
$data = $order->get_data();

/* Get configuration from database. */
global $wpdb;
$configuration = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "twispay_tw_configuration");

/* Get the Site ID and the Private Key. */
$siteID = '';
$secretKey = '';
if ($configuration) {
    if (1 == $configuration->live_mode) {
        $siteID = $configuration->live_id;
        $secretKey = $configuration->live_key;
    } else {
        if (0 == $configuration->live_mode) {
            $siteID = $configuration->staging_id;
            $secretKey = $configuration->staging_key;
        } else {
            echo '<style>.loader {display: none;}</style>';
            die($tw_lang['twispay_processor_error_missing_configuration']);
        }
    }
}

/** Save the timestamp of this payment. */
$timestamp = date('YmdHis');


/* Extract the customer details. */
$customer = [
    'identifier' => 'p_wo_' . ((0 == $data['customer_id']) ? ($_GET['order_id']) : ($data['customer_id'])) . '_' . $timestamp,
    'firstName' => $data['billing']['first_name'] ?: $data['shipping']['first_name'],
    'lastName' => $data['billing']['last_name'] ?: $data['shipping']['last_name'],
    'country' => $data['billing']['country'] ?: $data['shipping']['country'],
    'city' => $data['billing']['city'] ?: $data['shipping']['city'],
    'address' => $data['billing']['address_1'] ?: $data['shipping']['address_1'],
    'zipCode' => $data['billing']['postcode'] ?: $data['shipping']['postcode'],
    'phone' => (('+' == $data['billing']['phone'][0]) ? ('+') : ('')) . preg_replace('/([^0-9]*)+/', '', $data['billing']['phone']),
    'email' => $data['billing']['email'],
];

/* Extract the items details. */
$items = [];
foreach ($order->get_items() as $item) {
    $items[] = ['item' => $item['name']
        , 'units' => $item['quantity']
        , 'unitPrice' => number_format(number_format(( float ) $item['subtotal'], 2) / number_format(( float ) $item['quantity'], 2), 2)
        /* , 'type' => '' */
        /* , 'code' => '' */
        /* , 'vatPercent' => '' */
        /* , 'itemDescription' => '' */
    ];
}

/* Calculate the backUrl through which the server will pvide the status of the order. */
$backUrl = get_permalink(get_page_by_path('twispay-confirmation'));
$backUrl .= (false == strpos($backUrl, '?')) ? ('?secure_key=' . $data['cart_hash']) : ('&secure_key=' . $data['cart_hash']);

/* Build the data object to be posted to Twispay. */
$orderData = [
    'siteId' => $siteID,
    'customer' => $customer,
    'order' => [
        'orderId' => $_GET['order_id'] . '_' . $timestamp,
        'type' => 'purchase',
        'orderType' => 'purchase',
        'amount' => $data['total'],
        'currency' => $data['currency'],
//        'items' => $items,
    ],
    'cardTransactionMode' => 'authAndCapture',
    'invoiceEmail' => '',
    'backUrl' => $backUrl,
];


/* Build the HTML form to be posted to Twispay. */
$base64JsonRequest = Twispay_TW_Helper_Notify::getBase64JsonRequest($orderData);
$base64Checksum = Twispay_TW_Helper_Notify::getBase64Checksum($orderData, $secretKey);
$hostName = ($configuration && (1 == $configuration->live_mode)) ? ('https://secure.twispay.com' . '?lang=' . $lang) : ('https://secure-stage.twispay.com' . '?lang=' . $lang);
?>

<form action="<?= $hostName; ?>" method="POST" accept-charset="UTF-8" id="twispay_payment_form">
    <input type="hidden" name="jsonRequest" value="<?= $base64JsonRequest; ?>">
    <input type="hidden" name="checksum" value="<?= $base64Checksum; ?>">
</form>

<script>document.getElementById('twispay_payment_form').submit();</script>

