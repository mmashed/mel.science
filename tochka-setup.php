<?php
/**
 * Разовая настройка интеграции с Точкой.
 *
 * Открывается только по секретной ссылке:
 *   /tochka-setup.php?k=<$tochka_setup_key из config.php>
 *
 * Показывает customerCode и merchantId, которые нужно вписать в config.php,
 * и позволяет подписать сайт на уведомления об оплате.
 * После настройки файл лучше удалить.
 */
ini_set('display_errors', '0');
error_reporting(0);

$config = __DIR__ . '/config.php';
if (!file_exists($config)) { http_response_code(404); exit; }
require $config;
require __DIR__ . '/tochka.php';

// Без заданного секрета страница не существует
if (empty($tochka_setup_key) || !isset($_GET['k']) || !hash_equals((string) $tochka_setup_key, (string) $_GET['k'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

function show($title, $res) {
    echo "=== {$title} ===\n";
    if (!$res['ok']) {
        echo "ОШИБКА (HTTP {$res['code']}) " . $res['error'] . "\n";
        echo substr($res['raw'], 0, 1500) . "\n\n";
        return null;
    }
    echo json_encode($res['body'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    return $res['body'];
}

echo "Адрес API: " . tochka_base() . "\n\n";

$action = isset($_GET['do']) ? $_GET['do'] : '';

if ($action === 'subscribe') {
    // Подписка на уведомления об оплате по платёжной ссылке
    show('Create Webhook', tochka_api(
        '/webhook/v1.0/' . rawurlencode($tochka_client_id),
        'PUT',
        array(
            'webhooksList' => array('acquiringInternetPayment'),
            'url'          => $callback_url,
        )
    ));
} elseif ($action === 'pay') {
    // Пробное создание платёжной ссылки на вымышленном заказе.
    // Корзину и оформление заказа не затрагивает: проверяем только,
    // что запрос собран верно и банк его принимает.
    $res = tochka_create_payment(
        'TEST-' . time(),
        array(array('name' => 'Пробный набор', 'price' => 10.00, 'qty' => 1)),
        array('name' => 'Проверка связи', 'email' => 'test@example.com', 'phone' => '+79000000000'),
        'Проверка интеграции Science Kids'
    );
    $body = show('Create Payment Operation With Receipt', $res);
    if ($body && isset($body['Data']['paymentLink'])) {
        echo "Ссылка на оплату: " . $body['Data']['paymentLink'] . "\n";
        echo "operationId: " . $body['Data']['operationId'] . "\n";
        echo "\nВ песочнице по ссылке перейти нельзя — это нормально,\n";
        echo "оплата проверяется только на боевом слое.\n";
    }
} elseif ($action === 'pem') {
    // Открытый ключ для проверки подписи уведомлений — вписать в $tochka_webhook_pem
    $pem = tochka_webhook_pem();
    if ($pem === '') {
        echo "Ключ получить не удалось. Заполните \$tochka_jwks_url в config.php.\n";
    } else {
        echo "Вставьте это в config.php как \$tochka_webhook_pem:\n\n{$pem}\n";
    }
} elseif ($action === 'test') {
    // Банк пришлёт тестовое уведомление на $callback_url
    show('Send Webhook (тест)', tochka_api(
        '/webhook/v1.0/' . rawurlencode($tochka_client_id) . '/test_send',
        'POST',
        array('webhookType' => 'acquiringInternetPayment')
    ));
} else {
    // customerCode — в объекте с "customerType": "Business"
    show('Get Customers List', tochka_api('/open-banking/v1.0/customers'));
    // merchantId торговой точки
    show('Get Retailers', tochka_api('/acquiring/v1.0/retailers'));
    // Текущие подписки на уведомления
    show('Get Webhooks', tochka_api('/webhook/v1.0/' . rawurlencode($tochka_client_id)));

    echo "Дальше:\n";
    echo "  ?k=...&do=pay       — пробно создать платёжную ссылку (корзину не трогает)\n";
    echo "  ?k=...&do=subscribe — подписаться на уведомления об оплате\n";
    echo "  ?k=...&do=test      — попросить банк прислать тестовое уведомление\n";
    echo "  ?k=...&do=pem       — получить ключ для проверки подписи уведомлений\n";
}
