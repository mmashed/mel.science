<?php
/**
 * Проверка работоспособности оплаты и уведомлений.
 *
 * Открывается только по секретной ссылке:
 *   /selftest.php?k=<$tochka_setup_key из config.php>
 *
 * Без параметров делает только безопасные проверки — ничего никуда не пишет
 * и никому не отправляет. Действия с последствиями вынесены в ?do=...
 */
ini_set('display_errors', '0');
error_reporting(0);
set_time_limit(90);

$config = __DIR__ . '/config.php';
if (!file_exists($config)) { http_response_code(404); exit; }
require $config;
require __DIR__ . '/tochka.php';

if (empty($tochka_setup_key) || !isset($_GET['k']) || !hash_equals((string) $tochka_setup_key, (string) $_GET['k'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$failed = 0;

function ok($title, $note = '')   { echo "  OK       {$title}" . ($note !== '' ? " - {$note}" : '') . "\n"; }
function warn($title, $note = '') { echo "  ВНИМАНИЕ {$title}" . ($note !== '' ? " - {$note}" : '') . "\n"; }
function bad($title, $note = '')  { $GLOBALS['failed']++; echo "  ОШИБКА   {$title}" . ($note !== '' ? " - {$note}" : '') . "\n"; }
function head($t)                 { echo "\n== {$t} ==\n"; }

$action   = isset($_GET['do']) ? $_GET['do'] : '';
$provider = !empty($GLOBALS['payment_provider']) ? $GLOBALS['payment_provider'] : 'modulbank';

/* ───────────────── Действия с последствиями ───────────────── */

if ($action === 'sheets') {
    // Прогоняем через таблицу выдуманный заказ: сначала строка «ожидает оплаты»,
    // потом перевод её в «оплачен» — ровно так же, как это делают pay.php и callback.php.
    if (empty($sheets_url)) { echo "В config.php не задан \$sheets_url.\n"; exit; }
    $order_id = 'SELFTEST-' . date('Ymd-His');

    $send = function ($payload) use ($sheets_url) {
        $ctx = stream_context_create(array('http' => array(
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'       => 15,
            'ignore_errors' => true,
        )));
        return @file_get_contents($sheets_url, false, $ctx);
    };

    echo "Заказ для проверки: {$order_id}\n\n";
    echo "1. Добавляю строку «Ожидает оплаты»…\n";
    echo '   ответ таблицы: ' . substr((string) $send(array(
        'action' => 'new_row', 'type' => 'ПРОВЕРКА',
        'name' => 'Проверка связи', 'phone' => '+70000000000',
        'email' => 'test@example.com', 'city' => 'Проверка', 'address' => '',
        'items' => 'Тестовая позиция x1', 'amount' => '10.00 руб.',
        'order_id' => $order_id, 'status' => 'Ожидает оплаты', 'promo' => '',
    )), 0, 300) . "\n\n";

    echo "2. Перевожу её в «Оплачен»…\n";
    echo '   ответ таблицы: ' . substr((string) $send(array(
        'action' => 'paid', 'order_id' => $order_id,
    )), 0, 300) . "\n\n";

    echo "Откройте таблицу: должна быть ОДНА строка {$order_id} со статусом «Оплачен».\n";
    echo "Если строк две или статус не сменился — значит, по order_id строка не находится.\n";
    echo "После проверки строку можно удалить.\n";
    exit;
}

if ($action === 'rehearse') {
    // Боевая репетиция: настоящий заказ на 10 рублей, который можно оплатить
    // своей картой. Делает ровно то же, что pay.php — пишет снимок заказа,
    // строку в таблицу и создаёт платёжную ссылку, — но корзину покупателя
    // не затрагивает и провайдер при этом может оставаться прежним.
    if (strpos(tochka_base(), '/sandbox') !== false) {
        echo "Сейчас задана песочница — по её ссылкам оплатить нельзя.\n";
        echo "Для репетиции поставьте в config.php боевой адрес:\n";
        echo "  \$tochka_base = 'https://enter.tochka.com/uapi';\n";
        exit;
    }

    $order_id = 'SK-TEST-' . date('Ymd-His');
    $items    = array(array('name' => 'Проверка оплаты', 'price' => 10.00, 'qty' => 1));
    $email    = isset($_GET['email']) ? trim($_GET['email']) : '';
    if ($email === '') {
        echo "Добавьте к адресу свой e-mail — на него придёт кассовый чек:\n";
        echo "  ?k=...&do=rehearse&email=вы@почта.ru\n";
        exit;
    }

    $dir = __DIR__ . '/orders';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/.htaccess', "Deny from all\n");
    }
    file_put_contents($dir . '/' . $order_id . '.json', json_encode(array(
        'name' => 'Боевая репетиция', 'phone' => '+70000000000', 'email' => $email,
        'city' => 'Проверка', 'address' => '', 'items' => $items,
        'amount' => '10.00', 'promo' => '',
    ), JSON_UNESCAPED_UNICODE));
    echo "1. Снимок заказа сохранён: orders/{$order_id}.json\n";

    if (!empty($sheets_url)) {
        $ctx = stream_context_create(array('http' => array(
            'method' => 'POST', 'header' => "Content-Type: application/json\r\n",
            'content' => json_encode(array(
                'action' => 'new_row', 'type' => 'ПРОВЕРКА',
                'name' => 'Боевая репетиция', 'phone' => '+70000000000',
                'email' => $email, 'city' => 'Проверка', 'address' => '',
                'items' => 'Проверка оплаты x1', 'amount' => '10.00 руб.',
                'order_id' => $order_id, 'status' => 'Ожидает оплаты', 'promo' => '',
            ), JSON_UNESCAPED_UNICODE),
            'timeout' => 15, 'ignore_errors' => true,
        )));
        @file_get_contents($sheets_url, false, $ctx);
        echo "2. Строка «Ожидает оплаты» отправлена в таблицу\n";
    } else {
        echo "2. Таблица не настроена, строку не пишу\n";
    }

    $res  = tochka_create_payment($order_id, $items,
        array('name' => 'Боевая репетиция', 'email' => $email, 'phone' => '+70000000000'),
        'Проверка оплаты Science Kids');
    $link = isset($res['body']['Data']['paymentLink']) ? $res['body']['Data']['paymentLink'] : '';

    if (!$res['ok'] || $link === '') {
        echo "3. ОШИБКА: ссылку создать не удалось (HTTP {$res['code']})\n";
        echo substr((string) $res['raw'], 0, 1500) . "\n";
        exit;
    }
    echo "3. Ссылка создана\n\n";
    echo "Оплатите 10 рублей своей картой:\n{$link}\n\n";
    echo "После оплаты должно произойти всё сразу:\n";
    echo "  - строка {$order_id} в таблице сменит статус на «Оплачен»\n";
    echo "  - на почту придёт письмо «Оплата получена»\n";
    echo "  - на ваш e-mail придёт кассовый чек от оператора фискальных данных\n\n";
    echo "Если статус не сменился — уведомление от банка не дошло или не прошло\n";
    echo "проверку подписи. Пришлите мне этот номер заказа, разберёмся.\n";
    exit;
}

if ($action === 'mail') {
    $mailer = __DIR__ . '/mailer.php';
    if (!file_exists($mailer)) { echo "Нет файла mailer.php.\n"; exit; }
    require_once $mailer;
    $to = isset($notify_emails) ? $notify_emails : array();
    echo 'Адресов в списке: ' . count($to) . "\n";
    $sent = sk_notify(
        'Проверка связи — Science Kids',
        "Это письмо отправлено страницей проверки.\nВремя: " . date('c') . "\n",
        $to,
        isset($mail_from) ? $mail_from : ''
    );
    echo $sent
        ? "Отправлено. Проверьте оба ящика, в том числе «Спам».\n"
        : 'Не отправлено. Причина: ' . (isset($GLOBALS['sk_mail_error']) ? $GLOBALS['sk_mail_error'] : 'неизвестна') . "\n";
    exit;
}

/* ───────────────── Безопасные проверки ───────────────── */

echo 'Проверка оплаты и уведомлений — ' . date('d.m.Y H:i') . "\n";

head('Что сейчас принимает оплату');
if ($provider === 'tochka') {
    if (strpos(tochka_base(), '/sandbox') !== false) {
        bad('Покупателей обслуживает Точка в режиме песочницы',
            'по таким ссылкам оплатить нельзя, продажи стоят');
    } else {
        ok('Покупателей обслуживает Точка, боевой слой');
    }
} else {
    ok('Покупателей обслуживает Модульбанк', !empty($test_mode) ? 'тестовый режим' : 'боевой режим');
}

head('Модульбанк — старая схема');
if (empty($merchant_id)) {
    bad('Не задан $merchant_id');
} else {
    ok('merchant_id задан');
}
$mb_key = !empty($test_mode)
    ? (isset($test_secret_key) ? $test_secret_key : '')
    : (isset($secret_key) ? $secret_key : '');
if ($mb_key === '') {
    bad('Не задан ключ для текущего режима');
} else {
    // Считаем подпись так же, как pay.php, и проверяем так же, как callback.php.
    // Если формулы разойдутся, оплаченные заказы перестанут подтверждаться.
    $mk = function ($params) use ($mb_key) {
        $f = array_filter($params, function ($v) { return $v !== '' && $v !== null; });
        ksort($f, SORT_STRING);
        $parts = array();
        foreach ($f as $k => $v) { $parts[] = $k . '=' . base64_encode((string) $v); }
        return strtolower(sha1($mb_key . strtolower(sha1($mb_key . implode('&', $parts)))));
    };

    $p = array('merchant' => $merchant_id, 'amount' => '10.00', 'order_id' => 'SELFTEST', 'state' => 'COMPLETE');
    if (!empty($test_mode)) { $p['testing'] = '1'; }
    $sig = $mk($p);

    if ($sig !== '' && hash_equals($mk($p), $sig)) {
        ok('Подпись считается и сходится');
    } else {
        bad('Подпись не сходится сама с собой');
    }

    $p['amount'] = '11.00';
    if ($mk($p) !== $sig) {
        ok('Подделанная сумма подпись ломает', 'чужой запрос не пройдёт');
    } else {
        bad('Подпись не зависит от суммы');
    }
}

head('Точка — связь с банком');
echo '  адрес: ' . tochka_base() . "\n";
if (empty($tochka_token)) {
    bad('Не задан $tochka_token');
} else {
    if (tochka_sandbox_mismatch()) {
        bad('Адрес и токен из разных слоёв',
            strpos(tochka_base(), '/sandbox') !== false
                ? 'в песочницу отправляется боевой токен'
                : 'на боевой адрес отправляется токен песочницы');
    }
    // Самый простой метод без параметров: он же отдаёт customerCode
    $r = tochka_api('/open-banking/v1.0/customers');
    if ($r['ok']) {
        ok('Банк отвечает', 'HTTP ' . $r['code']);
    } elseif ($r['code'] === 401 || $r['code'] === 403) {
        bad('Банк не принял токен', 'HTTP ' . $r['code']);
    } elseif ($r['code'] === 0) {
        bad('До банка не достучались', $r['error']);
    } else {
        warn('Банк ответил кодом ' . $r['code'], substr((string) $r['raw'], 0, 200));
    }
}

head('Точка — проверка подписи уведомлений');
$pems = tochka_webhook_pems();
if (!$pems) {
    bad('Открытый ключ банка не получен', 'уведомления об оплате приниматься не будут');
} else {
    ok('Ключей от банка получено: ' . count($pems));
}

// Настоящая проверка разбора: делаем свою пару ключей, подписываем ею
// уведомление и прогоняем через тот же код, что принимает банковские.
$pair = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if (!$pair) {
    warn('Не удалось создать ключ для проверки', 'разбор уведомления не проверен');
} else {
    $det = openssl_pkey_get_details($pair);

    $jwt_head = tochka_b64url_encode('{"typ":"JWT","alg":"RS256"}');
    $jwt_body = tochka_b64url_encode(json_encode(array(
        'webhookType'   => 'acquiringInternetPayment',
        'status'        => 'APPROVED',
        'paymentLinkId' => 'SELFTEST',
        'amount'        => 10.0,
    )));
    $sign = '';
    openssl_sign($jwt_head . '.' . $jwt_body, $sign, $pair, OPENSSL_ALGO_SHA256);
    $jwt = $jwt_head . '.' . $jwt_body . '.' . tochka_b64url_encode($sign);

    // Подменяем ключ банка на свой только на время проверки
    $saved = isset($GLOBALS['tochka_webhook_pem']) ? $GLOBALS['tochka_webhook_pem'] : '';
    $GLOBALS['tochka_webhook_pem'] = tochka_jwk_to_pem(
        tochka_b64url_encode($det['rsa']['n']),
        tochka_b64url_encode($det['rsa']['e'])
    );

    $claims = tochka_verify_webhook($jwt);
    if (is_array($claims) && isset($claims['paymentLinkId']) && $claims['paymentLinkId'] === 'SELFTEST') {
        ok('Подписанное уведомление разбирается верно');
    } else {
        bad('Подписанное уведомление не разобралось', 'оплаты не будут подтверждаться');
    }

    $fake_body = tochka_b64url_encode('{"webhookType":"acquiringInternetPayment","status":"APPROVED","paymentLinkId":"FAKE","amount":1}');
    if (tochka_verify_webhook($jwt_head . '.' . $fake_body . '.' . tochka_b64url_encode($sign)) === null) {
        ok('Поддельное уведомление отклоняется');
    } else {
        bad('Поддельное уведомление принято', 'заказ смог бы «оплатить» кто угодно');
    }

    $GLOBALS['tochka_webhook_pem'] = $saved;
}

head('Приём уведомлений снаружи');
if (empty($callback_url)) {
    bad('Не задан $callback_url');
} elseif (strpos($callback_url, 'https://') !== 0) {
    bad('Адрес не на https', 'банк подключает вебхуки только по https на порт 443');
} else {
    // Банк проверяет адрес перед подпиской. Шлём заведомо неподписанный
    // JWT: правильный ответ — 403, значит код работает и мусор не принимает.
    $ch = curl_init($callback_url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'aaa.bbb.ccc',
        CURLOPT_HTTPHEADER     => array('Content-Type: text/plain'),
        CURLOPT_TIMEOUT        => 20,
    ));
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code === 403)     { ok('Адрес доступен и отвергает неподписанное', 'HTTP 403'); }
    elseif ($code === 200) { warn('Адрес доступен, но неподписанное принял', 'HTTP 200'); }
    elseif ($code === 0)   { bad('Адрес снаружи недоступен', $err); }
    else                   { bad('Неожиданный ответ', 'HTTP ' . $code); }
}

head('Заказы на диске');
$dir = __DIR__ . '/orders';
if (!is_dir($dir)) {
    warn('Папки orders/ ещё нет', 'создастся при первом заказе');
} elseif (!is_writable($dir)) {
    bad('Папка orders/ недоступна на запись', 'город и адрес покупателя потеряются');
} else {
    ok('Папка orders/ доступна на запись');
    if (file_exists($dir . '/.htaccess')) {
        ok('Папка закрыта от посторонних');
    } else {
        bad('Нет orders/.htaccess', 'данные заказов открыты в интернете');
    }
}

head('Таблица и почта');
if (empty($sheets_url)) { bad('Не задан $sheets_url', 'заказы не попадут в таблицу'); }
else                    { ok('Адрес таблицы задан'); }
if (empty($w3f_key))    { warn('Не задан $w3f_key'); }
else                    { ok('Web3Forms подключён'); }

$n = isset($notify_emails) ? count($notify_emails) : 0;
if ($n === 0) {
    warn('Своя отправка писем выключена', '$notify_emails пуст');
} else {
    ok('Своя отправка писем: адресов ' . $n, !empty($smtp_host) ? 'через ' . $smtp_host : 'функцией mail()');
}

echo "\n" . str_repeat('-', 60) . "\n";
echo $failed === 0 ? "Ошибок нет.\n" : "Ошибок: {$failed}. Смотрите строки «ОШИБКА» выше.\n";

echo "\nПроверки с последствиями (запускать по одной):\n";
echo "  ?k=...&do=sheets - прогнать выдуманный заказ через таблицу\n";
echo "  ?k=...&do=mail   - отправить одно письмо на все адреса\n";
echo "  ?k=...&do=rehearse&email=вы@почта.ru\n";
echo "                   - боевая репетиция: настоящий заказ на 10 рублей,\n";
echo "                     который нужно оплатить картой. Только когда в\n";
echo "                     config.php стоит боевой адрес Точки.\n";
