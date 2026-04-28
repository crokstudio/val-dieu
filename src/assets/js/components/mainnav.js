const navbar = document.querySelector('.mainnav__container');

window.addEventListener('scroll', () => {
  if (window.scrollY > 10) {
    navbar.classList.add('nav-scrolled');
  } else {
    navbar.classList.remove('nav-scrolled');
  }
});