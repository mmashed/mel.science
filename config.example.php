<?php
// Скопируйте этот файл в config.php и заполните значениями из ЛК Модульбанка.
// config.php НЕ попадает в git и хранится только на сервере.

// Данные из личного кабинета Модульбанка (раздел «Интернет-эквайринг»):
$merchant_id     = 'вставить_merchant_id';
$secret_key      = 'вставить_secret_key';
$test_secret_key = 'вставить_test_secret_key';

// true  = тестовый режим (деньги не списываются, используйте test_secret_key)
// false = боевой режим   (реальные платежи, используйте secret_key)
$test_mode = true;

// URL страниц сайта (замените ВАШ-ДОМЕН.ru на реальный домен):
$success_url  = 'https://ВАШ-ДОМЕН.ru/payment-success';
$callback_url = 'https://ВАШ-ДОМЕН.ru/callback.php';

// Система налогообложения для чеков (54-ФЗ).
// Распространённые значения: usn_income, usn_income_outcome, osn, patent
$sno = 'usn_income';

// Ключ Web3Forms для email-уведомлений (не менять):
$w3f_key = 'b19e7dd9-9b38-4009-a408-10fe3764d836';
