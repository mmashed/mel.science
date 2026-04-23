# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Science Kids** — статический сайт для продажи складских остатков наборов MEL Science. Написан на vanilla HTML/CSS/JS без фреймворков и сборщиков. Язык интерфейса: русский.

## Development

Сборка не требуется. Открывать файлы напрямую в браузере или через любой статический сервер:

```bash
# Python
python -m http.server 8080 --directory mel.science

# Node
npx serve mel.science
```

Тестирование — ручное в браузере. Автоматизированных тестов нет.

## Architecture

```
mel.science/
├── index.html               # Главная: каталог товаров, вся логика корзины/модалок
├── about-melscience.html    # Страница о бренде (3D-атом на Three.js)
├── 404.html                 # Интерактивная 404 (пазл на canvas)
├── vercel.json              # cleanUrls: true — убирает .html в продакшене
├── styles/
│   ├── global.css           # Переменные, reset, nav, footer, .catalog-section, reveal
│   ├── catalog.css          # Hero, карточки, фильтры, модалки, корзина (index.html)
│   ├── about.css            # Стили страницы about-melscience.html
│   └── 404.css              # Стили пазла 404.html
├── components/
│   └── layout.js            # IIFE: вставляет nav в #site-header, footer в #site-footer
└── assets/icons/            # SVG/PNG фавиконы
```

**Новая страница:** создать `pagename.html` + `styles/pagename.css`, подключить оба файла:
```html
<link rel="stylesheet" href="styles/global.css" />
<link rel="stylesheet" href="styles/pagename.css" />
```
Пути — относительные (не root-relative), чтобы работало и при открытии через `file://`.

## CSS-архитектура

`global.css` содержит всё, что используется на нескольких страницах: переменные, nav, footer, `.catalog-section` / `.section-header` / `.section-title` / `.section-count`, `.hero-eyebrow`, `.reveal`. Страничные CSS содержат только то, что уникально для этой страницы, включая свои `@media`-блоки.

## Design System

**Цвета (CSS-переменные):**
- `--bg / --bg2 / --bg3`: `#0a0a0a / #111 / #181818` — фоны
- `--accent`: `#c8ff00` — единственный акцентный цвет (кнопки, цены, выделения)
- `--line`: `#2a2a2a` — все рамки и разделители
- `--text / --text-muted / --text-dim`: `#f0f0f0 / #666 / #999`

**Шрифты** (подключены через Google Fonts):
- `Bebas Neue` — заголовки и дисплейные элементы
- `Tektur` — основной текст
- `IBM Plex Mono` — метки, кнопки, монопространственные подписи (всегда `uppercase`)

**Правила стиля:** border-radius нигде не используется. Переходы: `.15s` для цвета/рамки, `.4s ease` для изображений.

## Key Patterns

**Структура секции:**
```html
<section class="catalog-section">
  <div class="section-header">
    <h2 class="section-title">Название</h2>
    <span class="section-count">// N позиций</span>
  </div>
</section>
```

**Форма с Web3Forms** (ключ: `b19e7dd9-9b38-4009-a408-10fe3764d836`):
```html
<div class="form-box">
  <div id="FORM-body">
    <form id="FORM-id">
      <input type="hidden" name="access_key" value="b19e7dd9-9b38-4009-a408-10fe3764d836" />
      <input type="hidden" name="subject" value="Тема" />
      <!-- поля .field > label + input -->
      <button type="submit" class="btn-submit">Отправить</button>
    </form>
  </div>
  <div class="form-success" id="FORM-success"><!-- успех --></div>
</div>
```

**Модалка:**
```html
<div class="modal-overlay" id="NAME-overlay" onclick="closeNAMEOutside(event)">
  <div class="modal">
    <button class="modal-close" onclick="closeNAMEModal()">×</button>
  </div>
</div>
```
JS: добавить/убрать класс `open` на overlay + переключить `document.body.style.overflow = 'hidden'`.

**Анимация появления:** добавить класс `reveal` на элементы, вызвать `observeReveal()` после рендера.

**Маска телефона:** `initPhone('input-id')` — форматирует `+7 XXX XXX-XX-XX`, валидирует 11 цифр.

## Data

Все товары захардкожены в массиве `products` в `index.html`. Изображения хранятся на Cloudinary CDN. `reference/mel-science-page.md` — маркетинговые тексты оригинального сайта MEL Science.
