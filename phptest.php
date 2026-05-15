<?php
echo 'PHP version: ' . PHP_VERSION . '<br>';
echo 'random_bytes: ' . (function_exists('random_bytes') ? 'OK' : 'MISSING') . '<br>';
echo 'mb_substr: '    . (function_exists('mb_substr')    ? 'OK' : 'MISSING') . '<br>';
echo 'hash_equals: '  . (function_exists('hash_equals')  ? 'OK' : 'MISSING') . '<br>';
echo 'config.php exists: ' . (file_exists(__DIR__ . '/config.php') ? 'YES' : 'NO') . '<br>';
if (file_exists(__DIR__ . '/config.php')) {
    ob_start();
    $e = null;
    try { include __DIR__ . '/config.php'; } catch (Throwable $e) {}
    ob_end_clean();
    echo 'config.php loaded: ' . ($e ? 'ERROR: ' . $e->getMessage() : 'OK') . '<br>';
}
