<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

function show_error($msg) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Ошибка</title>'
       . '<style>body{background:#0a0a0a;color:#f0f0f0;font-family:sans-serif;display:flex;'
       . 'align-items:center;justify-content:center;min-height:100vh;margin:0}'
       . '.e{text-align:center;max-width:400px;padding:24px}'
       . '.e h2{color:#c8ff00;font-size:24px;margin:0 0 16px}'
       . '.e a{color:#c8ff00}</style></head><body>'
       . '<div class="e"><h2>Ошибка оформления заказа</h2>'
       . '<p>' . htmlspecialchars($msg, ENT_QUOTES) . '</p>'
       . '<p><a href="javascript:history.back()">← Назад</a></p></div></body></html>';
    exit;
}

function post_val($key, $default) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function item_val($item, $key, $default) {
    return isset($item[$key]) ? $item[$key] : $default;
}

// --- Config ---
$config = __DIR__ . '/config.php';
if (!file_exists($config)) {
    show_error('Сервис временно недоступен. Попробуйте позже.');
}
require $config;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /', true, 302);
    exit;
}

// --- Input ---
$cart_raw = trim(post_val('cart', ''));
$name     = mb_substr(trim(post_val('name',    '')), 0, 100);
$contact  = mb_substr(trim(post_val('contact', '')), 0, 15);
$email    = mb_substr(trim(post_val('email',   '')), 0, 64);
$city     = mb_substr(trim(post_val('city',    '')), 0, 100);
$address  = mb_substr(trim(post_val('address', '')), 0, 200);

if (!$cart_raw || !$contact || !$city) {
    show_error('Не заполнены обязательные поля: телефон и город.');
}

$items = json_decode($cart_raw, true);
if (!is_array($items) || count($items) === 0) {
    show_error('Корзина пуста.');
}

// --- Total ---
$total = 0.0;
foreach ($items as $item) {
    $price = max(0.0, (float)item_val($item, 'price', 0));
    $qty   = max(1,   (int)item_val($item,   'qty',   1));
    $total += $price * $qty;
}
if ($total <= 0) {
    show_error('Сумма заказа должна быть больше нуля.');
}
$amount = number_format($total, 2, '.', '');

// --- Receipt items ---
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

// --- Build params ---
$order_id  = 'SK-' . time() . '-' . bin2hex(openssl_random_pseudo_bytes(3));
$salt      = bin2hex(openssl_random_pseudo_bytes(4));
$ts        = (string)time();
$key       = $test_mode ? $test_secret_key : $secret_key;

$params = array(
    'merchant'            => $merchant_id,
    'amount'              => $amount,
    'order_id'            => $order_id,
    'description'         => 'Заказ Science Kids',
    'success_url'         => $success_url,
    'callback_url'        => $callback_url,
    'receipt_items'       => $receipt_json,
    'unix_timestamp'      => $ts,
    'salt'                => $salt,
    'client_phone'        => $contact,
);

if ($name)      { $params['client_name']     = $name; }
if ($email)     { $params['client_email']    = $email; }
if ($email)     { $params['receipt_contact'] = $email; }
if ($test_mode) { $params['testing']         = '1'; }

// --- Signature ---
$sig_params = array_filter($params, function($v) { return $v !== '' && $v !== null; });
ksort($sig_params, SORT_STRING);
$parts = array();
foreach ($sig_params as $k => $v) {
    $parts[] = $k . '=' . base64_encode((string)$v);
}
$sig_str   = implode('&', $parts);
$inner     = strtolower(sha1($key . $sig_str));
$signature = strtolower(sha1($key . $inner));

$params['signature'] = $signature;

// --- Output: auto-submit form ---
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <title>Переход к оплате…</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:#0a0a0a;color:#f0f0f0;font-family:'IBM Plex Mono',monospace;
         display:flex;align-items:center;justify-content:center;min-height:100vh}
    .wrap{text-align:center;padding:24px}
    .spinner{width:40px;height:40px;border:3px solid #2a2a2a;border-top-color:#c8ff00;
             border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 24px}
    @keyframes spin{to{transform:rotate(360deg)}}
    p{font-size:13px;text-transform:uppercase;letter-spacing:.08em;color:#666}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="spinner"></div>
    <p>Переходим на страницу оплаты&hellip;</p>
    <form id="pay-form" method="POST" action="https://pay.modulbank.ru/pay">
<?php foreach ($params as $k => $v): ?>
      <input type="hidden" name="<?php echo htmlspecialchars($k, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string)$v, ENT_QUOTES); ?>" />
<?php endforeach; ?>
    </form>
  </div>
  <script>document.getElementById('pay-form').submit();</script>
</body>
</html>
