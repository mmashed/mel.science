<?php
ini_set('display_errors', '0');
error_reporting(0);

$config = __DIR__ . '/config.php';
if (!file_exists($config)) {
    http_response_code(500);
    exit;
}
require $config;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$data = $_POST;
if (empty($data)) {
    http_response_code(400);
    exit;
}

// Extract received signature
$received_sig = isset($data['signature']) ? $data['signature'] : '';
unset($data['signature']);

// Choose key: test vs production
$is_test = isset($data['testing']) && (string)$data['testing'] === '1';
$key     = $is_test ? $test_secret_key : $secret_key;

// Recompute signature
$sig_params = array_filter($data, function($v) { return $v !== '' && $v !== null; });
ksort($sig_params);
$parts = array();
foreach ($sig_params as $k => $v) {
    $parts[] = $k . '=' . base64_encode((string)$v);
}
$sig_str      = implode('&', $parts);
$inner        = strtolower(sha1($key . $sig_str));
$expected_sig = strtolower(sha1($key . $inner));

if (!hash_equals($expected_sig, $received_sig)) {
    http_response_code(403);
    exit;
}

$state = isset($data['state']) ? $data['state'] : '';

if ($state === 'COMPLETE') {
    $order_id = isset($data['order_id'])       ? $data['order_id']       : '';
    $amount   = isset($data['amount'])         ? $data['amount']         : '';
    $currency = isset($data['currency'])       ? $data['currency']       : 'RUB';
    $tx_id    = isset($data['transaction_id']) ? $data['transaction_id'] : '';
    $method   = isset($data['payment_method']) ? $data['payment_method'] : '';
    $phone    = isset($data['client_phone'])   ? $data['client_phone']   : '';
    $email    = isset($data['client_email'])   ? $data['client_email']   : '';

    // Read saved order snapshot (city, address, items saved by pay.php)
    $snap = array();
    $order_file = __DIR__ . '/orders/' . $order_id . '.json';
    if (file_exists($order_file)) {
        $snap = json_decode(file_get_contents($order_file), true) ?: array();
        unlink($order_file);
    }
    $snap_name    = isset($snap['name'])    ? $snap['name']    : (isset($data['client_name']) ? $data['client_name'] : '');
    $snap_phone   = isset($snap['phone'])   ? $snap['phone']   : $phone;
    $snap_email   = isset($snap['email'])   ? $snap['email']   : $email;
    $snap_city    = isset($snap['city'])    ? $snap['city']    : '';
    $snap_address = isset($snap['address']) ? $snap['address'] : '';
    $snap_items   = isset($snap['items'])   && is_array($snap['items']) ? $snap['items'] : array();
    $items_parts  = array();
    foreach ($snap_items as $it) {
        $items_parts[] = (isset($it['name']) ? $it['name'] : 'Товар') . ' x' . (isset($it['qty']) ? $it['qty'] : 1);
    }
    $items_str = implode(', ', $items_parts);

    $msg = "Оплачен заказ: {$order_id}\n"
         . "Сумма: {$amount} {$currency}\n"
         . "Имя: {$snap_name}\n"
         . "Телефон: {$snap_phone}\n"
         . ($snap_email   ? "Email: {$snap_email}\n"     : '')
         . ($snap_city    ? "Город: {$snap_city}\n"      : '')
         . ($snap_address ? "Адрес: {$snap_address}\n"   : '')
         . ($items_str    ? "Товары: {$items_str}\n"     : '')
         . "Транзакция: {$tx_id}\n"
         . "Метод: {$method}\n";

    $payload = json_encode(array(
        'access_key' => $w3f_key,
        'subject'    => "Оплата получена — Science Kids — {$order_id}",
        'name'       => 'Модульбанк уведомление',
        'message'    => $msg,
    ), JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create(array(
        'http' => array(
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ),
    ));
    @file_get_contents('https://api.web3forms.com/submit', false, $ctx);

    // Google Sheets: update status of existing row to "Оплачен"
    if (!empty($sheets_url)) {
        $sheets_payload = json_encode(array(
            'action'   => 'paid',
            'order_id' => $order_id,
        ), JSON_UNESCAPED_UNICODE);
        $sheets_ctx = stream_context_create(array('http' => array(
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $sheets_payload,
            'timeout'       => 5,
            'ignore_errors' => true,
        )));
        @file_get_contents($sheets_url, false, $sheets_ctx);
    }
}

http_response_code(200);
echo 'OK';
