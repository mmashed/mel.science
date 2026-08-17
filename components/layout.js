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
