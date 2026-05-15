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
    $order_id = isset($data['order_id'])      ? $data['order_id']      : '';
    $amount   = isset($data['amount'])        ? $data['amount']        : '';
    $currency = isset($data['currency'])      ? $data['currency']      : 'RUB';
    $tx_id    = isset($data['transaction_id'])? $data['transaction_id']: '';
    $method   = isset($data['payment_method'])? $data['payment_method']: '';
    $phone    = isset($data['client_phone'])  ? $data['client_phone']  : '';
    $email    = isset($data['client_email'])  ? $data['client_email']  : '';

    $msg = "Оплачен заказ: {$order_id}\n"
         . "Сумма: {$amount} {$currency}\n"
         . "Транзакция: {$tx_id}\n"
         . "Метод оплаты: {$method}\n"
         . ($phone ? "Телефон: {$phone}\n" : '')
         . ($email ? "Email: {$email}\n"   : '');

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
}

http_response_code(200);
echo 'OK';
