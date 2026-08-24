<?php
/**
 * Отправка уведомлений почтой с сервера.
 *
 * Два транспорта:
 *   1. SMTP через существующий ящик — если в config.php задан $smtp_host.
 *      Подпись SPF/DKIM обеспечивает провайдер ящика, письма доходят.
 *   2. Обычная mail() — запасной вариант. У домена science-kids.ru нет
 *      SPF и DKIM, поэтому Gmail такие письма отбрасывает молча.
 *
 * Работает параллельно с Web3Forms и ничего не ломает: при любой ошибке
 * функция возвращает false, а письмо через Web3Forms уходит как раньше.
 */

/** Стадия последней ошибки — для диагностики. Учётных данных не содержит. */
$GLOBALS['sk_mail_error'] = '';

function sk_mail_fail($stage) {
    $GLOBALS['sk_mail_error'] = $stage;
    return false;
}

/** Убирает переводы строк — защита от подстановки лишних заголовков письма. */
function sk_mail_clean($value) {
    return trim(str_replace(array("\r", "\n", '%0a', '%0d', '%0A', '%0D'), ' ', (string) $value));
}

/** Настройки SMTP из config.php; null — значит SMTP не настроен. */
function sk_smtp_settings() {
    $host = isset($GLOBALS['smtp_host']) ? trim((string) $GLOBALS['smtp_host']) : '';
    if ($host === '') {
        return null;
    }
    return array(
        'host'   => $host,
        'port'   => isset($GLOBALS['smtp_port'])   ? (int) $GLOBALS['smtp_port'] : 465,
        'secure' => isset($GLOBALS['smtp_secure']) ? strtolower(trim((string) $GLOBALS['smtp_secure'])) : 'ssl',
        'user'   => isset($GLOBALS['smtp_user'])   ? (string) $GLOBALS['smtp_user'] : '',
        'pass'   => isset($GLOBALS['smtp_pass'])   ? (string) $GLOBALS['smtp_pass'] : '',
    );
}

/**
 * Читает ответ сервера целиком: многострочный ответ помечен дефисом
 * на 4-й позиции ("250-..."), последняя строка — пробелом ("250 ...").
 */
function sk_smtp_reply($fh) {
    $out = '';
    while (($line = fgets($fh, 1024)) !== false) {
        $out .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return $out;
}

function sk_smtp_expect($fh, $codes) {
    $reply = sk_smtp_reply($fh);
    return in_array((int) substr($reply, 0, 3), (array) $codes, true);
}

function sk_smtp_cmd($fh, $cmd, $codes) {
    fwrite($fh, $cmd . "\r\n");
    return sk_smtp_expect($fh, $codes);
}

function sk_smtp_deliver($recipients, $subject_encoded, $body, $from, $cfg) {
    $transport = ($cfg['secure'] === 'ssl') ? 'ssl://' : 'tcp://';
    $fh = @stream_socket_client(
        $transport . $cfg['host'] . ':' . $cfg['port'],
        $errno, $errstr, 20, STREAM_CLIENT_CONNECT
    );
    if (!$fh) {
        return sk_mail_fail('smtp_connect');
    }
    stream_set_timeout($fh, 20);

    if (!sk_smtp_expect($fh, 220)) { fclose($fh); return sk_mail_fail('smtp_greeting'); }

    $ehlo = 'EHLO science-kids.ru';
    if (!sk_smtp_cmd($fh, $ehlo, 250)) { fclose($fh); return sk_mail_fail('smtp_ehlo'); }

    if ($cfg['secure'] === 'tls') {
        if (!sk_smtp_cmd($fh, 'STARTTLS', 220)) { fclose($fh); return sk_mail_fail('smtp_starttls'); }
        if (!@stream_socket_enable_crypto($fh, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fh); return sk_mail_fail('smtp_tls');
        }
        if (!sk_smtp_cmd($fh, $ehlo, 250)) { fclose($fh); return sk_mail_fail('smtp_ehlo2'); }
    }

    if ($cfg['user'] !== '') {
        if (!sk_smtp_cmd($fh, 'AUTH LOGIN', 334))                  { fclose($fh); return sk_mail_fail('smtp_auth'); }
        if (!sk_smtp_cmd($fh, base64_encode($cfg['user']), 334))    { fclose($fh); return sk_mail_fail('smtp_login'); }
        if (!sk_smtp_cmd($fh, base64_encode($cfg['pass']), 235))    { fclose($fh); return sk_mail_fail('smtp_password'); }
    }

    $sent = false;
    foreach ($recipients as $to) {
        if (!sk_smtp_cmd($fh, 'MAIL FROM:<' . $from . '>', 250)) {
            sk_smtp_cmd($fh, 'RSET', 250); $GLOBALS['sk_mail_error'] = 'smtp_from'; continue;
        }
        if (!sk_smtp_cmd($fh, 'RCPT TO:<' . $to . '>', array(250, 251))) {
            sk_smtp_cmd($fh, 'RSET', 250); $GLOBALS['sk_mail_error'] = 'smtp_rcpt'; continue;
        }
        if (!sk_smtp_cmd($fh, 'DATA', 354)) {
            sk_smtp_cmd($fh, 'RSET', 250); $GLOBALS['sk_mail_error'] = 'smtp_data'; continue;
        }

        $headers = "MIME-Version: 1.0\r\n"
                 . 'Date: ' . date('r') . "\r\n"
                 . "From: Science Kids <{$from}>\r\n"
                 . "To: <{$to}>\r\n"
                 . "Subject: {$subject_encoded}\r\n"
                 . "Reply-To: {$from}\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: 8bit\r\n";

        // Нормализуем переводы строк и экранируем точку в начале строки:
        // одиночная точка на строке означает конец письма
        $data = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body));
        $data = preg_replace('/^\./m', '..', $data);

        fwrite($fh, $headers . "\r\n" . $data . "\r\n.\r\n");
        if (sk_smtp_expect($fh, 250)) {
            $sent = true;
        } else {
            $GLOBALS['sk_mail_error'] = 'smtp_send';
        }
    }

    sk_smtp_cmd($fh, 'QUIT', 221);
    fclose($fh);

    if ($sent) {
        $GLOBALS['sk_mail_error'] = '';
    }
    return $sent;
}

/**
 * @param string       $subject тема письма
 * @param string       $body    текст письма
 * @param array|string $to      получатели ($notify_emails из config.php)
 * @param string       $from    адрес отправителя ($mail_from из config.php)
 * @return bool ушло ли хотя бы одно письмо
 */
function sk_notify($subject, $body, $to, $from) {
    $GLOBALS['sk_mail_error'] = '';

    if (empty($to) || empty($from)) {
        return sk_mail_fail('not_configured');
    }

    $from = sk_mail_clean($from);
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return sk_mail_fail('bad_from');
    }

    $recipients = array();
    foreach ((array) $to as $email) {
        $email = sk_mail_clean($email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $email;
        }
    }
    if (!$recipients) {
        return sk_mail_fail('bad_recipients');
    }

    // Кириллическую тему обязательно кодировать, иначе часть клиентов покажет мусор
    $subject_encoded = '=?UTF-8?B?' . base64_encode(sk_mail_clean($subject)) . '?=';

    $smtp = sk_smtp_settings();
    if ($smtp !== null) {
        return sk_smtp_deliver($recipients, $subject_encoded, $body, $from, $smtp);
    }

    // Запасной транспорт — обычная mail()
    $headers = "MIME-Version: 1.0\r\n"
             . "From: Science Kids <{$from}>\r\n"
             . "Reply-To: {$from}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n";

    $sent = false;
    foreach ($recipients as $email) {
        if (@mail($email, $subject_encoded, $body, $headers, '-f' . $from)) {
            $sent = true;
        }
    }

    return $sent ? $sent : sk_mail_fail('mail_failed');
}
