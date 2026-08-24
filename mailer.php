<?php
/**
 * Отправка уведомлений почтой с сервера.
 *
 * Работает параллельно с Web3Forms и намеренно ничего не ломает: если почта
 * не настроена в config.php или отправка не удалась, вызывающий код просто
 * продолжает работу — письмо через Web3Forms уходит как раньше.
 */

/** Убирает переводы строк — защита от подстановки лишних заголовков письма. */
function sk_mail_clean($value) {
    return trim(str_replace(array("\r", "\n", '%0a', '%0d', '%0A', '%0D'), ' ', (string) $value));
}

/**
 * @param string       $subject тема письма
 * @param string       $body    текст письма
 * @param array|string $to      получатели ($notify_emails из config.php)
 * @param string       $from    ящик на домене сайта ($mail_from из config.php)
 * @return bool ушло ли хотя бы одно письмо
 */
function sk_notify($subject, $body, $to, $from) {
    if (empty($to) || empty($from)) {
        return false; // не настроено — тихо выходим, основной канал не трогаем
    }

    $from = sk_mail_clean($from);
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Кириллическую тему обязательно кодировать, иначе часть клиентов покажет мусор
    $subject_encoded = '=?UTF-8?B?' . base64_encode(sk_mail_clean($subject)) . '?=';

    $headers = "MIME-Version: 1.0\r\n"
             . "From: Science Kids <{$from}>\r\n"
             . "Reply-To: {$from}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n";

    $sent = false;
    foreach ((array) $to as $email) {
        $email = sk_mail_clean($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        if (@mail($email, $subject_encoded, $body, $headers, '-f' . $from)) {
            $sent = true;
        }
    }

    return $sent;
}
