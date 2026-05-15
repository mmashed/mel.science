<?php
// ВРЕМЕННЫЙ ФАЙЛ ДЛЯ ОТЛАДКИ — удалить после исправления подписи

function post_val($key, $default) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}
function item_val($item, $key, $default) {
    return isset($item[$key]) ? $item[$key] : $default;
}

include __DIR__ . '/config.php';

echo '<pre>';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Откройте эту страницу, отправив заказ с сайта, но с action=/pay_debug.php\n";
    echo "Или откройте index.html и временно смените action формы на /pay_debug.php\n";
    echo '</pre>';
    exit;
}

$cart_raw = trim(post_val('cart', ''));
$name     = mb_substr(trim(post_val('name',    '')), 0, 100);
$contact  = mb_substr(trim(post_val('contact', '')), 0, 15);
$email    = mb_substr(trim(post_val('email',   '')), 0, 64);
$city     = mb_substr(trim(post_val('city',    '')), 0, 100);

$items = json_decode($cart_raw, true);
$total = 0.0;
foreach ($items as $item) {
    $price = max(0.0, (float)item_val($item, 'price', 0));
    $qty   = max(1,   (int)item_val($item,   'qty',   1));
    $total += $price * $qty;
}
$amount = number_format($total, 2, '.', '');

$receipt = array();
foreach ($items as $item) {
    $price = max(0.0, (float)item_val($item, 'price', 0));
    $qty   = max(1,   (int)item_val($item,   'qty',   1));
    $receipt[] = array(
        'name'           => mb_substr((string)item_val($item, 'name', 'Товар'), 0, 128),
        'quantity'       => $qty,
        'price'          => round($price, 2),
        'sno'            => $sno,
        'vat'            => 'none',
        'payment_method' => 'full_payment',
        'payment_object' => 'commodity',
    );
}
$receipt_json = json_encode($receipt, JSON_UNESCAPED_UNICODE);

$order_id  = 'SK-' . time() . '-' . bin2hex(openssl_random_pseudo_bytes(3));
$salt      = bin2hex(openssl_random_pseudo_bytes(4));
$ts        = (string)time();
$key       = $test_mode ? $test_secret_key : $secret_key;

$params = array(
    'merchant'       => $merchant_id,
    'amount'         => $amount,
    'order_id'       => $order_id,
    'description'    => 'Заказ Science Kids',
    'success_url'    => $success_url,
    'callback_url'   => $callback_url,
    'receipt_items'  => $receipt_json,
    'unix_timestamp' => $ts,
    'salt'           => $salt,
    'client_phone'   => $contact,
);
if ($name)      { $params['client_name']     = $name; }
if ($email)     { $params['client_email']    = $email; }
if ($email)     { $params['receipt_contact'] = $email; }
if ($test_mode) { $params['testing']         = '1'; }

$sig_params = array_filter($params, function($v) { return $v !== '' && $v !== null; });
unset($sig_params['callback_url']);
ksort($sig_params, SORT_STRING);

echo "=== PARAMS INCLUDED IN SIGNATURE ===\n";
$parts = array();
foreach ($sig_params as $k => $v) {
    $b64 = base64_encode((string)$v);
    echo $k . " = " . $b64 . "\n";
    $parts[] = $k . '=' . $b64;
}

$sig_str   = implode('&', $parts);
$inner     = strtolower(sha1($key . $sig_str));
$signature = strtolower(sha1($key . $inner));

echo "\n=== SIGNATURE STRING (first 200 chars) ===\n";
echo substr($sig_str, 0, 200) . "...\n";
echo "\n=== COMPUTED SIGNATURE ===\n";
echo $signature . "\n";
echo "\n=== KEY INFO ===\n";
echo 'test_mode: ' . ($test_mode ? 'true (using test_secret_key)' : 'false (using secret_key)') . "\n";
echo 'key length: ' . strlen($key) . "\n";
echo 'key first 4 chars: ' . substr($key, 0, 4) . "\n";
echo '</pre>';
