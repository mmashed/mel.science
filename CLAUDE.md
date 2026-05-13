# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Science Kids** — статический сайт для продажи складских остатков наборов MEL Science. Написан на vanilla HTML/CSS/JS без фреймворков и сборщиков. Язык интерфейса: русский.

## Development

Сборка не требуется. Открывать через любой статический сервер:

```bash
python -m http.server 8080 --directory mel.science
# или
npx serve mel.science
```

Тестирование — ручное в браузере. Автоматизированных тестов нет.

## Deploy

Деплой через GitHub Actions → FTP на Beget (`.github/workflows/deploy.yml`). Каждый `push` в `main` автоматически обновляет сайт. Секреты: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`.

Clean URLs на хостинге обеспечивает `.htaccess` (Apache/Beget). На Vercel — `vercel.json`.

## Architecture

```
mel.science/
├── index.html               # Главная: каталог всех товаров, корзина, модалки
├── chemistry.html           # Каталог химии: 5 карточек бандлов
├── physics.html             # Каталог физики
├── medicine.html            # Каталог медицины
├── old-catalog.html         # Все наборы по отдельности (noindex, для внутреннего use)
├── about-melscience.html    # О бренде (3D-атом на Three.js)
├── 404.html                 # Интерактивная 404 (пазл на canvas)
├── chemistry/               # Страницы бандлов (bundle-pro-8, bundle-fire-8, bundle-pro-10, bundle-combo-12, bundle-super-14)
├── styles/
│   ├── global.css           # Переменные, reset, nav, footer, .catalog-section, .reveal
│   ├── catalog.css          # Hero, карточки, фильтры, модалки, корзина
│   ├── bundle.css           # Стили страниц бандлов (hero, benefits, sets-grid, other-bundles)
│   ├── chemistry.css        # Стили chemistry.html
│   ├── physics.css / medicine.css / about.css / legal.css / 404.css
├── components/
│   └── layout.js            # IIFE: вставляет nav в #site-header, footer в #site-footer
└── assets/icons/
```

Страницы бандлов живут в `chemistry/` и подключают CSS относительными путями: `../styles/global.css`, `../styles/bundle.css`, `../components/layout.js`.

## CSS-архитектура

`global.css` — всё переиспользуемое: переменные, nav, footer, `.catalog-section` / `.section-header` / `.section-title` / `.section-count`, `.hero-eyebrow`, `.reveal`. Страничные CSS содержат только уникальное для этой страницы.

## Design System

**Цвета:**
- `--bg / --bg2 / --bg3`: `#0a0a0a / #111 / #181818`
- `--accent`: `#c8ff00` — кнопки, цены, выделения
- `--line`: `#2a2a2a` — рамки и разделители
- `--text / --text-muted / --text-dim`: `#f0f0f0 / #666 / #999`

**Шрифты:** `Bebas Neue` — заголовки; `Tektur` — основной текст; `IBM Plex Mono` — метки/кнопки (всегда `uppercase`).

`border-radius` нигде не используется. Переходы: `.15s` для цвета/рамки, `.4s ease` для изображений.

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

**Корзина (localStorage):** Корзина общая для всех страниц, хранится в `localStorage` под ключом `sk_cart`.
```javascript
let cart = JSON.parse(localStorage.getItem('sk_cart') || '{}');
function saveCart() { localStorage.setItem('sk_cart', JSON.stringify(cart)); }

function addToCart(p) {
  if (cart[p.name]) cart[p.name].qty++;
  else cart[p.name] = { name: p.name, img: p.img, price: p.price, qty: 1 };
  saveCart(); updateCartUI();
}
function removeFromCart(name) { delete cart[name]; saveCart(); updateCartUI(); renderCartModal(); }
// На каждой странице в конце init-блока обязательно:
updateCartUI();
```

**Форма с Web3Forms** (ключ: `b19e7dd9-9b38-4009-a408-10fe3764d836`):
```html
<div class="form-box">
  <div id="FORM-body">
    <form id="FORM-id">
      <input type="hidden" name="access_key" value="b19e7dd9-9b38-4009-a408-10fe3764d836" />
      <input type="hidden" name="subject" value="Тема" />
      <button type="submit" class="btn-submit">Отправить</button>
    </form>
  </div>
  <div class="form-success" id="FORM-success"></div>
</div>
```

Форма заказа всегда включает поля: Имя, Телефон (`initPhone('id')`), Город (обязательно), Адрес доставки (необязательно). Город валидируется вручную в `submitCart()` перед отправкой.

**Модалка:**
```html
<div class="modal-overlay" id="NAME-overlay" onclick="closeNAMEOutside(event)">
  <div class="modal">
    <button class="modal-close" onclick="closeNAMEModal()">×</button>
  </div>
</div>
```
JS: добавить/убрать класс `open` на overlay + `document.body.style.overflow = 'hidden'`.

**Анимация:** класс `reveal` на элементы + вызов `observeReveal()` после рендера.

**Маска телефона:** `initPhone('input-id')` — `+7 XXX XXX-XX-XX`, валидация 11 цифр.

## Data

Товары захардкожены: каталожные страницы — в массиве `products`, бандлы — непосредственно в HTML. Изображения на Cloudinary CDN. `reference/mel-science-page.md` — маркетинговые тексты MEL Science.
