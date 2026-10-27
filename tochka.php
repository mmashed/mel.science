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

/* ─────────── Проверка подписи уведомления ───────────
   Вебхук приходит не обычным JSON, а подписанным JWT (RS256):
   тело вида <header>.<payload>.<signature>. Подпись проверяется
   открытым ключом банка — без этого «оплатить» заказ смог бы любой,
   кто отправит запрос на наш адрес. */

function tochka_b64url_decode($s) {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) { $s .= str_repeat('=', 4 - $pad); }
    return base64_decode($s);
}

function tochka_b64url_encode($s) {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** Длина в нотации ASN.1 DER */
function tochka_asn1_len($len) {
    if ($len < 128) { return chr($len); }
    $out = '';
    while ($len > 0) { $out = chr($len & 0xFF) . $out; $len >>= 8; }
    return chr(0x80 | strlen($out)) . $out;
}

/** Целое в нотации ASN.1 DER (со старшим нулём, если выставлен знаковый бит) */
function tochka_asn1_int($bin) {
    if (ord($bin[0]) > 0x7F) { $bin = "\x00" . $bin; }
    return "\x02" . tochka_asn1_len(strlen($bin)) . $bin;
}

/** Собирает PEM открытого ключа из модуля и экспоненты JWK */
function tochka_jwk_to_pem($n_b64, $e_b64) {
    $seq = tochka_asn1_int(tochka_b64url_decode($n_b64))
         . tochka_asn1_int(tochka_b64url_decode($e_b64));
    $rsa = "\x30" . tochka_asn1_len(strlen($seq)) . $seq;
    $bit = "\x03" . tochka_asn1_len(strlen($rsa) + 1) . "\x00" . $rsa;
    $alg = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
    $spki = "\x30" . tochka_asn1_len(strlen($alg . $bit)) . $alg . $bit;
    return "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($spki), 64, "\n")
         . "-----END PUBLIC KEY-----\n";
}

/** Выбирает из ответа JWKS все ключи: он бывает и одиночным, и списком */
function tochka_jwks_keys($decoded) {
    if (!is_array($decoded)) { return array(); }
    if (isset($decoded['keys']) && is_array($decoded['keys'])) {
        $raw = $decoded['keys'];
    } elseif (isset($decoded['n'], $decoded['e'])) {
        $raw = array($decoded);
    } else {
        $raw = $decoded;
    }
    $out = array();
    foreach ($raw as $k) {
        if (is_array($k) && isset($k['n'], $k['e'])) { $out[] = $k; }
    }
    return $out;
}

/**
 * Открытые ключи банка: из config.php либо с его JWKS-адреса (с кэшем на сутки).
 * Ключей может быть несколько — например, при смене ключа банк какое-то время
 * подписывает и старым, и новым. Возвращаем все, проверять будем по очереди.
 *
 * @return array список PEM
 */
function tochka_webhook_pems() {
    if (!empty($GLOBALS['tochka_webhook_pem'])) {
        return tochka_split_pems((string) $GLOBALS['tochka_webhook_pem']);
    }
    if (empty($GLOBALS['tochka_jwks_url'])) {
        return array();
    }
    $cache = __DIR__ . '/orders/.tochka-jwks.pem';
    if (file_exists($cache) && (time() - filemtime($cache)) < 86400) {
        return tochka_split_pems(file_get_contents($cache));
    }
    $ch = curl_init((string) $GLOBALS['tochka_jwks_url']);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ));
    $raw = curl_exec($ch);
    curl_close($ch);

    $keys = tochka_jwks_keys(json_decode($raw, true));
    if (!$keys) {
        // Банк недоступен — старый кэш лучше, чем ничего
        return file_exists($cache) ? tochka_split_pems(file_get_contents($cache)) : array();
    }
    $pems = array();
    foreach ($keys as $k) {
        $pems[] = tochka_jwk_to_pem($k['n'], $k['e']);
    }
    @file_put_contents($cache, implode('', $pems));
    return $pems;
}

/** Разбирает склеенные PEM-блоки в список */
function tochka_split_pems($blob) {
    preg_match_all('/-----BEGIN PUBLIC KEY-----.*?-----END PUBLIC KEY-----/s', $blob, $m);
    return $m[0] ? $m[0] : array();
}

/**
 * Проверяет подпись уведомления и возвращает его содержимое.
 * null — подпись не сошлась или ключ недоступен; такое уведомление принимать нельзя.
 */
function tochka_verify_webhook($body) {
    $pems = tochka_webhook_pems();
    if (!$pems) { return null; }

    $parts = explode('.', trim($body));
    if (count($parts) !== 3) { return null; }

    $header = json_decode(tochka_b64url_decode($parts[0]), true);
    if (!isset($header['alg']) || $header['alg'] !== 'RS256') { return null; }

    $signed = $parts[0] . '.' . $parts[1];
    $sig    = tochka_b64url_decode($parts[2]);
    foreach ($pems as $pem) {
        if (openssl_verify($signed, $sig, $pem, OPENSSL_ALGO_SHA256) === 1) {
            return json_decode(tochka_b64url_decode($parts[1]), true);
        }
    }
    return null;
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
