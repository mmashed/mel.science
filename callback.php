<?php
ini_set('display_errors', '0');
error_reporting(0);

$config = __DIR__ . '/config.php';
if (!file_exists($config)) {
    http_response_code(500);
    exit;
}
require $config;

// Accept only POST
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
$received_sig = $data['signature'] ?? '';
unset($data['signature']);

// Choose key: test vs production
$is_test = isset($data['testing']) && (string)$data['testing'] === '1';
$key     = $is_test ? $test_secret_key : $secret_key;

// Recompute signature from callback fields
$sig_params = array_filter($data, function($v) { return $v !== '' && $v !== null; });
ksort($sig_params);
$parts = [];
foreach ($sig_params as $k => $v) {
    $parts[] = $k . '=' . base64_encode((string)$v);
}
$sig_str      = implode('&', $parts);
$inner        = strtolower(sha1($key . $sig_str));
$expected_sig = strtolower(sha1($key . $inner));

// Reject invalid signatures
if (!hash_equals($expected_sig, $received_sig)) {
    http_response_code(403);
    exit;
}

// Process confirmed payment
$state = $data['state'] ?? '';

if ($state === 'COMPLETE') {
    $order_id  = $data['order_id']        ?? '';
    $amount    = $data['amount']           ?? '';
    $currency  = $data['currency']         ?? 'RUB';
    $tx_id     = $data['transaction_id']   ?? '';
    $method    = $data['payment_method']   ?? '';
    $phone     = $data['client_phone']     ?? '';
    $email     = $data['client_email']     ?? '';

    $msg = "Оплачен заказ: {$order_id}\n"
         . "Сумма: {$amount} {$currency}\n"
         . "Транзакция: {$tx_id}\n"
         . "Метод оплаты: {$method}\n"
         . ($phone ? "Телефон: {$phone}\n" : '')
         . ($email ? "Email: {$email}\n"   : '');

    $payload = json_encode([
        'access_key' => $w3f_key,
        'subject'    => "Оплата получена — Science Kids — {$order_id}",
        'name'       => 'Модульбанк уведомление',
        'message'    => $msg,
    ], JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
    ]);
    @file_get_contents('https://api.web3forms.com/submit', false, $ctx);
}

http_response_code(200);
echo 'OK';
