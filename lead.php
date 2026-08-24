<?php
/**
 * Приём заявок с сайта и отправка их почтой с сервера.
 *
 * Работает параллельно с Web3Forms: браузер отправляет заявку в оба места,
 * поэтому даже если этот обработчик недоступен, заявка всё равно дойдёт.
 */
ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false));
    exit;
}

$config = __DIR__ . '/config.php';
if (!file_exists($config)) {
    http_response_code(500);
    echo json_encode(array('success' => false));
    exit;
}
require $config;
require __DIR__ . '/mailer.php';

// Данные приходят JSON-ом; на всякий случай поддерживаем и обычный POST
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}

// Скрытое поле-ловушка: если оно заполнено, это бот — отвечаем успехом молча
if (!empty($data['botcheck'])) {
    echo json_encode(array('success' => true));
    exit;
}

function lead_val($data, $key, $limit = 200) {
    $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
}

$name    = lead_val($data, 'name');
$contact = lead_val($data, 'contact');
$city    = lead_val($data, 'city');
$address = lead_val($data, 'address');
$product = lead_val($data, 'product');
$message = lead_val($data, 'message', 4000);
$page    = lead_val($data, 'page');

// Пустые отправки не рассылаем
if ($contact === '' && $message === '') {
    http_response_code(400);
    echo json_encode(array('success' => false));
    exit;
}

$subject = $product !== '' ? "Заявка на набор: {$product}" : 'Новая заявка — Science Kids';

$body = "Новая заявка с сайта\n\n"
      . ($name    !== '' ? "Имя: {$name}\n"        : '')
      . ($contact !== '' ? "Телефон: {$contact}\n" : '')
      . ($city    !== '' ? "Город: {$city}\n"      : '')
      . ($address !== '' ? "Адрес: {$address}\n"   : '')
      . ($product !== '' ? "Набор: {$product}\n"   : '')
      . ($message !== '' ? "\n{$message}\n"        : '')
      . ($page    !== '' ? "\nСтраница: {$page}\n" : '')
      . 'Время: ' . date('d.m.Y H:i');

$ok = sk_notify(
    $subject,
    $body,
    isset($notify_emails) ? $notify_emails : array(),
    isset($mail_from) ? $mail_from : ''
);

echo json_encode(array('success' => $ok));
