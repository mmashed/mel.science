<?php
echo '<pre>';
echo 'PHP version: ' . PHP_VERSION . "\n";
echo 'openssl_random_pseudo_bytes: ' . (function_exists('openssl_random_pseudo_bytes') ? 'OK' : 'MISSING') . "\n";
echo 'mb_substr: '    . (function_exists('mb_substr')    ? 'OK' : 'MISSING') . "\n";

// Check config
echo "\n--- config.php ---\n";
if (!file_exists(__DIR__ . '/config.php')) {
    echo "config.php: NOT FOUND\n";
} else {
    include __DIR__ . '/config.php';
    echo 'merchant_id length: '     . strlen($merchant_id)     . ' (expected 36)' . "\n";
    echo 'secret_key length: '      . strlen($secret_key)      . ' (expected 32)' . "\n";
    echo 'test_secret_key length: ' . strlen($test_secret_key) . ' (expected 32)' . "\n";
    echo 'test_mode: '              . ($test_mode ? 'true' : 'false') . "\n";
    echo 'success_url: '            . $success_url . "\n";
}

// Verify signature algorithm against documentation example
echo "\n--- Signature algorithm test ---\n";
$test_key = '00112233445566778899aabbccddeeff';
$test_str = 'amount=OTcz&client_email=dGVzdEB0ZXN0LnJ1&client_phone=KzcgOTEyIDk4NzY1NDM=&description=0JfQsNC60LDQtyDihJYxNDQyNTg0MA==&merchant=YWQyNWVmMDYtMTgyNC00MTNmLThlZjEtYzA4MTE1YjliOTc5&order_id=MTQ0MjU4NDA=&receipt_contact=dGVzdEBtYWlsLmNvbQ==&receipt_items=W3siZGlzY291bnRfc3VtIjogNDAsICJuYW1lIjogItCi0L7QstCw0YAgMSIsICJwYXltZW50X21ldGhvZCI6ICJmdWxsX3ByZXBheW1lbnQiLCAicGF5bWVudF9vYmplY3QiOiAiY29tbW9kaXR5IiwgInByaWNlIjogNDgsICJxdWFudGl0eSI6IDEwLCAic25vIjogIm9zbiIsICJ2YXQiOiAidmF0MTAifSwgeyJuYW1lIjogItCi0L7QstCw0YAgMiIsICJwYXltZW50X21ldGhvZCI6ICJmdWxsX3ByZXBheW1lbnQiLCAicGF5bWVudF9vYmplY3QiOiAiY29tbW9kaXR5IiwgInByaWNlIjogNTMzLCAicXVhbnRpdHkiOiAxLCAic25vIjogIm9zbiIsICJ2YXQiOiAidmF0MTAifV0=&salt=ZFBVVEx0Yk1mY1RHemthQm5HdHNlS2xjUXltQ0xyWUk=&success_url=aHR0cDovL215YXdlc29tZXNpdGUuY29tL3BheW1lbnRfc3VjY2Vzcw==&testing=MQ==&unix_timestamp=MTU3MzQ1MTE2MA==';
$inner    = strtolower(sha1($test_key . $test_str));
$computed = strtolower(sha1($test_key . $inner));
$expected = '9b28fa592922dc8a0c1ba2e40f2c0432aa617afd';
echo 'Computed : ' . $computed . "\n";
echo 'Expected : ' . $expected . "\n";
echo 'Match: ' . ($computed === $expected ? 'YES' : 'NO') . "\n";
echo '</pre>';
