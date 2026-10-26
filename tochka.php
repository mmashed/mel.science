<?php
/**
 * Клиент API Точка Банка.
 *
 * Базовый адрес и токен берутся из config.php:
 *   $tochka_base  — https://enter.tochka.com/uapi (боевой)
 *                   https://enter.tochka.com/sandbox/v2 (песочница)
 *   $tochka_token — JWT из «Сервисы» → «Интеграции и API»
 *
 * Совместимо с PHP 5.6 — именно эта версия стоит на хостинге.
 */

function tochka_base() {
    $base = isset($GLOBALS['tochka_base']) ? trim((string) $GLOBALS['tochka_base']) : '';
    return $base !== '' ? rtrim($base, '/') : 'https://enter.tochka.com/uapi';
}

/**
 * @param string     $path   путь после базового адреса, например /acquiring/v1.0/payments_with_receipt
 * @param string     $method GET, POST, PUT, DELETE
 * @param array|null $body   тело запроса, будет отправлено как JSON
 * @return array ok, code, error, body (разобранный JSON), raw
 */
function tochka_api($path, $method = 'GET', $body = null) {
    $token = isset($GLOBALS['tochka_token']) ? (string) $GLOBALS['tochka_token'] : '';
    if ($token === '') {
        return array('ok' => false, 'code' => 0, 'error' => 'не задан $tochka_token в config.php', 'body' => null, 'raw' => '');
    }

    $headers = array(
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    );
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init(tochka_base() . $path);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return array(
        'ok'    => ($err === '' && $code >= 200 && $code < 300),
        'code'  => $code,
        'error' => $err,
        'body'  => json_decode($raw, true),
        'raw'   => $raw,
    );
}

/**
 * Создаёт платёжную ссылку и отправляет покупателю чек по 54-ФЗ.
 * Возвращает ответ tochka_api(); при успехе в body.Data лежат paymentLink и operationId.
 */
function tochka_create_payment($order_id, $items, $client, $purpose) {
    $supplier = array();
    if (!empty($GLOBALS['tochka_supplier_inn'])) {
        $supplier = array(
            'phone'   => (string) $GLOBALS['tochka_supplier_phone'],
            'name'    => (string) $GLOBALS['tochka_supplier_name'],
            'taxCode' => (string) $GLOBALS['tochka_supplier_inn'],
        );
    }

    $amount = 0.0;
    $lines  = array();
    foreach ($items as $it) {
        $price = round(max(0.0, (float) $it['price']), 2);
        $qty   = max(1, (int) $it['qty']);
        $amount += $price * $qty;

        $line = array(
            'name'          => mb_substr((string) $it['name'], 0, 256),
            'amount'        => number_format($price, 2, '.', ''),
            'quantity'      => $qty,
            'vatType'       => 'none',           // ИП на УСН — НДС не выделяется
            'paymentMethod' => 'full_payment',
            'paymentObject' => 'goods',
            'measure'       => 'шт.',
        );
        if ($supplier) {
            $line['Supplier'] = $supplier;
        }
        $lines[] = $line;
    }

    $modes = !empty($GLOBALS['tochka_payment_modes']) ? $GLOBALS['tochka_payment_modes'] : array('card', 'sbp');

    $data = array(
        'customerCode'    => (string) $GLOBALS['tochka_customer_code'],
        'merchantId'      => (string) $GLOBALS['tochka_merchant_id'],
        'amount'          => number_format($amount, 2, '.', ''),
        'purpose'         => mb_substr($purpose, 0, 140),
        'paymentMode'     => array_values($modes),
        'paymentLinkId'   => mb_substr($order_id, 0, 45),
        'taxSystemCode'   => !empty($GLOBALS['sno']) ? $GLOBALS['sno'] : 'usn_income',
        'redirectUrl'     => (string) $GLOBALS['success_url'],
        'failRedirectUrl' => !empty($GLOBALS['fail_url']) ? (string) $GLOBALS['fail_url'] : (string) $GLOBALS['success_url'],
        'Client'          => $client,
        'Items'           => $lines,
    );
    if ($supplier) {
        $data['Supplier'] = $supplier;
    }

    return tochka_api('/acquiring/v1.0/payments_with_receipt', 'POST', array('Data' => $data));
}
