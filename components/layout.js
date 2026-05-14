(function () {
  var NAV_LOGO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 210 28" height="22" aria-label="Science Kids" role="img">'
    + '<text font-family="\'Bebas Neue\', Impact, sans-serif" font-size="22" letter-spacing="2.64" fill="#f0f0f0" y="22">'
    + '<tspan>SCIENCE</tspan><tspan fill="#c8ff00">.</tspan><tspan>KIDS</tspan>'
    + '</text></svg>';

  var FOOTER_LOGO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 210 28" height="18" aria-label="Science Kids" role="img">'
    + '<text font-family="\'Bebas Neue\', Impact, sans-serif" font-size="22" letter-spacing="2.64" fill="#666" y="22">'
    + '<tspan>SCIENCE</tspan><tspan fill="#c8ff00">.</tspan><tspan>KIDS</tspan>'
    + '</text></svg>';

  var headerEl = document.getElementById('site-header');
  if (headerEl) {
    headerEl.innerHTML = '<nav>'
      + '<a href="/" class="nav-logo" aria-label="Science Kids">' + NAV_LOGO + '</a>'
      + '<div class="nav-right">'
      + '<div class="nav-dropdown-wrap">'
      + '<button class="nav-dropdown-btn">Каталог ▾</button>'
      + '<div class="nav-dropdown">'
      + '<a href="/chemistry" class="nav-dropdown-item">Химия</a>'
      + '<a href="/physics" class="nav-dropdown-item">Физика</a>'
      + '<a href="/medicine" class="nav-dropdown-item">Медицина</a>'
      + '</div>'
      + '</div>'
      + '<a href="/about-melscience" class="nav-link">О MEL Science</a>'
      + '<button class="cart-btn" onclick="openCartModal()">'
      + 'Корзина <span class="cart-count hidden" id="nav-cart-count">0</span>'
      + '</button>'
      + '<button class="nav-cta" onclick="openOrderModal(\'\')">Оставить заявку</button>'
      + '</div>'
      + '<button class="nav-burger" id="nav-burger" aria-label="Меню" onclick="toggleMobileNav()">'
      + '<span></span><span></span><span></span>'
      + '</button>'
      + '</nav>'
      + '<div class="nav-mobile" id="nav-mobile">'
      + '<div class="nav-mobile-links">'
      + '<span class="nav-mobile-label">Каталог</span>'
      + '<a href="/chemistry" class="nav-mobile-link">Химия</a>'
      + '<a href="/physics" class="nav-mobile-link">Физика</a>'
      + '<a href="/medicine" class="nav-mobile-link">Медицина</a>'
      + '<a href="/about-melscience" class="nav-mobile-link nav-mobile-link--gap">О MEL Science</a>'
      + '</div>'
      + '<div class="nav-mobile-actions">'
      + '<button class="cart-btn" onclick="closeMobileNav(); openCartModal()">Корзина</button>'
      + '<button class="nav-cta" onclick="closeMobileNav(); openOrderModal(\'\')">Оставить заявку</button>'
      + '</div>'
      + '</div>';
  }

  window.toggleMobileNav = function () {
    var menu = document.getElementById('nav-mobile');
    var burger = document.getElementById('nav-burger');
    var isOpen = menu.classList.contains('open');
    menu.classList.toggle('open', !isOpen);
    burger.classList.toggle('open', !isOpen);
    document.body.style.overflow = isOpen ? '' : 'hidden';
  };

  window.closeMobileNav = function () {
    var menu = document.getElementById('nav-mobile');
    var burger = document.getElementById('nav-burger');
    if (menu) { menu.classList.remove('open'); }
    if (burger) { burger.classList.remove('open'); }
    document.body.style.overflow = '';
  };

  var footerEl = document.getElementById('site-footer');
  if (footerEl) {
    footerEl.innerHTML = '<footer>'
      + '<div class="footer-top">'
      + '<div class="footer-left">'
      + '<div class="footer-logo">' + FOOTER_LOGO + '</div>'
      + '<div class="footer-links">'
      + '<a href="/public-offer">Публичная оферта</a>'
      + '<a href="/privacy">Политика конфиденциальности</a>'
      + '<a href="/payment">Оплата и возврат</a>'
      + '<a href="/delivery">Доставка</a>'
      + '</div>'
      + '</div>'
      + '<div class="footer-right">'
      + '<div class="footer-company">ИП Янко Виталий Сергеевич</div>'
      + '<div class="footer-req">ИНН: 781711142300 · ОГРНИП: 316784700098653</div>'
      + '<div class="footer-req">г. Санкт-Петербург, п. Металлострой, ул. Полевая, 25, 137</div>'
      + '<a href="mailto:yanko@softwarelead.pro" class="footer-req footer-email">yanko@softwarelead.pro</a>'
      + '<a href="tel:+79119612835" class="footer-req footer-email">+7 911 961-28-35</a>'
      + '</div>'
      + '</div>'
      + '<div class="footer-bottom">'
      + '<div class="footer-note">© 2026 · Складские остатки MEL Science · Все права защищены</div>'
      + '</div>'
      + '</footer>';
  }
})();
