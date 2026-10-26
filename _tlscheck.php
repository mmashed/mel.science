<?php
/**
 * ВРЕМЕННЫЙ диагностический скрипт. Удалить сразу после проверки.
 * Проверяет, доверяет ли сервер сертификату Минцифры, которым подписан API Точки.
 * Без него pay.php не сможет создать платёжную ссылку.
 */
if (!isset($_GET['k']) || $_GET['k'] !== 'a7f3c912e4') {
    http_response_code(404);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');

echo "PHP: " . PHP_VERSION . "\n";
echo "cURL: " . (function_exists('curl_init') ? 'есть' : 'НЕТ') . "\n";
echo "CA bundle (openssl.cafile): " . (ini_get('openssl.cafile') ?: '(не задан)') . "\n";
echo "CA bundle (curl.cainfo): "   . (ini_get('curl.cainfo')   ?: '(не задан)') . "\n\n";

foreach (array('https://enter.tochka.com/', 'https://api.tochka.com/') as $url) {
    echo "--- {$url} ---\n";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "cURL: " . ($err === '' ? "TLS ОК, HTTP {$code}" : "ОШИБКА — {$err}") . "\n";
    }

    $ctx = stream_context_create(array(
        'http' => array('method' => 'HEAD', 'timeout' => 15, 'ignore_errors' => true),
        'ssl'  => array('verify_peer' => true, 'verify_peer_name' => true),
    ));
    $res = @file_get_contents($url, false, $ctx);
    echo "file_get_contents: " . ($res !== false || !empty($http_response_header) ? 'TLS ОК' : 'ОШИБКА') . "\n\n";
}
