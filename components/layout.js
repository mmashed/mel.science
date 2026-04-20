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
      + '<a href="index.html" class="nav-logo" aria-label="Science Kids">' + NAV_LOGO + '</a>'
      + '<div class="nav-right">'
      + '<a href="about-melscience.html" class="nav-link">О MEL Science</a>'
      + '<button class="cart-btn" onclick="openCartModal()">'
      + 'Корзина <span class="cart-count hidden" id="nav-cart-count">0</span>'
      + '</button>'
      + '<button class="nav-cta" onclick="openOrderModal(\'\')">Оставить заявку</button>'
      + '</div>'
      + '</nav>';
  }

  var footerEl = document.getElementById('site-footer');
  if (footerEl) {
    footerEl.innerHTML = '<footer>'
      + '<div class="footer-logo">' + FOOTER_LOGO + '</div>'
      + '<div class="footer-note">© 2026 · Складские остатки MEL Science · Все права защищены</div>'
      + '</footer>';
  }
})();
