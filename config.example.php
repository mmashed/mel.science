<?php
// Скопируйте этот файл в config.php и заполните значениями из ЛК Модульбанка.
// config.php НЕ попадает в git и хранится только на сервере.

// Какая платёжная система принимает оплату: 'modulbank' или 'tochka'.
// Пока здесь 'modulbank', настройки Точки ниже ни на что не влияют —
// можно заполнять их спокойно, сайт продолжит работать по-старому.
$payment_provider = 'modulbank';

// ── Точка Банк ──
// Боевой адрес; для проб — https://enter.tochka.com/sandbox/v2 с токеном sandbox.jwt.token
$tochka_base      = 'https://enter.tochka.com/uapi';
// JWT и client_id из «Сервисы» → «Интеграции и API» → «Создать JWT-ключ».
// Срок жизни ограничен: при перевыпуске меняются ОБА значения.
$tochka_token     = '';
$tochka_client_id = '';
// Узнаются один раз через /tochka-setup.php?k=<ключ ниже>
$tochka_customer_code = '';
$tochka_merchant_id   = '';
// Способы оплаты: card, sbp, tinkoff, dolyame
$tochka_payment_modes = array('card', 'sbp');
// Произвольная строка — пароль к служебной странице /tochka-setup.php
$tochka_setup_key     = '';
// Проверка подписи уведомлений об оплате. Нужно заполнить ОДНО из двух:
// либо открытый ключ банка в формате PEM, либо адрес, откуда его забрать.
// PEM можно получить готовым: /tochka-setup.php?k=<ключ>&do=pem
$tochka_webhook_pem   = '';
$tochka_jwks_url      = '';
// Данные продавца для кассового чека
$tochka_supplier_name  = 'ИП Янко Виталий Сергеевич';
$tochka_supplier_inn   = '781711142300';
$tochka_supplier_phone = '+79119612835';

// Данные из личного кабинета Модульбанка (раздел «Интернет-эквайринг»):
$merchant_id     = '3f00b481-1427-41ab-874b-c14f7018527a';
$secret_key      = '5CFD85C0ECB6F860446BDF104C759EAE';
$test_secret_key = '1C3D17C55DA2661B45282BE150103E8D';

// true  = тестовый режим (деньги не списываются, используйте test_secret_key)
// false = боевой режим   (реальные платежи, используйте secret_key)
$test_mode = true;

// URL страниц сайта (замените ВАШ-ДОМЕН.ru на реальный домен):
$success_url  = 'https://science-kids.ru/payment-success';
$callback_url = 'https://science-kids.ru/callback.php';

// Система налогообложения для чеков (54-ФЗ).
// Распространённые значения: usn_income, usn_income_outcome, osn, patent
$sno = 'usn_income';

// Ключ Web3Forms для email-уведомлений (не менять):
$w3f_key = 'b19e7dd9-9b38-4009-a408-10fe3764d836';

// ── Отправка писем с сервера (работает параллельно с Web3Forms) ──
// Адреса, на которые дублируются заявки и оплаченные заказы.
// Можно указать сколько угодно. Пустой массив = отправка выключена.
$notify_emails = array(
    'yanko@softwarelead.pro',
    'ВТОРОЙ-ЯЩИК@example.com',
);

// Адрес отправителя. При отправке через SMTP он должен совпадать
// с $smtp_user, иначе почтовый провайдер отклонит письмо.
$mail_from = 'ваш-ящик@gmail.com';

// ── SMTP через существующий ящик ──
// Заводить новый ящик не нужно — подойдёт любой ваш действующий.
// Если $smtp_host пустой, письма уходят функцией mail(); у домена
// science-kids.ru нет SPF и DKIM, поэтому Gmail их отбрасывает.
//
// Gmail:  smtp.gmail.com,  порт 465, secure 'ssl'
//         пароль — только «Пароль приложения» (нужна двухэтапная
//         аутентификация): https://myaccount.google.com/apppasswords
// Яндекс: smtp.yandex.ru,  порт 465, secure 'ssl', тоже пароль приложения
// Mail.ru: smtp.mail.ru,   порт 465, secure 'ssl'
$smtp_host   = 'smtp.gmail.com';
$smtp_port   = 465;
$smtp_secure = 'ssl';           // 'ssl' для порта 465, 'tls' для 587
$smtp_user   = 'ваш-ящик@gmail.com';
$smtp_pass   = 'пароль приложения';

// URL Google Apps Script для записи заявок в таблицу:
$sheets_url = 'https://script.google.com/macros/s/AKfycbxOvJskB0s3kFSaM2bQbpDNOZ_ODauHX2730JauNPCFOoCKphBdlFdXqUNCkCEig52N/exec';
?>
