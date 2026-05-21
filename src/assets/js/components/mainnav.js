const navbar = document.querySelector('.mainnav__container');

window.addEventListener('scroll', () => {
  if (window.scrollY > 10) {
    navbar.classList.remove('nav-not-scrolled');
  } else {
    navbar.classList.add('nav-not-scrolled');
  }
});