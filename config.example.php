<?php
// Скопируйте этот файл в config.php и заполните значениями из ЛК Модульбанка.
// config.php НЕ попадает в git и хранится только на сервере.

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

// URL Google Apps Script для записи заявок в таблицу:
$sheets_url = 'https://script.google.com/macros/s/AKfycbxOvJskB0s3kFSaM2bQbpDNOZ_ODauHX2730JauNPCFOoCKphBdlFdXqUNCkCEig52N/exec';
?>
